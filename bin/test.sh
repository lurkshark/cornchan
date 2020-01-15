#!/bin/sh
set -e
# Test service has the actual tests
docker-compose -f "docker-compose.yml" down
docker-compose -f "docker-compose.yml" up --exit-code-from test

