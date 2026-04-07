---
name: bump-version
description: Increase version number. Use this skill to update the version number before releasing a new version of the Smartling Connector. Usually, a release happens after a new feature is added.
---

1. Examine changes in the current git branch compared to master.
2. Decide if changes are minor, major or patch.
3. Update the smartling-connector.php comment that starts with `Version:` with the new version number.
4. Update the composer.json version with the new version number.
5. Update the readme.txt `Stable tag:` with the new version number and add the release notes.
