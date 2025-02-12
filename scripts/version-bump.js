const { updateSinceTags } = require('./update-since-tags');
const { updateVersions } = require('./update-versions');
const { updateReadme } = require('./update-readme');

async function bumpVersion(newVersion, changes, upgradeNotes) {
    const isBeta = newVersion.includes('beta') || newVersion.includes('alpha');

    // Update @since todo tags
    const updatedFiles = updateSinceTags(newVersion);
    console.log('Updated @since tags in:', updatedFiles);

    // Update version numbers
    updateVersions(newVersion);
    console.log('Updated version numbers to:', newVersion);

    // Update readme.txt
    updateReadme(newVersion, changes, upgradeNotes, isBeta);
    console.log('Updated readme.txt');
}

module.exports = { bumpVersion };