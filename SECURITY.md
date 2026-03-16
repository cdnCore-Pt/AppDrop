# Security Policy

## Reporting a vulnerability

If you discover a security vulnerability in AppDrop, please report it responsibly.

**Do not open a public issue.**

Instead, email the maintainers directly or use [GitHub's private vulnerability reporting](https://github.com/cdnCore-Pt/AppDrop/security/advisories/new).

We will acknowledge your report within 48 hours and aim to release a fix as soon as possible.

## Scope

AppDrop handles file uploads and app installation, which are security-sensitive operations. The following areas are particularly relevant:

- Zip archive extraction (Zip Slip / path traversal)
- File type and MIME validation
- Permission and access control
- Input sanitization on app IDs and directory names

## Supported versions

| Version | Supported |
|---|---|
| 1.x | Yes |
