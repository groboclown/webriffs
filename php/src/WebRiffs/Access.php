<?php

namespace WebRiffs;


/**
 * Basic access definitions.  These are common for all the different
 * layers of the system.
 */
class Access {
    // Administration permissions.
    static $ADMIN_USER_MOD = "admin-user-mod";
    static $ADMIN_USER_DEL = "admin-user-del";
    static $ADMIN_USER_BAN = "admin-user-ban";
    static $ADMIN_LINKS = "admin-links";
    
    
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
    static $BRANCH_USER_MAINTENANCE = 'branch-users';
    static $BRANCH_DELETE = 'branch-del';
    static $BRANCH_TAG = 'branch-tag';
    
    
    // quips inside a branch
    static $QUIP_READ = 'quip-read';
    static $QUIP_WRITE = 'quip-write';
    static $QUIP_TAG = 'quip-tag';
    
    
    
    // Array of all rights that a user can have
    static $USER_ACCESS;
    
    
    // Array of all rights that a branch has
    static $BRANCH_ACCESS;
    
    
    
    // Access permission levels; these only apply to the USER_ACCESS table
    // and the FILM_BRANCH_ACCESS table when User_Id = null.
    static $PRIVILEGE_NONE = 0; // Blocked access
    static $PRIVILEGE_GUEST = 1; // A non-logged in user
    static $PRIVILEGE_USER = 2;
    static $PRIVILEGE_AUTHORIZED = 3;
    static $PRIVILEGE_TRUSTED = 4;
    static $PRIVILEGE_OWNER = 5;
    static $PRIVILEGE_ADMIN = 100;
    
}

Access::$USER_ACCESS = array(
    Access::$ADMIN_USER_MOD,
    Access::$ADMIN_USER_DEL,
    Access::$ADMIN_USER_BAN,
    Access::$ADMIN_LINKS,
    Access::$FILM_CREATE,
    Access::$FILM_MODIFICATION,
    Access::$FILM_BRANCH,
    Access::$BRANCH_READ,    Access::$BRANCH_WRITE,
    Access::$BRANCH_USER_MAINTENANCE,
    Access::$BRANCH_DELETE,    Access::$QUIP_READ,    Access::$QUIP_WRITE,    Access::$QUIP_TAG,);

Access::$BRANCH_ACCESS = array(
    Access::$BRANCH_READ,
    Access::$BRANCH_WRITE,
    Access::$BRANCH_USER_MAINTENANCE,
    Access::$BRANCH_DELETE,
    Access::$BRANCH_TAG,
    Access::$QUIP_READ,
    Access::$QUIP_WRITE,
    Access::$QUIP_TAG,
);
