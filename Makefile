#/bin/bash

.PHONY: tests
tests:
	make build
	docker-compose run search-bundle

.PHONY: build
build:
	docker-compose build
