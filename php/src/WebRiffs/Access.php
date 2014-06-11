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
    static $FILM_CREATE = 'film-create';
    static $FILM_MODIFICATION = 'film-mod'; // change title, year
    static $FILM_BRANCH = 'film-branch'; // creation of branches
    
    
    // top-level access to the branch; the name of the branch, the
    // tags for the branch, etc.
    static $BRANCH_READ = 'branch-read';
    static $BRANCH_WRITE = 'branch-write';
    
    
    // quips inside a branch
    static $QUIP_READ = 'quip-read';
    static $QUIP_WRITE = 'quip-write';
    static $QUIP_TAG = 'quip-tag';
    
    
    // User access
    //static final $USER_EDIT = 'user-edit';
    static $USER_BAN = 'user-ban';
    static $USER_LOGIN = 'user-login';
    
    // Array of all rights that a user can have
    static $USER_RIGHTS;
    
    
    
    // Access permission levels
    static $PRIVILEGE_NONE = 0;
    static $PRIVILEGE_GUEST = 1;
    static $PRIVILEGE_USER = 2;
    static $PRIVILEGE_AUTHORIZED = 3;
    static $PRIVILEGE_TRUSTED = 4;
    static $PRIVILEGE_OWNER = 5;
    static $PRIVILEGE_ADMIN = 100;
    
}

Access::$USER_RIGHTS = array(
    Access::$FILM_CREATE,
    Access::$FILM_MODIFICATION,
    Access::$FILM_BRANCH,
    Access::$BRANCH_READ,    Access::$BRANCH_WRITE,    Access::$QUIP_READ,    Access::$QUIP_WRITE,    Access::$QUIP_TAG,    Access::$USER_BAN,    Access::$USER_LOGIN,
);