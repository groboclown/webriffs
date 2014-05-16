#!/bin/bash

cd $(dirname $0)

if [ ! -d exports/sql ]; then
    echo "You must run mkscripts.sh before this"
    exit 1
fi

source ../local-settings.sh

for i in exports/sql/*/*.sql; do
    echo "$i"
    mysql --user=${TEST_MYSQL_USER} --password=${TEST_MYSQL_PASSWD} ${TEST_MYSQL_DBNAME} < "$i"
done
echo 'INSERT INTO GA_SOURCE (Source_Name, Created_On, Last_Updated_On) VALUES ('\''local'\'', NOW(), NULL);' | mysql --user=${TEST_MYSQL_USER} --password=${TEST_MYSQL_PASSWD} ${TEST_MYSQL_DBNAME}
