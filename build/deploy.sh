#!/bin/bash

cd $(dirname $0)

source ../local-settings.sh

if [ -z "$DART_HOME" ]; then
    echo "Must specify DART_HOME"
    exit 1
fi
if [ -z "$DEPLOYMENT_DIR" -o ! -d "$DEPLOYMENT_DIR" ]; then
    echo "DEPLOYMENT_DIR must exist and be writable."
    exit 1
fi
if [ ! -f ../php/conf/site.conf.php -o ! -f ../php/conf/opauth.conf.php ]; then
    echo "You need to create local versions of the php/conf files."
    exit 1
fi

test -d deployed || mkdir deployed
test -d deployed && rm -r deployed/* 2>/dev/null

# Deploy the client files
test -d "$DEPLOYMENT_DIR/web" || mkdir "$DEPLOYMENT_DIR/web"


# Deploy the PHP files
cp -R ../php/lib ../php/web ../php/src "$DEPLOYMENT_DIR/."
