const { parseTitle, parsePRBody, createChangeset, formatSummary, ALLOWED_TYPES } = require('../generate-changeset');
const { generateSinceTagsMetadata } = require('../scan-since-tags');
const fs = require('fs');
const path = require('path');

jest.mock('../scan-since-tags', () => ({
    generateSinceTagsMetadata: jest.fn()
}));

// Mock fs functions
jest.mock('fs', () => ({
    existsSync: jest.fn(),
    writeFileSync: jest.fn(),
    mkdirSync: jest.fn(),
    readFileSync: jest.fn().mockImplementation(() => JSON.stringify({ name: 'wp-graphql' }))
}));

// Mock path.join to avoid file system issues
jest.mock('path', () => ({
    join: jest.fn().mockImplementation((...args) => args.join('/'))
}));

// Mock the fetch function for GitHub API calls
global.fetch = jest.fn();

// Mock the contributor detection functions
jest.mock('../generate-changeset', () => {
  const originalModule = jest.requireActual('../generate-changeset');
  return {
    ...originalModule,
    isNewContributor: jest.fn().mockResolvedValue(true),
    getPRData: jest.fn().mockResolvedValue({
      user: {
        login: 'testuser'
      }
    }),
    extractGitHubUsername: jest.fn().mockReturnValue('testuser')
  };
});

