<?php

namespace WebRiffs;


/**
 * Basic access definitions.  These are common for all the different
 * layers of the system.
 */
class Access {
    // film access.  This is always top level, independent of branches.
    // That is, these should only live in the user_access table, not the
    // film_branch_X tables.
    static final $FILM_CREATE = 'film-create';
    static final $FILM_MODIFICATION = 'film-mod'; // change title, year
    static final $FILM_BRANCH = 'film-branch'; // creation of branches
    
    
    // top-level access to the branch; the name of the branch, the
    // tags for the branch, etc.
    static final $BRANCH_READ = 'branch-read';
    static final $BRANCH_WRITE = 'branch-write';
    
    
    // quips inside a branch
    static final $QUIP_READ = 'quip-read';
    static final $QUIP_WRITE = 'quip-write';
    static final $QUIP_TAG = 'quip-tag';
    
    
    // User access
    //static final $USER_EDIT = 'user-edit';
    static final $USER_BAN = 'user-ban';
    static final $USER_LOGIN = 'user-login';
    
    // Array of all rights that a user can have
    static $USER_RIGHTS;
    
    
    
    // Access permission levels
    static final $PRIVILEGE_NONE = 0;
    static final $PRIVILEGE_GUEST = 1;
    static final $PRIVILEGE_USER = 2;
    static final $PRIVILEGE_AUTHORIZED = 3;
    static final $PRIVILEGE_TRUSTED = 4;
    static final $PRIVILEGE_OWNER = 5;
    static final $PRIVILEGE_ADMIN = 100;
    
}

Access::$USER_RIGHTS = array(
    Access::$FILM_CREATE,
    Access::$FILM_MODIFICATION,
    Access::$FILM_BRANCH,
    Access::$BRANCH_READ,    Access::$BRANCH_WRITE,    Access::$QUIP_READ,    Access::$QUIP_WRITE,    Access::$QUIP_TAG,    Access::$USER_BAN,    Access::$USER_LOGIN,
);