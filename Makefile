.PHONY: build test lint clean

build:
	docker compose build

test: build
	docker compose run --rm test vendor/bin/phpunit

lint: build
	docker compose run --rm test php -l src/Vinkius.php

clean:
	docker compose down --rmi local
