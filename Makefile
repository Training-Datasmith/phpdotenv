install:
	@echo "Use: bash scripts/system-test.sh (digest-pinned Docker)"

phpunit:
	@bash scripts/system-test.sh

phpstan-analyze:
	@bash scripts/system-test-phpstan.sh

test: phpunit phpstan-analyze

clean:
	@rm -rf .phpunit.result.cache composer.lock vendor vendor-bin/*/composer.lock vendor-bin/*/vendor
