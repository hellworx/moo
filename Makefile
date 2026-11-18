.PHONY: test coverage lint phpstan check

PHPUNIT_FLAGS = --display-warnings --display-notices --display-errors --fail-on-warning --fail-on-deprecation --fail-on-notice

test:
	php vendor/bin/phpunit $(PHPUNIT_FLAGS)

coverage:
	XDEBUG_MODE=coverage php vendor/bin/phpunit $(PHPUNIT_FLAGS) --coverage-html test/results

lint:
	find src test examples -name '*.php' -exec php -l {} \;

phpstan:
	php vendor/bin/phpstan analyse --no-progress

check: test lint phpstan
	composer validate --strict
