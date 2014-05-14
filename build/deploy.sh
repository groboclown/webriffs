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
if [ ! -d exports/dbo ]; then
    echo "You need to run gen-dbo.sh first."
    exit 1
fi
if [ ! -d ../client/build ]; then
    echo "You need to run gen-client.sh first."
    exit 1
fi

test -d deployed || mkdir deployed
test -d deployed && rm -r deployed/* 2>/dev/null

# Deploy the client files
test -d "$DEPLOYMENT_DIR/web" || mkdir "$DEPLOYMENT_DIR/web"
cp -R ../client/build/web/* ../client/build/web/.ht* "$DEPLOYMENT_DIR/web/."
# For native Dart browsers
cp -R ../client/web/* ../client/web/.ht* "$DEPLOYMENT_DIR/web/."
# A work-around for issues w/ strangely bundled dart dirs
test -d ../client/build/x-web && cp -R ../client/build/x-web/* ../client/build/x-web/.ht* "$DEPLOYMENT_DIR/web/."

# Deploy the PHP files
cp -R exports/dbo ../php/lib ../php/web ../php/src "$DEPLOYMENT_DIR/." || exit 1

# Deploy the configuration files
test -d "$DEPLOYMENT_DIR/conf" || mkdir "$DEPLOYMENT_DIR/conf"
cp -R ../php/conf/*.conf.php "$DEPLOYMENT_DIR/conf" || exit 1

