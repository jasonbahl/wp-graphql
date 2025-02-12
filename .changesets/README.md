# Changesets

This directory contains "changesets" which help us manage versioning and changelogs.

## What is a Changeset?

A changeset is a file that describes changes made in a PR. It includes:

- Type of change (patch/minor/major)
- PR number and link
- Whether it's a beta release
- Whether it contains breaking changes
- Description of changes
- Upgrade notes (if any)
- Files containing `@since todo` that need updating

## Example Changeset

```md
---
type: minor
pr: 123
beta: false
breaking: false
---

### feat: Add new GraphQL field to Post type

[PR #123](https://github.com/wp-graphql/wp-graphql/pull/123)

#### Description
Adds a new GraphQL field `customField` to the Post type that exposes custom meta data.

#### Upgrade Notes
Users implementing the PostType interface will need to implement this new field.

#### Files with @since todo
- src/Type/ObjectType/PostType.php
```

## How are Changesets Generated?

Changesets are automatically generated when PRs are merged. The changeset content is derived from:

1. PR title (for change type and description)
2. PR metadata (number, URL)
3. "Upgrade Notes" section in PR description
4. Files changed that contain `@since todo`

## How are Changesets Used?

When a release is created:
1. All changesets are collected
2. Version bump is determined (patch/minor/major)
3. Changelog is generated
4. Version numbers are updated across files
5. `@since todo` tags are replaced with the new version
6. Stable tag is updated (for non-beta releases)