# Beta Releases

WPGraphQL uses beta releases to test major changes and breaking features before they are released to the public.

## Beta Release Process

### For Contributors
- Breaking changes and major features should target the `next-major` branch
- PRs should follow the standard process but be directed to `next-major`
- Breaking changes should be clearly documented in the PR description

### For Maintainers

#### Starting a Beta Cycle
```bash
# Switch to next-major branch
git checkout next-major

# Enter pre-release mode
npm run changeset pre enter beta

# Version and create first beta
npm run version-packages
git push --follow-tags
```

#### Creating Additional Beta Releases
```bash
# Make sure you're on next-major
git checkout next-major

# Version and create next beta
npm run version-packages
git push --follow-tags
```

#### Promoting to Stable
```bash
# Exit pre-release mode
npm run changeset pre exit

# Version and create stable release
npm run version-packages
git push --follow-tags
```

## Version Numbering
- Beta releases: `v2.0.0-beta.1`, `v2.0.0-beta.2`, etc.
- Alpha releases: `v2.0.0-alpha.1` (if needed)
- Final release: `v2.0.0`

## Notes
- Beta releases are not deployed to WordPress.org
- The stable tag in readme.txt is not updated for beta releases
- Beta releases are marked as pre-releases on GitHub
- Breaking changes should include upgrade notes