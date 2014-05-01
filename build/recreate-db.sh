#!/bin/bash


source ../local-settings.sh

echo "Use the 'root' password for this command."
echo "SET FOREIGN_KEY_CHECKS = 0; DROP DATABASE IF EXISTS $TEST_MYSQL_DBNAME;CREATE DATABASE $TEST_MYSQL_DBNAME;" | mysql --user=root -p || exit 1


