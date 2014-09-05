webriffs
========

Custom movie riffs managed from your web browser.



# Why WebRiffs?

Have you ever watched _Mystery Science Theater 3000_ or _Riff Tracks_ and thought, "Hey, I could do that!" but didn't have the tools to record your comments?  Well now you can!

WebRiffs allows you to create your own comments to a film (with time markers), and share them with friends.  You can even collaborate with friends to create community comments.



# How it Works

In WebRiffs, each registered film has a set of _branches_.  These branches can have their own set of comments, called "quips".  This allows for comments to be in different groups, such as one for "snark" and one for "filming locations".


# What's Required

WebRiffs runs on PHP and MySQL, and uses the browser to manage and maintain a repository of comments.



# Building WebRiffs

You'll need to install Python v3.2 or higher, and Dart v1.5 or higher.  You'll need the `dart-sdk/bin` set in your `PATH`.

Then, create a copy of `local-settings.sh.template` as `local-settings.sh` and fill in the necessary fields.

In the `client` directory, setup the Dart dependencies with:

    pub install

You can then create the sql files, php dbo files, and the web directory layout by running from the `build` directory:

    python build.py all

This will put the web-server files out at `build/exports`.  That directory has the correct layout as expected by the server.  Note that the web accesible files are in the `web` subdirectory.

To simulate an install, you can run:

    python build.py copy_php_test

This will remove the `admin.php` file, whose existence prohibits the normal execution of the server.

You will need to setup the MySql database to load the SQL files.  You can do that by running from the `build` directory:

    ./recreate-db.sh

This will touch the test setup page and create some initial data.


    
# Current Status

The authentication, film, and branch creation/editing is present.  The quip storage and retrieval are in the workings.  The UI is just a rudimentary outline that allows for data input.

## Feature Status

 * The User Interface is just the bare minimum to show the data.  Later, a massive undertaking will begin to style and shape the html.
 * User authentication is complete.
 * Films 
 * Branch editing is still under development.
 * There's currently no way to recover a lost password.
 * Administrative tools are non-existent beyond site set-up.
 * Currently there's only support for a "stopwatch" video timer.  Eventually, this will allow for embeddable video playback services such as YouTube and Twitch.

## Known Bugs

 * The "Add a Film" page can be accessed even if the user doesn't have the authorization to create it.  This needs to check the user access when showing the create button.
 * The "Edit this branch" link on the "View Branch" page is flaky.  It appears at odd times.  If you log out, the link is still on the page.
 * Need to add sercurity checks at the top of each page, to see if the user has access to that functionality. 
 