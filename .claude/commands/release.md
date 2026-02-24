Commit all changes, push, and create a new release if the WordPress plugin has changed.

Steps:

1. Run `git status` and `git diff --stat` to see what changed.

2. If there are no changes, tell me and stop.

3. Stage all changed files and create a commit with a descriptive message summarizing the changes.

4. Push to origin.

5. Check if any files under `wordpress/` were changed in this commit (use `git diff --name-only HEAD~1` to check). If NOT, stop here — no release needed.

6. If WordPress plugin files changed:
   a. Read the current version from the `Version:` header in `wordpress/instagram-scraper.php` and from the `INSTAGRAM_SCRAPER_VERSION` constant in the same file.
   b. Bump the patch version (e.g. 2.0.0 → 2.0.1). Update BOTH the `Version:` plugin header AND the `INSTAGRAM_SCRAPER_VERSION` constant in `wordpress/instagram-scraper.php`.
   c. Commit the version bump with message "Bump version to X.Y.Z".
   d. Push.
   e. Create a git tag `vX.Y.Z` and push it (`git push origin vX.Y.Z`). This triggers the GitHub Actions workflow that builds and attaches `instagram-scraper.zip` to the release.
   f. Tell me the new version and that the release will be created automatically by GitHub Actions.
