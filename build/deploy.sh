#!/bin/bash

cd $(dirname $0)

source ../local-settings.sh

if [ -z "$DEPLOYMENT_DIR" -o ! -d "$DEPLOYMENT_DIR" ]; then
    echo "DEPLOYMENT_DIR must exist and be writable."
    exit 1
fi
# This is optional, because of the admin.php page
#if [ ! -f ../php/conf/site.conf.php -o ! -f ../php/conf/opauth.conf.php ]; then
#    echo "You need to create local versions of the php/conf files."
#    exit 1
#fi
if [ ! -d exports/dbo ]; then
    echo "You need to run 'build.py generate_dbo' first."
    exit 1
fi
if [ ! -f exports/web/main.dart ]; then
    echo "You need to run 'build.py generate_client_js copy_client' first."
    exit 1
fi

if [ "$1" == "-c" ]; then
    rm -r "$DEPLOYMENT_DIR"/*
    shift
fi

#  Deploy the files
cp -R exports/* "$DEPLOYMENT_DIR/." || exit 1

if [ "$1" == "-nc" ]; then
    rm "$DEPLOYMENT_DIR"/conf/*.conf.php
else
    mv "$DEPLOYMENT_DIR/web/admin.php" "$DEPLOYMENT_DIR/web/.htadmin.php"
fi