describe('Changeset Generation', () => {
    beforeEach(() => {
        jest.clearAllMocks();
        generateSinceTagsMetadata.mockResolvedValue({
            sinceFiles: ['test.php'],
            totalTags: 1
        });
        fs.existsSync.mockReturnValue(true);
        fs.writeFileSync.mockImplementation(() => {});
        fs.mkdirSync.mockImplementation(() => {});
    });

    describe('parseTitle', () => {
        test('parses basic title format', () => {
            const result = parseTitle('feat: Add new feature');
            expect(result).toEqual({
                type: 'feat',
                isBreaking: false
            });
        });

        test('parses title with scope', () => {
            const result = parseTitle('feat(core): Add new feature');
            expect(result).toEqual({
                type: 'feat',
                isBreaking: false
            });
        });

        test('detects breaking change with !', () => {
            const result = parseTitle('feat!: Breaking feature');
            expect(result).toEqual({
                type: 'feat',
                isBreaking: true
            });
        });

        test('detects breaking change with BREAKING CHANGE', () => {
            const result = parseTitle('feat: BREAKING CHANGE - New feature');
            expect(result).toEqual({
                type: 'feat',
                isBreaking: true
            });
        });

        test('throws on invalid type', () => {
            expect(() => parseTitle('invalid: Some change')).toThrow('PR title does not follow conventional commit format');
        });

        test('validates allowed types', () => {
            ALLOWED_TYPES.forEach(type => {
                const result = parseTitle(`${type}: Some change`);
                expect(result.type).toBe(type);
            });
        });
    });

    describe('parsePRBody', () => {
        test('extracts all sections with ### headings', () => {
            const body = `
                What does this implement/fix? Explain your changes.
                ---
                This is a description

                ### Breaking Changes
                This breaks something

                ### Upgrade Instructions
                Follow these steps
            `;

            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'This is a description',
                breaking: 'This breaks something',
                upgrade: 'Follow these steps'
            });
        });

        test('extracts all sections with ## headings', () => {
            const body = `
                What does this implement/fix? Explain your changes.
                ---
                This is a description

                ## Breaking Changes
                This breaks something

                ## Upgrade Instructions
                Follow these steps
            `;

            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'This is a description',
                breaking: 'This breaks something',
                upgrade: 'Follow these steps'
            });
        });

        test('handles mixed heading levels', () => {
            const body = `
                What does this implement/fix? Explain your changes.
                ---
                This is a description

                ## Breaking Changes
                This breaks something

                ### Upgrade Instructions
                Follow these steps
            `;

            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'This is a description',
                breaking: 'This breaks something',
                upgrade: 'Follow these steps'
            });
        });

        test('handles missing sections', () => {
            const body = 'What does this implement/fix? Explain your changes.\n---\nJust a description';
            const result = parsePRBody(body);
            expect(result).toEqual({
                description: 'Just a description',
                breaking: '',
                upgrade: ''
            });
        });

        test('cleans up N/A placeholders', () => {
            const body = `
                What does this implement/fix? Explain your changes.
                ---
                Description

                ## Breaking Changes
                N/A

                ## Upgrade Instructions
                none
            `;

            const result = parsePRBody(body);
            expect(result.breaking).toBe('');
            expect(result.upgrade).toBe('');
        });
    });

    describe('createChangeset', () => {
        const validPR = {
            title: 'feat: New feature',
            body: `
                What does this implement/fix? Explain your changes.
                ---
                Adds a new feature

                ### Breaking Changes

                ### Upgrade Instructions
            `,
            prNumber: '123'
        };

        test('creates basic changeset', async () => {
            const result = await createChangeset(validPR);
            expect(result).toEqual({
                type: 'minor',
                breaking: false,
                pr: 123,
                sinceFiles: ['test.php'],
                totalSinceTags: 1,
                changesetId: expect.stringContaining('pr-123-')
            });
            expect(fs.writeFileSync).toHaveBeenCalled();
        });

        test('handles breaking changes', async () => {
            const breakingPR = {
                title: 'feat!: Breaking feature',
                body: `
                    What does this implement/fix? Explain your changes.
                    ---
                    Breaking feature description

                    ### Breaking Changes
                    This breaks something

                    ### Upgrade Instructions
                    Follow these steps
                `,
                prNumber: '123'
            };

            const result = await createChangeset(breakingPR);
            expect(result.type).toBe('major');
            expect(result.breaking).toBe(true);

            // Verify changeset content
            const writeCall = fs.writeFileSync.mock.calls[0][1];
            expect(writeCall).toContain('#### Breaking Changes');
            expect(writeCall).toContain('#### Upgrade Instructions');
        });

        test('requires upgrade instructions for breaking changes', async () => {
            const breakingPR = {
                title: 'feat!: Breaking feature',
                body: `
                    What does this implement/fix? Explain your changes.
                    ---
                    Breaking feature

                    ### Breaking Changes
                    This breaks something
                `,
                prNumber: '123'
            };

            await expect(createChangeset(breakingPR)).rejects.toThrow('Breaking changes must include upgrade instructions');
        });

        test('includes @since tags metadata', async () => {
            const result = await createChangeset(validPR);
            expect(result.sinceFiles).toEqual(['test.php']);
            expect(result.totalSinceTags).toBe(1);

            const writeCall = fs.writeFileSync.mock.calls[0][1];
            expect(writeCall).toContain('#### Files with @since next-version');
            expect(writeCall).toContain('- test.php');
        });

        test('creates correct changeset file structure', async () => {
            // Mock fs.writeFileSync
            fs.writeFileSync = jest.fn().mockImplementation((path, content) => {
                // Store the content for assertions
                fs.writeFileSync.mockContent = content;
            });
            fs.existsSync = jest.fn().mockReturnValue(false);
            fs.mkdirSync = jest.fn();

            // Mock generateSinceTagsMetadata
            const mockSinceMetadata = {
                sinceFiles: [],
                totalTags: 0
            };
            generateSinceTagsMetadata.mockResolvedValue(mockSinceMetadata);

            // Call createChangeset
            await createChangeset({
                title: 'feat: Test feature',
                body: 'What does this implement/fix? Explain your changes.\n---\nThis is a test feature',
                prNumber: '123'
            });

            // Verify fs.writeFileSync was called
            expect(fs.writeFileSync).toHaveBeenCalled();
            const content = fs.writeFileSync.mockContent;

            // Check that the content contains the expected parts
            expect(content).toContain('"wp-graphql": minor');
            expect(content).toContain('pr: 123');
            expect(content).toContain('breaking: false');
            expect(content).toContain('### feat: This is a test feature');
            expect(content).toContain('[PR #123](https://github.com/wp-graphql/wp-graphql/pull/123)');
        });

        test('creates changeset with breaking change from title', async () => {
            // Mock fs.writeFileSync
            fs.writeFileSync = jest.fn().mockImplementation((path, content) => {
                // Store the content for assertions
                fs.writeFileSync.mockContent = content;
            });
            fs.existsSync = jest.fn().mockReturnValue(false);
            fs.mkdirSync = jest.fn();

            // Mock generateSinceTagsMetadata
            const mockSinceMetadata = {
                sinceFiles: [],
                totalTags: 0
            };
            generateSinceTagsMetadata.mockResolvedValue(mockSinceMetadata);

            // Call createChangeset with breaking change in title
            await createChangeset({
                title: 'feat!: Breaking change',
                body: 'What does this implement/fix? Explain your changes.\n---\nThis is a breaking change\n\n## Breaking Changes\nThis breaks something\n\n## Upgrade Instructions\nFollow these steps',
                prNumber: '123'
            });

            // Verify fs.writeFileSync was called
            expect(fs.writeFileSync).toHaveBeenCalled();
            const content = fs.writeFileSync.mockContent;

            // Check that the content contains the expected parts
            expect(content).toContain('"wp-graphql": major');
            expect(content).toContain('breaking: true');
            expect(content).toContain('#### Breaking Changes');
            expect(content).toContain('#### Upgrade Instructions');
        });

        test('creates changeset with breaking change from PR body', async () => {
            // Mock fs.writeFileSync
            fs.writeFileSync = jest.fn().mockImplementation((path, content) => {
                // Store the content for assertions
                fs.writeFileSync.mockContent = content;
            });
            fs.existsSync = jest.fn().mockReturnValue(false);
            fs.mkdirSync = jest.fn();

            // Mock generateSinceTagsMetadata
            const mockSinceMetadata = {
                sinceFiles: [],
                totalTags: 0
            };
            generateSinceTagsMetadata.mockResolvedValue(mockSinceMetadata);

            // Call createChangeset with breaking change in body
            await createChangeset({
                title: 'feat: Feature with breaking change',
                body: 'What does this implement/fix? Explain your changes.\n---\nThis is a feature with breaking change\n\n## Breaking Changes\nThis breaks something\n\n## Upgrade Instructions\nFollow these steps',
                prNumber: '123'
            });

            // Verify fs.writeFileSync was called
            expect(fs.writeFileSync).toHaveBeenCalled();
            const content = fs.writeFileSync.mockContent;

            // Check that the content contains the expected parts
            expect(content).toContain('"wp-graphql": major');
            expect(content).toContain('#### Breaking Changes');
            expect(content).toContain('This breaks something');
        });

        test('creates changeset with breaking change from both title and body', async () => {
            // Mock fs.writeFileSync
            fs.writeFileSync = jest.fn().mockImplementation((path, content) => {
                // Store the content for assertions
                fs.writeFileSync.mockContent = content;
            });
            fs.existsSync = jest.fn().mockReturnValue(false);
            fs.mkdirSync = jest.fn();

            // Mock generateSinceTagsMetadata
            const mockSinceMetadata = {
                sinceFiles: [],
                totalTags: 0
            };
            generateSinceTagsMetadata.mockResolvedValue(mockSinceMetadata);

            // Call createChangeset with breaking change in both title and body
            await createChangeset({
                title: 'feat!: Breaking change',
                body: 'What does this implement/fix? Explain your changes.\n---\nThis is a breaking change\n\n## Breaking Changes\nThis also breaks something\n\n## Upgrade Instructions\nFollow these steps',
                prNumber: '123'
            });

            // Verify fs.writeFileSync was called
            expect(fs.writeFileSync).toHaveBeenCalled();
            const content = fs.writeFileSync.mockContent;

            // Check that the content contains the expected parts
            expect(content).toContain('"wp-graphql": major');
            expect(content).toContain('breaking: true');
            expect(content).toContain('#### Breaking Changes');
            expect(content).toContain('This also breaks something');
        });

        test('createChangeset generates correct file content', async () => {
            // Mock fs.writeFileSync
            fs.writeFileSync = jest.fn().mockImplementation((path, content) => {
                // Store the content for assertions
                fs.writeFileSync.mockContent = content;
            });
            fs.existsSync = jest.fn().mockReturnValue(false);
            fs.mkdirSync = jest.fn();

            // Mock generateSinceTagsMetadata
            const mockSinceMetadata = {
                sinceFiles: ['test.php'],
                totalTags: 1
            };
            generateSinceTagsMetadata.mockResolvedValue(mockSinceMetadata);

            // Call createChangeset
            await createChangeset({
                title: 'feat: Test feature',
                body: 'What does this implement/fix? Explain your changes.\n---\nThis is a test feature',
                prNumber: '123'
            });

            // Get the file content that would be written
            const content = fs.writeFileSync.mockContent;

            // Check that the content contains the expected parts
            expect(content).toContain('"wp-graphql": minor');
            expect(content).toContain('pr: 123');
            expect(content).toContain('breaking: false');
            expect(content).toContain('### feat: This is a test feature');
            expect(content).toContain('[PR #123](https://github.com/wp-graphql/wp-graphql/pull/123)');
        });
    });

    describe('formatSummary', () => {
        test('formats regular summary', () => {
            expect(formatSummary('feat', false, 'New feature')).toBe('feat: New feature');
        });

        test('formats breaking change summary', () => {
            expect(formatSummary('feat', true, 'Breaking feature')).toBe('feat!: Breaking feature');
        });

        test('trims description', () => {
            expect(formatSummary('fix', false, '  Fix bug  ')).toBe('fix: Fix bug');
        });
    });
});

