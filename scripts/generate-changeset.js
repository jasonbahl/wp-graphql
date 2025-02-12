#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

function getCurrentBranch() {
    return execSync('git rev-parse --abbrev-ref HEAD').toString().trim();
}

function getPRDetails() {
    // Get the last commit message to extract PR info
    const commitMessage = execSync('git log -1 --pretty=%B').toString().trim();

    // Extract PR number if it exists in the commit message
    const prMatch = commitMessage.match(/\(#(\d+)\)/) || commitMessage.match(/#(\d+)/);
    const prNumber = prMatch ? prMatch[1] : 'local';

    return {
        title: commitMessage.split('\n')[0],
        number: prNumber,
        url: `https://github.com/wp-graphql/wp-graphql/pull/${prNumber}`,
        body: commitMessage,
        branch: getCurrentBranch()
    };
}

function findSinceTodoFiles() {
    // Get changed files in the current branch
    const changedFiles = execSync('git diff --name-only HEAD^').toString().trim().split('\n');

    return changedFiles
        .filter(file => {
            if (!fs.existsSync(file)) return false;
            const content = fs.readFileSync(file, 'utf8');
            return content.includes('@since todo');
        })
        .map(file => `- ${file}`);
}

function generateChangeset() {
    const pr = getPRDetails();

    // Determine change type from commit message
    let changeType = 'patch';
    let isBreaking = false;

    if (pr.title.startsWith('BREAKING')) {
        changeType = 'major';
        isBreaking = true;
    } else if (pr.title.startsWith('feat:')) {
        changeType = 'minor';
    }

    // Check if it's a beta based on branch
    const isBeta = pr.branch === 'next-major';

    // Create changeset directory if it doesn't exist
    const changesetDir = path.join(process.cwd(), '.changesets');
    if (!fs.existsSync(changesetDir)) {
        fs.mkdirSync(changesetDir);
    }

    // Generate changeset content
    const changesetContent = `---
type: ${changeType}
pr: ${pr.number}
beta: ${isBeta}
breaking: ${isBreaking}
---

### ${pr.title}

[PR #${pr.number}](${pr.url})

#### Description
${pr.body}
`;

    // Add @since todo files if any exist
    const sinceTodoFiles = findSinceTodoFiles();
    const sinceTodoContent = sinceTodoFiles.length
        ? `\n#### Files with @since todo\n${sinceTodoFiles.join('\n')}`
        : '';

    // Write changeset file
    const timestamp = Math.floor(Date.now() / 1000);
    const changesetFile = path.join(changesetDir, `${timestamp}-${pr.number}.md`);

    fs.writeFileSync(
        changesetFile,
        changesetContent + sinceTodoContent
    );

    console.log(`Generated changeset: ${changesetFile}`);
}

generateChangeset();