#! /bin/bash

set -ex

cd ..

# make sure that LOG_DIR exists before trying to write to a file in it
mkdir -p $LOG_DIR

php -S 127.0.0.1:8080 -t $(pwd) > $LOG_DIR/php-server-logs.txt 2>&1 &

cd mediawiki

composer phpunit:entrypoint -- extensions/EntitySchema/tests/phpunit/

cd extensions/EntitySchema

npm run test

npm run selenium-test

kill %1
