#!/bin/bash

cd $(dirname $0)

source ../local-settings.sh

test -d exports/sql && rm -r exports/sql 2>/dev/null

# Make sure ordering is correct
DIRC=0
PYTHONPATH="${PYTHONPATH}:../sql-migration/src"
for d in ../sql/GroboAuth ../sql/WebRiffs; do
    outd=exports/sql/$(printf "%02d" ${DIRC})
    test -d ${outd} || mkdir -p ${outd}


    python3 ../sql-migration/src/genBaseSql.py mysql ${d} ${outd}

    DIRC=$(( $DIRC + 1 ))
done
