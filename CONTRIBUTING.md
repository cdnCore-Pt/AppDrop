# Contributing to AppDrop

Thank you for your interest in contributing to AppDrop!

## Reporting bugs

Please open an issue at [GitHub Issues](https://github.com/cdnCore-Pt/AppDrop/issues) with:

- Nextcloud version
- PHP version
- Steps to reproduce
- Expected vs actual behavior

## Submitting changes

1. Fork the repository
2. Create a feature branch from `main`
3. Make your changes
4. Run the test suite: `make all` (or see [tests/README.md](tests/README.md))
5. Open a pull request against `main`

## Code style

This project uses [php-cs-fixer](https://github.com/PHP-CS-Fixer/PHP-CS-Fixer) with the Nextcloud coding standard. Run `make lint` to check.

## Tests

All new features and bug fixes should include unit tests. See [tests/README.md](tests/README.md) for how to run them.

## Translations

Translation contributions are welcome. Add or update files in the `l10n/` directory.

## License

By contributing, you agree that your contributions will be licensed under the AGPL-3.0-or-later license.
