# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.1] - 2026-03-18

### Fixed
- Remove max-width cap on content sections so they fill the full viewport
- Fix apps table using `max-content` width instead of filling available space

## [1.3.0] - 2026-03-04

### Added
- Detailed health check checklist with visual indicators and per-check status
- Fix hints in health check results to guide users on resolving issues
- Self-update prevention: blocks uploading AppDrop through itself

### Fixed
- Always register navigation entry to prevent Nextcloud 32 crash
- Icon color for light theme compatibility
- Hide navigation from users without upload permission
- Add `@NoAdminRequired` to controllers to allow permitted non-admin users
- Strengthen warning/error colors for better contrast in light theme

## [1.2.0] - 2026-03-04

### Added
- Nextcloud-standard sidebar navigation (replaces horizontal tabs)
- Permission system: admin can grant upload access to specific users and groups
- Permissions management UI with user/group search autocomplete
- PermissionService for access control via IConfig app values
- PermissionController with CRUD and search API endpoints

### Changed
- UI redesigned to follow Nextcloud's `#app-navigation` / `#app-content` layout pattern
- Admin-only sections (Apps, Backups, Permissions) hidden from non-admin users via PHP
- Upload, History, Generator sections accessible to permitted users (not just admins)
- Generator section improved: renamed to "App Skeleton Generator", added file structure preview
- All upload-related controllers now use `canUpload()` instead of `denyIfNotAdmin()`
- Bumped version to 1.2.0

### Removed
- Horizontal tab navigation (tabs.php, tabs.js)
- Legacy admin.js upload handler

## [1.1.0] - 2026-03-04

### Added
- Tab-based UI with 5 sections: Upload, Apps, History, Generator, Backups
- Drag & drop upload zone for .zip files
- Multi-file upload with sequential processing and per-file status
- Pre-install health check validation (errors and warnings)
- App Manager: list, enable, disable, remove custom apps
- Backup Management: view, restore, delete backups created during updates
- Template Generator: generate and download Nextcloud app skeleton .zip
- Upload History with database persistence and paginated view
- Dark mode support via Nextcloud CSS variables
- Responsive design for mobile screens

### Changed
- Extracted `AppPathResolver` as shared utility service
- Extracted `AdminAuthTrait` for consistent admin checks across controllers
- Expanded CSS with tables, badges, dropzone, and responsive styles
- Bumped version to 1.1.0

## [1.0.0] - 2026-03-04

### Added
- Upload and install Nextcloud app packages (.zip) via web UI
- Automatic validation of uploaded packages (Zip Slip protection, MIME type, file size, app ID format)
- Automatic backup of existing app before updates
- Admin-only access enforcement
- Settings panel integration in Nextcloud admin settings
- Client-side file validation (extension, size)
- Inline success/error messaging
- Support for top-level wrapper directory detection in zip files
