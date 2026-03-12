.SILENT:
SHELL := /bin/bash

ROOT_DIR := $(abspath $(dir $(lastword $(MAKEFILE_LIST))))
BTREE_DEPTH ?= 5
ANALYZE_PATHS ?= src

.PHONY: help tree test test-unit static stan psalm phpcs

help: ## Show available targets
	@echo "Available targets:"
	@grep -E '^[a-zA-Z0-9_-]+:.*##' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*##"}; {printf "  %-12s %s\n", $$1, $$2}'

tree: ## Print project tree (depth via BTREE_DEPTH)
	@cd '$(ROOT_DIR)' && \
	find . -mindepth 1 -maxdepth $(BTREE_DEPTH) \
	  \( -name '.git' -o -name 'vendor' -o -name 'node_modules' -o -name '.idea' -o -name '.cache' \) -prune -o -print \
	| sed 's#^\./##' \
	| sort

test: ## Run all tests
	@composer test

test-unit: ## Run unit tests only
	@vendor/bin/codecept run unit

stan: ## Run PHPStan static analysis
	@vendor/bin/phpstan analyse $(ANALYZE_PATHS) --level=max --memory-limit=1G

psalm: ## Run Psalm static analysis
	@vendor/bin/psalm --no-cache --threads=1 $(ANALYZE_PATHS)

phpcs: ## Run PHP_CodeSniffer (PSR-12)
	@vendor/bin/phpcs --standard=PSR12 $(ANALYZE_PATHS)

static: stan psalm phpcs ## Run all static analyzers
