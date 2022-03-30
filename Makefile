install:
	composer install

update:
	composer update

dump-autoload:
	composer dump-autoload

lint:
	composer run-script phpcs -- --standard=PSR12 src

lint-fix:
	composer run-script phpcbf -- --standard=PSR12 src

tests:
	php ./test.php