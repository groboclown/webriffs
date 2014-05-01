#!/bin/bash

source ../local-settings.sh

if [ ! -d "$LIQUIBASE_HOME" ]; then
  echo "Set LIQUIBASE_HOME to the location where you unzipped the liquibase files:"
  echo "http://www.liquibase.org/download/index.html"
  exit 1
fi

ARG=updateSQL
if [ "$1" = "-run" ]; then
  ARG=update
fi

LIQUIBASE_ARGS="--driver=com.mysql.jdbc.Driver --classpath=$MYSQL_JDBC_JAR --url=jdbc:mysql://$TEST_MYSQL_HOST/$TEST_MYSQL_DBNAME --username=$TEST_MYSQL_USER --password=$TEST_MYSQL_PASSWD"

test -d exports/sql && rm -r exports/sql 2>/dev/null

# Make sure ordering is correct
DIRC=0
for d in ../sql/GroboAuth ../sql/WebRiffs; do
    if [ "$ARG" != 'update' ]; then
        outd=exports/sql/$(printf "%02d" $DIRC)
        test -d $outd || mkdir -p $outd
    fi
    for i in $d/*.yaml; do
        name=$(basename "$i" .yaml)
        echo "$DIRC/$name"
        if [ "$ARG" != 'update' ]; then
            "$LIQUIBASE_HOME/liquibase" $LIQUIBASE_ARGS --changeLogFile "$i" $ARG || exit 1
        else
            "$LIQUIBASE_HOME/liquibase" $LIQUIBASE_ARGS --changeLogFile "$i" $ARG > $outd/$name.sql || exit 1
        fi
    done

    DIRC=$(( $DIRC + 1 ))
done
