#!/bin/bash

cd $(dirname $0)

source ../local-settings.sh

cd ../client

"$DART_HOME"/dart-sdk/bin/pub build
