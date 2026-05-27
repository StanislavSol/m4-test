install:
	composer install
	composer dump-autoload

run:
	php script.php

clean:
	rm -rf vendor/ composer.lock
