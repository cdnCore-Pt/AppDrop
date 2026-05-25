app_name := appdrop
build_dir := $(CURDIR)/build

all: lint psalm test

clean:
	rm -rf $(build_dir)

test:
	cd $(CURDIR) && ./vendor/bin/phpunit

lint:
	cd $(CURDIR) && ./vendor/bin/php-cs-fixer fix --dry-run --diff

psalm:
	cd $(CURDIR) && php vendor/psalm/phar/psalm.phar --no-cache

# Packaging and signing live in one place (scripts/prepare-appstore.sh), which
# reads the canonical exclude list from .nextcloudignore. Don't reintroduce a
# second exclude list here — the signed tree must equal the shipped tree.
package:
	./scripts/prepare-appstore.sh --package-only

sign:
	./scripts/prepare-appstore.sh --sign-only

package-test:
	./tests/test-package.sh

.PHONY: all clean test lint psalm package sign package-test
