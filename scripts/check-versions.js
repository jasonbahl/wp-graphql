const fs = require('fs');
const path = require('path');

function getVersions() {
    const versions = {};

    // Check package.json
    const pkg = require('../package.json');
    versions.package = pkg.version;

    // Check wp-graphql.php
    const plugin = fs.readFileSync(path.join(__dirname, '../wp-graphql.php'), 'utf8');
    const pluginVersion = plugin.match(/Version:\s*([\d.]+)/)?.[1];
    versions.plugin = pluginVersion;

    // Check constants.php
    const constants = fs.readFileSync(path.join(__dirname, '../constants.php'), 'utf8');
    const constantVersion = constants.match(/WPGRAPHQL_VERSION',\s*'([\d.]+)'/)?.[1];
    versions.constant = constantVersion;

    // Check readme.txt
    const readme = fs.readFileSync(path.join(__dirname, '../readme.txt'), 'utf8');
    const stableTag = readme.match(/Stable tag:\s*([\d.]+)/)?.[1];
    versions.readme = stableTag;

    return versions;
}

function checkVersions() {
    const versions = getVersions();
    console.log('Current versions:', versions);

    const allVersions = Object.values(versions);
    const allMatch = allVersions.every(v => v === allVersions[0]);

    if (!allMatch) {
        console.error('Version mismatch detected!');
        process.exit(1);
    }

    console.log('All versions match:', allVersions[0]);
}

if (require.main === module) {
    checkVersions();
}

module.exports = { getVersions, checkVersions };