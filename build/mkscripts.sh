#!/bin/bash

if [ ! -d "$LIQUIBASE_HOME" ]; then
  echo "Set LIQUIBASE_HOME to the location where you unzipped the liquibase files:"
  echo "http://www.liquibase.org/download/index.html"
  exit 1
fi

ARG=updateSQL
if [ "$1" = "-run" ]; then
  ARG=update
fi

source ../local-settings.sh
LIQUIBASE_ARGS="--driver=com.mysql.jdbc.Driver --classpath=$MYSQL_JDBC_JAR --url=jdbc:mysql://$TEST_MYSQL_HOST/$TEST_MYSQL_DBNAME --username=$TEST_MYSQL_USER --password=$TEST_MYSQL_PASSWD"

test -d exports/sql || mkdir -p exports/sql

# Make sure ordering is correct
for i in ../sql/GroboAuth/*.yaml ../sql/WebRiffs/*.yaml; do
  name=$(basename "$i" .yaml)
  echo "$name"
  if [ "$1" = '-run' ]; then
   "$LIQUIBASE_HOME/liquibase" $LIQUIBASE_ARGS --changeLogFile "$i" $ARG || exit 1
  else
   "$LIQUIBASE_HOME/liquibase" $LIQUIBASE_ARGS --changeLogFile "$i" $ARG > exports/sql/$name.sql || exit 1
  fi
done

