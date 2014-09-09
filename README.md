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
 * Films can be created, and can have branches.
 * Branch editing is still under development.
 * There's currently no way to recover a lost password.
 * Administrative tools are non-existent beyond site set-up.
 * Support for multiple media time code captures:
 * * A "stopwatch" video timer, for DVDs or other media.
 * * YouTube videos.

## Known Bugs

 * Need to add security checks at the top of each page, to see if the user has access to that functionality.  This is partially implemented.
 * Log in and log out does not trigger a security refresh for the current page.
 * Security checks for editing a branch should check if the user can edit the quips on the branch, rather than if the user can edit the branch header information.
 

# Architecture Issues

I'm still pondering some fundamental questions about the data design for the
package.

## Media Association

Right now, a Film can have a set of links associated with it.  If one of those
links matches up with a known embedded media provider (e.g. YouTube), then that
player is embedded with the given link.  This means that a Film is associated
with one specific media format.  If we have one film that has several different
edits, each edit would need to be its own Film.

Instead, this could be done per branch.  This way, we could have one film
(say, _Blade Runner_), and one branch per different edit of that film.  This
would mean, though, that the time codes in one branch could only correctly be
shared with another branch if they share the same media link.

I'm working now on automatic conversion between playback formats, to make this
less of a problem.  That way, you can watch the same riffs on both NTSC, PAL,
and the original film version.  If it's the same edit, then hopefully the times
will line up.
