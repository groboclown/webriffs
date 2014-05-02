#!/bin/bash

cd $(dirname $0)


source ../local-settings.sh

echo "Use the mysql 'root' password for this command."
echo "SET FOREIGN_KEY_CHECKS = 0; DROP DATABASE IF EXISTS $TEST_MYSQL_DBNAME;CREATE DATABASE $TEST_MYSQL_DBNAME;GRANT ALL PRIVILEGES ON $TEST_MYSQL_DBNAME.* TO $TEST_MYSQL_USER@localhost;" | mysql --user=root -p || exit 1


