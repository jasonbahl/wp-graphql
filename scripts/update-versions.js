const fs = require('fs');
const path = require('path');

function updateVersions(newVersion) {
    // Update constants.php
    const constantsPath = path.join(process.cwd(), 'constants.php');
    let constants = fs.readFileSync(constantsPath, 'utf8');
    constants = constants.replace(
        /(define\(\s*'WPGRAPHQL_VERSION',\s*')[^']+('\s*\);)/,
        `$1${newVersion}$2`
    );
    fs.writeFileSync(constantsPath, constants);

    // Update wp-graphql.php
    const pluginPath = path.join(process.cwd(), 'wp-graphql.php');
    let plugin = fs.readFileSync(pluginPath, 'utf8');
    plugin = plugin
        .replace(/(Version:\s*)[\d.]+/g, `$1${newVersion}`)
        .replace(/(@version\s*)[\d.]+/g, `$1${newVersion}`);
    fs.writeFileSync(pluginPath, plugin);

    // Update readme.txt
    const readmePath = path.join(process.cwd(), 'readme.txt');
    let readme = fs.readFileSync(readmePath, 'utf8');

    // Only update stable tag if not a beta version
    if (!newVersion.includes('beta')) {
        readme = readme.replace(
            /(Stable tag:\s*)[\d.]+/,
            `$1${newVersion}`
        );
    }
    fs.writeFileSync(readmePath, readme);
}

module.exports = { updateVersions };