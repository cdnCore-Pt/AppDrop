# AppDrop

Upload, validate and install Nextcloud app packages (.zip) directly from the web UI — no SSH required.

## Features

- **Drag & drop upload** — Upload one or multiple .zip app packages with drag & drop or file picker
- **Pre-install validation** — Health check analyzes the zip before installing (Zip Slip protection, MIME check, info.xml verification, PHP/NC version compatibility)
- **Automatic backups** — Existing apps are backed up before updates, with restore and delete options
- **App Manager** — List, enable, disable and remove installed custom apps
- **Template Generator** — Generate and download a Nextcloud app skeleton .zip ready to develop
- **Upload History** — Database-backed log of all uploads with pagination
- **Permission system** — Admins can grant upload access to specific users and groups
- **Dark mode** — Full support via Nextcloud CSS variables

## Requirements

- Nextcloud 30 – 32
- PHP 8.1+

## Installation

Copy the `appdrop` directory into your Nextcloud `custom_apps/` folder and enable it:

```bash
php occ app:enable appdrop
```

Or upload it to itself via an already-running AppDrop instance.

## Usage

Once enabled, **AppDrop** appears in the top navigation bar for users with permission (admins always have access).

The UI has the following sections:

| Section | Access | Description |
|---|---|---|
| **Upload** | Permitted users | Drag & drop .zip packages to install or update apps |
| **Apps** | Admin only | Manage installed custom apps (enable/disable/remove) |
| **History** | Permitted users | View past uploads with status and timestamps |
| **Generator** | Permitted users | Generate a Nextcloud app skeleton .zip |
| **Backups** | Admin only | View, restore or delete backups created during updates |
| **Permissions** | Admin only | Grant/revoke upload access to users and groups |

Settings (max upload size) are available in **Administration Settings → AppDrop**.

## Security

- Zip Slip protection (absolute paths and directory traversal rejected)
- MIME type validation (only zip archives accepted)
- File size limits (configurable, default 20 MB)
- App ID format validation (lowercase alphanumeric + underscore, 3–64 chars)
- CSRF protection on all POST routes
- Admin-only enforcement on management endpoints

## Project Structure

```
appdrop/
├── appinfo/
│   ├── info.xml                        # App metadata
│   └── routes.php                      # Route definitions
├── lib/
│   ├── AppInfo/Application.php         # Bootstrap (IBootstrap)
│   ├── Controller/
│   │   ├── AdminController.php         # Main page + upload install
│   │   ├── AppManagerController.php    # List/enable/disable/remove apps
│   │   ├── BackupController.php        # Backup list/restore/delete
│   │   ├── HealthCheckController.php   # Pre-install validation
│   │   ├── HistoryController.php       # Upload history
│   │   ├── PermissionController.php    # User/group permission CRUD
│   │   ├── SettingsController.php      # App settings
│   │   └── TemplateGeneratorController.php  # Skeleton generator
│   ├── Db/
│   │   ├── UploadHistory.php           # Entity
│   │   └── UploadHistoryMapper.php     # Mapper
│   ├── Migration/                      # Database migrations
│   ├── Service/
│   │   ├── AppInstallService.php       # Upload, validate, extract, enable
│   │   ├── AppManagerService.php       # App list/enable/disable/remove
│   │   ├── AppPathResolver.php         # Writable apps path resolution
│   │   ├── BackupService.php           # Backup management
│   │   ├── HealthCheckService.php      # Zip analysis and validation
│   │   ├── PermissionService.php       # User/group access control
│   │   ├── TemplateGeneratorService.php # App skeleton zip generation
│   │   └── UploadHistoryService.php    # History persistence
│   └── Settings/
│       ├── AdminSection.php            # Admin settings section
│       └── AdminSettings.php           # Admin settings form
├── templates/                          # PHP view templates
├── js/                                 # Client-side scripts
├── css/                                # Styles
└── img/                                # App icon
```

## License

AGPL-3.0-or-later

## Authors

- Mehran Pourvahab
- Henrique Rodrigues
