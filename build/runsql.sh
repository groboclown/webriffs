#!/bin/bash

if [ ! -d exports/sql ]; then
    echo "You must run mkscripts.sh before this"
    exit 1
fi

source ../local-settings.sh
