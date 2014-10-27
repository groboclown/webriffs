# Developing the Dart Client

## Setting up your environment

You'll need to use the general build environment to construct the correct
database schema and PHP environment.  After that, there is some trickery to
allow the speedy `pub serve` to work with the web server, so that you don't
need to constantly be running a build to try out your updates.


### Prerequisites

You'll need to make sure you have MySql, Apache HTTPD 2.4+, Python 3.4+,
Dart 1.7+, and PHP 5.5+ installed on your development machine.  You can use a
different web server than Apache HTTPD, but setting those up are left as an
exercise to the reader. _TODO double check the PHP version number_

For MySql, you will need to create a new database dedicated to the project.
It will need a user to act for the normal setup.

You can run this on either Windows or Linux (and probably Mac), but right now
these instructions are for Linux.


### Download the code

Pull down from GitHub the master branch:

    $ git clone git@github.com:groboclown/webriffs.git webriffs
    $ cd webriffs
    $ git submodule init
    $ git submodule update


### Environment Variables

The build tool relies upon the `local-settings.sh` file to store the various
information used by the build.  You can ignore setting the root MySql user and
password, as that's not supported at the moment.

You can also pre-setup the PHP configuration by copying
`php/conf/site.conf.php.template` to `php/conf/site.conf.php` and setting the
configuration.


### Apache HTTPD Setup

The system uses PHP running in a web server as the business logic layer above
the database.  This guide shows how to setup an Apache HTTPD server to work
with this PHP web side.

There are many guides on setting up an Apache HTTPD server.  The key point
to remember is ensuring that it has PHP enabled
(`LoadModule php5_module .....`).  You may be using virtual hosts or not,
but either way, the following steps are required.

First, to allow easier control over the directory read access for Apache, I
created a soft link from my `webriffs/build/exports` directory into
`/usr/local/share/webriffs`

    # cd /usr/local/share
    # ln -s /dir/to/webriffs/build/exports/ webriffs

Then I set the `httpd.conf` file to include a pointer into the `web`
sub-directory.  Note that, because of the PHP library files and configuration
files, you only want the web directory exposed.

    <Directory "/usr/local/share/webriffs">
        AllowOverride All
        Order allow,deny
        Options FollowSymLinks
        Allow from all
    </Directory>
    Alias /webriffs /usr/local/share/webriffs/web

You'll definitely want the `ErrorLog` pointing to a file you can easily read,
because you'll be checking that for the PHP error messages.

#### Proxy to the Dart server

The standard deployment of the application has the Dart client files (Dart
sources, Dart compiled JS files, and the support client files) living in the
same web server as the PHP files.  The `php/web/.htaccess` file partially
describes this relationship.

When developing the Dart web client, it's much faster to use the `pub serve`
command to allow for a fast turn-around between editing and testing.  It will
automatically redeploy edited files out to the web server (including recompiling
Dart to JS when testing in standard web browsers).  However, the `pub serve`
web server doesn't handle the PHP layer, and doesn't allow for pass-through
proxy to the httpd server that handles the PHP side.

The method described here will enable the Apache HTTPD reverse proxy, so that
non-PHP requests instead redirect to the Dart `pub serve` server.
This requires enabling the `mod_proxy` and `mod_proxy_http` extensions in
Apache (that's an excercise left to the reader - it depends upon how you have
Apache installed).

You'll need to add the Proxy settings into the `httpd.conf` file, as these
settings cannot live in a `.htaccess` file.  If you are using virtual hosts,
this setting must be inside the virtual host for the server.

    <IfModule mod_proxy.c>
        ProxyRequests Off
        
        # Exclude the mapped PHP files
        ProxyPass /webriffs/*.php !
        ProxyPass /webriffs/api/ !
        
        ProxyPass /webriffs/ http://localhost:8080/
        ProxyVia Block
    </IfModule>

This will pass the non-php requests to the underlying Dart `pub serve`
service.  Note that if you run `pub serve` on a different IP address, or if you
run the `pub serve` on a different server, you will need to change the URL
appropriately (from `http://localhost:8080/` in the example above to the
one that's correct for your installation).


### Dart Setup

The Dart client files are a bit different than the rest of the system, due to
Dart having its own built-in build system.  All of these commands are run from
the `client` directory.

The client Dart package needs to be setup with the correct library dependencies.
This requires having an active Internet connection.

    `pub get`



## Build

The standard build tool for the project is located in the `build` directory.
It is invoked with `build.py (targets)`.  For the purposes of this document,
here's the standard steps that need to be run:

### First run or when the SQL files are altered

    build.py generate_sql generate_dbo
    
This creates the SQL input files and the PHP data access layer.

### First run or when the PHP files are altered

    build.py copy_php_test
    
For an official build, you just want `copy_php`, but for standard development,
you'll want the `copy_php_test`.  This simulates constructing an existing
install.  If you want to test out the administration setup page, then just
use `copy_php`.

### Load the SQL files and test data

    ./recreate-db.sh && ./runsql.sh
    
These will drop the existing SQL database and recreate it with the generated
SQL files.  That means **all existing data will be wiped clean.**  It will
also run the test data PHP script to generate a bit of initial sample data
to play with.

### Official Release

    build.py all
    
This will clean out the `build/exports` directory, and create the official
distribution files.  The admin page will be active, and there shouldn't be a
`site.conf.php` or other configuration file present.
