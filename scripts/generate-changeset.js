const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');
const { generateSinceTagsMetadata } = require('./scan-since-tags');

// Define allowed types
const ALLOWED_TYPES = ['feat', 'fix', 'chore', 'docs', 'perf', 'refactor', 'revert', 'style', 'test', 'ci', 'build'];

/**
 * Parse PR title for type and breaking changes
 */
function parseTitle(title) {
    // Match: type(scope)!: description or type!: description
    const typeMatch = title.match(/^(feat|fix|build|chore|ci|docs|perf|refactor|revert|style|test)(?:\([^)]+\))?(!)?:/);
    if (!typeMatch) {
        throw new Error(`PR title does not follow conventional commit format. Must start with type: ${ALLOWED_TYPES.join(', ')}`);
    }

    const type = typeMatch[1];
    const isBreaking = Boolean(typeMatch[2] || title.includes('BREAKING CHANGE'));

    if (!ALLOWED_TYPES.includes(type)) {
        throw new Error(`Invalid type "${type}". Must be one of: ${ALLOWED_TYPES.join(', ')}`);
    }

    return {
        type,
        isBreaking
    };
}

/**
 * Format the summary with type prefix
 */
function formatSummary(type, isBreaking, description) {
    return `${type}${isBreaking ? '!' : ''}: ${description.trim()}`;
}

/**
 * Extract sections from PR body
 */
function parsePRBody(body) {
    const sections = {
        description: body.match(/What does this implement\/fix\? Explain your changes\.\s*-+\s*([\s\S]*?)(?=##|$)/)?.[1]?.trim() || '',
        breaking: body.match(/#{2,3}\s*Breaking Changes\s*([\s\S]*?)(?=##|$)/)?.[1]?.trim() || '',
        upgrade: body.match(/#{2,3}\s*Upgrade Instructions\s*([\s\S]*?)(?=##|$)/)?.[1]?.trim() || ''
    };

    // Clean up any "N/A" or similar placeholders
    Object.keys(sections).forEach(key => {
        if (sections[key].toLowerCase() === 'n/a' || sections[key].toLowerCase() === 'none') {
            sections[key] = '';
        }
    });

    return sections;
}

/**
 * Create changeset using @changesets/cli
 */
async function createChangeset({ title, body, prNumber }) {
    // Parse PR title and body
    const { type, isBreaking } = parseTitle(title);
    const sections = parsePRBody(body);

    // Validate breaking changes have upgrade instructions
    if (isBreaking || sections.breaking) {
        if (!sections.breaking) {
            throw new Error('Breaking changes must be documented in the PR description');
        }
        if (!sections.upgrade) {
            throw new Error('Breaking changes must include upgrade instructions');
        }
    }

    // Get description from PR body or fallback to title
    const description = sections.description || title.split(':')[1].trim();

    // Format the summary
    const summary = formatSummary(type, isBreaking, description);

    // Determine bump type
    const bumpType = isBreaking || sections.breaking
        ? 'major'  // Breaking changes are major
        : (type === 'feat'
            ? 'minor'  // New features are minor
            : 'patch'  // Everything else is patch
        );

    // Get @since tags metadata
    const sinceMetadata = await generateSinceTagsMetadata();

    // Create a unique ID for the changeset
    const changesetId = `pr-${prNumber}-${Date.now()}`;
    const changesetDir = path.join(process.cwd(), '.changeset');
    const changesetPath = path.join(changesetDir, `${changesetId}.md`);

    // Ensure .changeset directory exists
    if (!fs.existsSync(changesetDir)) {
        fs.mkdirSync(changesetDir, { recursive: true });
    }

    // Create the changeset file content with YAML frontmatter (not JSON)
    let fileContent = '---\n';
    fileContent += `type: ${bumpType}\n`;
    fileContent += `pr: ${prNumber}\n`;
    fileContent += `breaking: ${isBreaking}\n`;
    fileContent += '---\n\n';
    fileContent += `### ${summary}\n\n`;
    fileContent += `[PR #${prNumber}](https://github.com/wp-graphql/wp-graphql/pull/${prNumber})\n\n`;

    if (sections.description) {
        fileContent += `#### Description\n${sections.description}\n\n`;
    }

    if (sections.breaking) {
        fileContent += `#### Breaking Changes\n${sections.breaking}\n\n`;
    }

    if (sections.upgrade) {
        fileContent += `#### Upgrade Instructions\n${sections.upgrade}\n\n`;
    }

    if (sinceMetadata.sinceFiles.length > 0) {
        fileContent += `#### Files with @since next-version\n`;
        sinceMetadata.sinceFiles.forEach(file => {
            fileContent += `- ${file}\n`;
        });
    }

    // Write the changeset file
    fs.writeFileSync(changesetPath, fileContent);

    return {
        type: bumpType,
        breaking: isBreaking,
        pr: Number(prNumber),
        sinceFiles: sinceMetadata.sinceFiles,
        totalSinceTags: sinceMetadata.totalTags,
        changesetId
    };
}

// When run directly from command line
if (require.main === module) {
    const title = process.env.PR_TITLE;
    const body = process.env.PR_BODY;
    const prNumber = process.env.PR_NUMBER;

    if (!title || !body || !prNumber) {
        console.error('Missing required environment variables');
        process.exit(1);
    }

    createChangeset({ title, body, prNumber })
        .then(result => {
            console.log('Changeset created successfully:', result);
        })
        .catch(error => {
            console.error('Error creating changeset:', error);
            process.exit(1);
        });
}

module.exports = {
    parseTitle,
    parsePRBody,
    createChangeset,
    formatSummary,
    ALLOWED_TYPES
};