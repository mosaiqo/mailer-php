# Publishing & distribution

This package lives inside the Mailer monorepo at `sdk/php/`, but it is consumed
as a **standalone Composer package**. This document describes how to split it
out into its own Git repository and how downstream apps require it.

> The standalone repository is `git@github.com:mosaiqo/mailer-php.git`
> (web: <https://github.com/mosaiqo/mailer-php>), used throughout the commands
> below.

## Why a subtree split?

Composer reads the package metadata from `composer.json` **at the repository
root**. In the monorepo that file lives at `sdk/php/composer.json`, so the
monorepo itself is not directly installable. A `git subtree split` extracts the
`sdk/php/` directory (with its own history) into a branch whose root **is**
`sdk/php/`, putting `composer.json` exactly where Composer expects it. Pushing
that branch to a dedicated repo gives you a clean, installable, taggable
package — the `.gitattributes` `export-ignore` rules then keep the dist tarball
lean (no `tests/`, no `phpunit.xml.dist`, ...).

## 1. Split the subtree from the monorepo

Run from the monorepo root:

```bash
# Create a branch whose root is sdk/php/ (composer.json lands at the top).
git subtree split --prefix=sdk/php -b sdk-php-split

# Push that branch as `main` on the standalone repo.
git push git@github.com:mosaiqo/mailer-php.git sdk-php-split:main
```

`--prefix=sdk/php` is the load-bearing flag: it is what makes
`sdk/php/composer.json` become the repository-root `composer.json` on the split
branch.

### One-liner alternative

`git subtree push` does the split and push in one step (it maintains an
internal split cache, so re-runs are incremental):

```bash
git subtree push --prefix=sdk/php git@github.com:mosaiqo/mailer-php.git main
```

### Full-history rewrite alternative

`git subtree split` preserves the relevant commits but keeps monorepo commit
hashes. If you want a repository whose history contains **only** `sdk/php/`
paths (smaller, rewritten hashes), use
[`git-filter-repo`](https://github.com/newren/git-filter-repo):

```bash
git clone <monorepo> mailer-php && cd mailer-php
git filter-repo --subdirectory-filter sdk/php
git remote add origin git@github.com:mosaiqo/mailer-php.git
git push -u origin main
```

This rewrites history, so only do it on a fresh clone dedicated to the split.

## 2. Versioning & tags (SemVer)

Composer resolves versions from **Git tags on the standalone repo** — never
from the monorepo. After pushing the split branch, tag a release there:

```bash
# On the standalone repo (or via a fresh clone of it):
git tag v1.0.0
git push origin v1.0.0
```

Follow [SemVer](https://semver.org/):

- **MAJOR** (`v2.0.0`) — a breaking change to the public SDK API (renamed /
  removed public methods, changed signatures or DTO shapes, dropped PHP / Laravel
  versions).
- **MINOR** (`v1.1.0`) — backward-compatible features (new resources, new
  optional arguments).
- **PATCH** (`v1.0.1`) — backward-compatible bug fixes.

### Cutting a new release after monorepo changes

1. Land the SDK changes in the monorepo under `sdk/php/`.
2. Update `CHANGELOG.md` (move items from `[Unreleased]` into a versioned
   section).
3. Re-run the split + push (step 1) to update the standalone repo's `main`.
4. Tag the new version on the standalone repo and push the tag (step 2).

## 3. Consuming the package (private Composer VCS repo)

While the package is private (not on Packagist), the consumer app declares the
standalone repo as a Composer **VCS repository** in its own `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "git@github.com:mosaiqo/mailer-php.git" }
]
```

Then require it by version constraint:

```bash
composer require mosaiqo/mailer-php:^1.0
```

Notes:

- **Auth for private repos.** The machine running Composer needs Git read
  access to the standalone repo — typically an SSH deploy key (the `git@...`
  URL) or an HTTPS token configured via `composer config`
  (`github-oauth` / `gitlab-token` / `bitbucket-oauth`) or `auth.json`.
- **Before the first tag.** You can require the untagged default branch with a
  `dev-` constraint:

  ```bash
  composer require "mosaiqo/mailer-php:dev-main"
  ```

  Switch to `^1.0` once `v1.0.0` is tagged.

## Automated distribution

The manual split above is mirrored by CI. Pushes to the `main` branch that
touch `sdk/php/**` automatically sync the `main` branch of
`mosaiqo/mailer-php` via a `git subtree split` GitHub Actions workflow — no
manual split/push needed for routine SDK changes.

To cut a SemVer release, run the GitHub Actions workflow named
**"Split SDK to mosaiqo/mailer-php"** with `workflow_dispatch` and provide the
`tag` input (e.g. `v1.1.0`); the workflow tags the split branch on
`mosaiqo/mailer-php` and pushes the tag.

The workflow requires the `MAILER_PHP_DEPLOY_KEY` repository secret — an SSH
deploy key with write access to `mosaiqo/mailer-php`.
