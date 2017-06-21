# Coding Style

cs:
	./bin/php-cs-fixer fix --dry-run --stop-on-violation --diff

cs-fix:
	./bin/php-cs-fixer fix

cs-ci:
	./bin/php-cs-fixer fix --dry-run --using-cache=no --verbose