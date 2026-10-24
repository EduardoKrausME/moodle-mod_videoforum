# Changelog

This changelog documents only the two most recent published GitHub releases. Each entry contains changes verified against its immediately preceding published release. Changes made later on `master` are excluded.

## 1.0.2 - 2026-10-15

### Changed

- Updated the GitHub Actions CI PostgreSQL service from version 16 to 17.
- Updated the MariaDB CI service from version 10 to 11.4, adjusting startup environment variables and replacing the health-check command with `healthcheck.sh --connect --innodb_initialized`.
- Added the plugin icon at `pix/icon.png` and reorganized the SVG logo asset as `pix/monologo.svg`.
- Updated the plugin release identifier from `1.0.1` to `1.0.2` and its build number from `2026101200` to `2026101500`.

No changes to Video Forum activity functionality were identified in this tag comparison.

[Compare 1.0.1...1.0.2](https://github.com/EduardoKrausME/moodle-mod_videoforum/compare/1.0.1...1.0.2)

## 1.0.1 - 2026-10-12

### Fixed

- Hardened the release workflow against shell injection by reading the head commit message from the `RELEASE_MESSAGE` environment variable instead of interpolating it directly into shell commands.

### Changed

- Updated the PHP 8.3 CI matrix from Moodle 5.2 (`MOODLE_502_STABLE`) to Moodle 5.3 (`MOODLE_503_STABLE`), retaining the Moodle 4.5 test entry.
- Applied minor formatting changes to the release workflow file, including its push trigger and trailing newline.
- Updated the plugin release identifier from `1.0.0` to `1.0.1` and its build number from `2026100601` to `2026101200`.

No changes to Video Forum activity functionality were identified in this tag comparison.

[Compare 1.0.0...1.0.1](https://github.com/EduardoKrausME/moodle-mod_videoforum/compare/1.0.0...1.0.1)
