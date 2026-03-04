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
	cd $(CURDIR) && ./vendor/bin/psalm

package: clean
	mkdir -p $(build_dir)/$(app_name)
	rsync -a --exclude=build --exclude=.git --exclude=.github --exclude=tests \
		--exclude=psalm.xml --exclude=phpunit.xml --exclude=.php-cs-fixer.dist.php \
		--exclude=.php-cs-fixer.cache --exclude=composer.lock --exclude=CHANGELOG.md \
		--exclude=README.md --exclude=Makefile --exclude=krankerl.toml \
		--exclude=.nextcloudignore --exclude=.gitignore \
		$(CURDIR)/ $(build_dir)/$(app_name)/
	cd $(build_dir) && tar -czf $(app_name).tar.gz $(app_name)

sign: package
	docker exec -u www-data nextcloud-app-1 php occ integrity:sign-app \
		--path=/var/www/html/custom_apps/$(app_name) \
		--privateKey=/path/to/$(app_name).key \
		--certificate=/path/to/$(app_name).crt

.PHONY: all clean test lint psalm package sign
