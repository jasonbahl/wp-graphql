const fs = require('fs');
const path = require('path');

function formatUpgradeNotes(version, notes) {
    if (!notes || !notes.length) return '';

    return `= ${version} =

### Upgrade Notice

${notes.join('\n\n')}
`;
}

function formatChangelog(version, changes, isBeta) {
    return `= ${version} ${isBeta ? '(Beta)' : ''} =

${changes.map(change => `* ${change}`).join('\n')}
`;
}

function updateReadme(version, changes, upgradeNotes = [], isBeta = false) {
    const readmePath = path.join(process.cwd(), 'readme.txt');
    let readme = fs.readFileSync(readmePath, 'utf8');

    // Add upgrade notice if provided
    if (upgradeNotes.length) {
        const upgradeSection = formatUpgradeNotes(version, upgradeNotes);
        readme = readme.replace(
            /(== Upgrade Notice ==\n\n)/,
            `$1${upgradeSection}\n\n`
        );
    }

    // Add changelog entry
    const changelogEntry = formatChangelog(version, changes, isBeta);
    readme = readme.replace(
        /(== Changelog ==\n\n)/,
        `$1${changelogEntry}\n\n`
    );

    fs.writeFileSync(readmePath, readme);
}

module.exports = { updateReadme };