#!/bin/bash


cd $(dirname $0)

source ../local-settings.sh

test -d exports/dbo && rm -r exports/dbo 2>/dev/null

PYTHONPATH="${PYTHONPATH}:../sql-migration/src"
for d in ../sql/GroboAuth ../sql/WebRiffs; do
    dd=$(basename $d)  
    outd=exports/dbo/$dd
    test -d ${outd} || mkdir -p ${outd}

    python3 ../sql-migration/src/genPhpDboLayer.py Base\\DboParent ${dd} $d ${outd}
done