describe('Contributor detection', () => {
  const { isNewContributor, getPRData, extractGitHubUsername } = require('../generate-changeset');

  beforeEach(() => {
    jest.clearAllMocks();
    // Setup fetch mock for GitHub API
    global.fetch.mockImplementation(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ total_count: 0 }) // Simulate first-time contributor
      })
    );
  });

  test('extractGitHubUsername extracts username from PR data', () => {
    const prData = {
      user: {
        login: 'testuser'
      },
      html_url: 'https://github.com/testuser/repo/pull/123'
    };

    expect(extractGitHubUsername(prData)).toBe('testuser');
  });

  test('isNewContributor correctly identifies new contributors', async () => {
    // Setup
    global.fetch.mockImplementationOnce(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ total_count: 0 }) // No previous PRs
      })
    );

    const result = await isNewContributor('newuser', 123);

    // Verify
    expect(result).toBe(true);
    expect(global.fetch).toHaveBeenCalledWith(
      expect.stringContaining('author:newuser'),
      expect.any(Object)
    );
  });

  test('isNewContributor correctly identifies returning contributors', async () => {
    // Setup
    global.fetch.mockImplementationOnce(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ total_count: 5 }) // Has previous PRs
      })
    );

    const result = await isNewContributor('returninguser', 123);

    // Verify
    expect(result).toBe(false);
  });

  test('getPRData fetches PR information correctly', async () => {
    // Setup
    global.fetch.mockImplementationOnce(() =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve({
          user: { login: 'testuser' },
          html_url: 'https://github.com/wp-graphql/wp-graphql/pull/123'
        })
      })
    );

    const result = await getPRData(123);

    // Verify
    expect(result).toHaveProperty('user.login', 'testuser');
    expect(global.fetch).toHaveBeenCalledWith(
      'https://api.github.com/repos/wp-graphql/wp-graphql/pulls/123',
      expect.any(Object)
    );
  });

  test('createChangeset includes contributor information in changeset', async () => {
    // Setup
    const { createChangeset } = require('../generate-changeset');
    const fs = require('fs');
    jest.spyOn(fs, 'writeFileSync').mockImplementation();

    // Mock the contributor detection functions
    isNewContributor.mockResolvedValueOnce(true);
    getPRData.mockResolvedValueOnce({
      user: { login: 'newcontributor' }
    });
    extractGitHubUsername.mockReturnValueOnce('newcontributor');

    // Execute
    await createChangeset({
      title: 'feat: New feature',
      body: 'Description of the feature',
      prNumber: '123'
    });

    // Verify
    expect(fs.writeFileSync).toHaveBeenCalledWith(
      expect.any(String),
      expect.stringContaining('contributorUsername: "newcontributor"')
    );
    expect(fs.writeFileSync).toHaveBeenCalledWith(
      expect.any(String),
      expect.stringContaining('newContributor: true')
    );
  });
});