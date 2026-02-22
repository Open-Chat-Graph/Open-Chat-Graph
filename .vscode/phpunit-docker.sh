#!/bin/bash
shift
exec docker compose exec -T app vendor/bin/phpunit "$@"
