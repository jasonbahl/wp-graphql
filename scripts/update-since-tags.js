const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

function findSinceTodoFiles(dir = 'src') {
    const files = [];

    function scanDir(currentDir) {
        const entries = fs.readdirSync(currentDir, { withFileTypes: true });

        for (const entry of entries) {
            const fullPath = path.join(currentDir, entry.name);

            if (entry.isDirectory()) {
                scanDir(fullPath);
            } else if (entry.isFile() && entry.name.endsWith('.php')) {
                const content = fs.readFileSync(fullPath, 'utf8');
                if (content.includes('@since todo')) {
                    files.push(fullPath);
                }
            }
        }
    }

    scanDir(dir);
    return files;
}

function updateSinceTags(version) {
    const files = findSinceTodoFiles();

    files.forEach(file => {
        let content = fs.readFileSync(file, 'utf8');
        content = content.replace(/@since todo/g, `@since ${version}`);
        fs.writeFileSync(file, content);
    });

    return files;
}

module.exports = { updateSinceTags };