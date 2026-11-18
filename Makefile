.PHONY: test lint

test:
	XDEBUG_MODE=coverage php vendor/bin/phpunit --display-warnings --display-notices --display-errors --fail-on-warning --fail-on-deprecation --fail-on-notice --coverage-html test/results

lint:
	find src test examples -name '*.php' -exec php -l {} \;
