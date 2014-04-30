#!/bin/bash

if [ ! -d "$LIQUIBASE_HOME" ]; then
  echo "Set LIQUIBASE_HOME to the location where you unzipped the liquibase files:"
  echo "http://www.liquibase.org/download/index.html"
  exit 1
fi

test -d exports/sql || mkdir -p exports/sql

for i in ../php/admin/sql/tables/*.yaml; do
  name=$(basename "$i" .yaml)
  "$LIQUIBASE_HOME/liquibase" --changeLogFile "$i" updateSQL > exports/sql/$name.sql || exit 1
done

