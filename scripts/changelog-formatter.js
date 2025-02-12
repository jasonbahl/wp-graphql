const { getInfo } = require('@changesets/get-github-info');

async function getReleaseLine(changeset, type, options) {
    const [firstLine, ...futureLines] = changeset.summary
        .split('\n')
        .map(l => l.trimRight());

    const { links } = await getInfo({
        repo: options.repo,
        commit: changeset.commit
    });

    return {
        summary: `- ${links.commit}${links.pull === null ? '' : ` ${links.pull}`} ${firstLine}`,
        pullRequest: links.pull,
        upgradeNotes: changeset.upgradeNotes,
        sinceTodoFiles: changeset.sinceTodoFiles
    };
}

async function getDependencyReleaseLine() {
    return '';
}

async function getFixedInitializer() {
    return ``;
}

module.exports = {
    getReleaseLine,
    getDependencyReleaseLine,
    getFixedInitializer
};