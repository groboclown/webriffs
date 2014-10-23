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

Right now, WebRiffs is designed for a development environment.  Production
environment support will come soon after the initial development completes.

The build guide is (available here)[client/developing.md].


    
# Current Status

The authentication, film, and branch creation/editing is present.  The quip storage and retrieval are in the workings.  The UI is just a rudimentary outline that allows for data input.

## Feature Status

* The User Interface is just the bare minimum to show the data.  Later, a massive undertaking will begin to style and shape the html.
* User authentication is complete.
* Films can be created, and can have branches.
* Branch editing is present in a limited form.
* There's currently no way to recover a lost password.
* Administrative tools are non-existent beyond site set-up.
* No way to manage the user rights per branch beyond raw SQL.

## Known Bugs

* The main bugs are noted within the page.
* Need to add security checks at the top of each page, to see if the user has access to that functionality.  This is partially implemented.


# NTSC vs PAL vs 1:1 Ratio

[The Wikipedia Referenced Link](http://www.paradiso-design.net/videostandards.html)

Movies are usually filmed in 24 frames per second (there are exceptions), so
this is classified here as the "natural rate" of the film.  However, the
actual playback speed can fluctuate between 24 fps and 26 fps.

NTSC has a natural refresh rate of 29.97 fps.  Films are usually transferred to
NTSC format by using a 2:3 pulldown, which means the end-result playback of the
film is at 23.976 fps (the NTSC-Film standard).  A NTSC film is decelerated,
so that the NTSC film is 24/(24*1000/1001) times longer than the original film.

PAL has a natural refresh rate of 25 fps.  Films are directly transferred to PAL
format, so that the playback speed of films is at 25 fps.  This means that the
film is simply accelerates (played about 4.167% faster than the original).
So, a PAL film is 24/25 times longer than the original film.

SECAM, from what I can tell, uses the same refresh rate as PAL.

However, things just aren't that simple.

 * PAL DVD movie: 25 fps
 * PAL DVD specials: 25 fps
 * PAL TV or VHS: 25 fps (50 "fields" per second)
 * NTSC DVD movie: (24*1000/1001) fps or 24 fps (common motion picture films are encoded in 24 fps)
 * NTSC DVD specials: (24*1000/1001) fps, (30*1000/1001) fps, or 24 fps
 * NTSC TV or VHS: (24*1000/1001) fps, (30*1000/1001) fps, 24 fps, or (60*1000/1001) "fields" per second.
 * Blu-ray Disc movie: (24*1000/1001) fps or 24 fps (common motion picture films are encoded in (24*1000/1001) fps)
 * HD DVD movie: (24*1000/1001) fps or 24 fps (common motion picture films are encoded in 24 fps)
 * Cinema: 24 fps (nominal), unless it's _The Hobbit: An Unexpected Journey_, in which case it's 48 fps.
 * PC NTSC DVD movie: 24 fps
 
 Additionally, for DVD (24*1000/1001) fps playback, the time counter would
 display 100 film minutes (real time) as 100 min and 6 sec for the video
 time code.
 
 