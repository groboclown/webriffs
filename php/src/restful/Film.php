<?php

namespace WebRiffsRest;

require_once(__DIR__.'/Resource.php');

use Tonic;
use WebRiffs;
use Base;


/*
 * For all changes that are requested by a user, they occur in the user's
 * pending change for the branch.  The user's visible details is the
 * head active change for the branch + the user's pending change.
 */


/**
 * References information about the films.
 *
 * @uri /film
 */
class FilmCollection extends Resource {
    
    
    /**
     * "GET" returns the records, and includes paging.  All films are public,
     * while the individual branches are not necessarily public.
     *
     * @method GET
     */
    public function fetch() {
        $db = $this->getDB();
        
        $result = WebRiffs\FilmLayer::pageFilms($db);
        
        return array(200, $result);
    }



    /**
     * @method PUT
     * @csrf create_film
     */
    public function create() {
        $this->secure(WebRiffs\Access::$FILM_CREATE,
                WebRiffs\Access::$PRIVILEGE_AUTHORIZED);
        
        // FIXME client side is still referencing the wrong keys
        $name = $this->loadRequestString("Name");
        $year = $this->loadRequestInt("Release_Year");
        $this->validate();
        
        $db = $this->getDB();
        
        $idList = WebRiffs\FilmLayer::createFilm($db, $this->container['user'],
            $name, $year,
            WebRiffs\BranchLayer::$DEFAULT_TEMPLATE_ACCESS_NAME);

        $data = array(
            'Film_Id' => $idList[1],
            'Branch_Id' => $idList[2],
            'Change_Id' => $idList[3]
        );

        return new Tonic\Response(Tonic\Response::CREATED, $data);
    }
}


/**
 * Does the film with the given name and year exactly exist?  This is needed
 * over the general filter search, because the filter search checks if any
 * film like the name exists (designed for humans to find information, not for
 * computers to check for existence).
 *
 * @uri /filmexists
 */
class FilmNameObj extends Resource {
    /**
     * @method GET
     */
    public function fetch() {
        $name = $this->loadGetString("Name");
        $year = $this->loadGetInt("Release_Year");
        $this->validate();
        
        return array(200, array(
            'exists' => WebRiffs\FilmLayer::doesFilmExist(
                    $this->getDB(), $name, $year)
        ));
    }
}



/**
 *
 * @uri /film/:filmid
 */
class FilmObj extends Resource {
    /**
     * @method GET
     */
    public function display() {
        $filmId = $this->validateId($this->filmid, "filmId");
        $this->validate();
                
        $db = $this->getDB();
        
        $row = WebRiffs\FilmLayer::getFilm($db, $filmId);
        
        if (!$row) {
            throw new Tonic\NotFoundException;
        }
        
        $links = WebRiffs\FilmLayer::getLinksForFilm($db, $filmId);
        $row['links'] = $links;

        return new Tonic\Response(200, $row);
    }



    /**
     * @method POST
     * @csrf update_film
     */
    public function update() {
        $this->secure(WebRiffs\Access::$FILM_MODIFICATION,
                WebRiffs\Access::$PRIVILEGE_AUTHORIZED);
        
        $filmId = $this->validateId($this->filmid, "filmId");
        $name = $this->loadRequestString("Name");
        $year = $this->loadRequestInt("Release_Year");
        if ($year !== null) {
            $this->checkThat($year >= 1800 && $year <= 9999,
                'Release_Year', "Release_Year must be within the bounds [1800, 9999]");
        }
        $this->validate();
        
        $db = $this->getDB();
        WebRiffs\FilmLayer::updateFilm($db, $filmId, $name, $year);
        
        return $this->display();
    }



    /**
     * @method DELETE
     * @csrf delete_film
     */
    function remove() {
        $this->secure(WebRiffs\Access::$FILM_DELETE,
                WebRiffs\Access::$PRIVILEGE_AUTHORIZED);
        
        $filmId = $this->validateId($this->filmid, "filmId");
        $this->validate();
        
        $db = $this->getDB();
        
        // FIXME perform the delete

        return new Tonic\Response(Tonic\Response::NOCONTENT);
    }
}



/**
 *
 * @uri /film/:filmid/link
 */
class FilmObjLinkCollection extends Resource {
    /**
     * @mthod GET
     */
    public function fetch() {
        $filmId = $this->validateId($this->filmid, "filmId");
        $this->validate();
        
        $db = $this->getDB();
        $result = WebRiffs\FilmLayer::getLinksForFilm($db, $filmId);
        return array(200, $result);
    }
}


/**
 * @uri /film/:filmid/link/:linktypename
 */
class FilmObjLink extends Resource {
    /**
     * @method POST
     * @csrf save_film_link
     */
    public function update() {
        $this->secure(WebRiffs\Access::$FILM_MODIFICATION,
                WebRiffs\Access::$PRIVILEGE_AUTHORIZED);
        
        $filmId = $this->validateId($this->filmid, "filmId");
        $linkTypeName = $this->linktypename;
        // FIXME validate input text of $linkTypeName -  non-null and such.
        $uri = $this->loadRequestString("Uri");
        $this->validate();
        
        $db = $this->getDB();
        
        WebRiffs\FilmLayer::saveLinkForFilm($db, $filmId, $linkTypeName, $uri);
        return new Tonic\Response(Tonic\Response::ACCEPTED);
    }
}



/**
 * Returns all the branches visible to the user (or that are
 * publicly visible for those not logged in).
 *
 * @uri /film/:filmid/branch
 */
class FilmObjBranch extends Resource {
    /**
     *
     * @method GET
     */
    function fetch() {
        $filmId = $this->validateId($this->filmid, "filmId");
        $this->validate();
        
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        
        $db = $this->getDB();
        
        $result = WebRiffs\BranchLayer::pageBranches($db, $userId, $filmId);
        
        return array(200, $result);
    }
    
    
    /**
     * @method PUT
     * @csrf create_branch
     */
    function create() {
        $filmId = $this->validateId($this->filmid, "filmId");
        
        // Any user can create a new branch.
        $this->secure(WebRiffs\Access::$FILM_BRANCH,
                WebRiffs\Access::$PRIVILEGE_USER);

        // FIXME make these parameter keys match what the rest of the
        // API uses (first letter capitalized).
        $branchName = Validation::normalizeBranchName(
                $this->loadRequestString("name"), $this);
        $desc = $this->loadRequestString("description", FALSE) || "";
        $this->validate();
        
        $userInfo = $this->container['user'];
        
        $db = $this->getDB();
        
        $result = WebRiffs\BranchLayer::createBranch($db, $filmId,
            $userInfo['User_Id'], $userInfo['Ga_User_Id'],
            $branchName, $desc, null);
        
        $data = array(
            'Branch_Id' => $result[0],
            'Change_Id' => $result[2]
        );
        
        return array(200, $data);
    }
    
    
}



/**
 * Does the branch with the given name exactly exist?  This is needed
 * over the general filter search, because the filter search checks if any
 * branch like the name exists (designed for humans to find information, not for
 * computers to check for existence).
 *
 * FIXME this should be like BitBucket or GitHub, where each user can have
 * their own branch names.
 *
 * @uri /film/:filmid/branchexists
 */
class FilmObjBranchName extends Resource {
    /**
     * @method GET
     */
    public function fetch() {
        $filmId = $this->validateId($this->filmid, "filmId");
        
        $name = $this->loadGetString("Name");
        $this->validate();

        return array(200, array(
            'exists' => WebRiffs\BranchLayer::doesBranchExist(
                    $this->getDB(), $filmId, $name)
        ));
    }
}


/**
 * Retrieves the head version number for the branch.  Used for standard
 * viewing of a branch, so that subsequent submits won't affect the
 * current viewing.
 *
 * @uri /branch/:branchid/head
 */
class BranchObjHeadVersion extends Resource {
    /**
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
        
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        
        $result = WebRiffs\BranchLayer::getHeadBranchVersion($this->getDB(),
            $userId, $branchId);
        
        return array(200, $result);
    }
}


/**
 * All committed changes for the branch.
 *
 * @uri /branch/:branchid/version
 */
class BranchObjChanges extends Resource {
    /**
     * Retrieve all the changes for the branch.  This is a paging operation.
     *
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
        
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        
        $result = WebRiffs\BranchLayer::pageBranchVersions($this->getDB(),
            $userId, $branchId, null);
        
        return array(200, $result);
    }
    
    
    /**
     * @method POST
     * @csrf edit_branch
     */
    function update() {
        $branchId = $this->validateId($this->branchid, "branchId");
        
        // FIXME match argument names with the rest of the API
        $branchName = Validation::normalizeBranchName(
                $this->loadRequestString('name'), $this);
        $desc = $this->loadRequestString('description', FALSE) || "";
        
        $this->validate();
        
        
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        
        $tags = array();
        if (!! $data->{'tags'} && is_array($data->{'tags'})) {
            foreach ($data->{'tags'} as $tag) {
                if (!! $tag && is_string($tag)) {
                    $tag = trim($tag);
                    if (strlen($tag) > 0) {
                        $tags[] = $tag;
                    }
                }
            }
        }
        
        $this->validate();
        
        WebRiffs\BranchLayer::updateBranchHeader($this->getDB(), $branchId,
            $userId, $gaUserId, $branchName, $desc, $tags);
        
        return new Tonic\Response(Tonic\Response::ACCEPTED);
    }
    
    
    /**
     * Calls the post method.  Conceptually, the user is updating a branch,
     * but it can also be viewed as adding a new change to the branch.  So
     * we'll allow both.
     *
     * @method PUT
     * @csrf edit_branch
     */
    function create() {
        return $this->update();
    }
}


/**
 * Returns details about committed changes on a branch.
 *
 * @uri /branch/:branchid/version/:changeid
 */
class BranchObjChangeVersion extends Resource {
    
    /**
     * Get the details for the branch.  If the branch is not visible by the
     * user, then it returns a "no content" response.
     *
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $changeId = $this->validateId($this->changeid, "changeId");
        $this->validate();
        
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        $ret = WebRiffs\BranchLayer::getBranchDetails($this->getDB(),
            $userId, $branchId, $changeId);
        
        if (! $ret) {
            return new Tonic\Response(Tonic\Response::NOCONTENT);
        }
        return array(200, $ret);
    }
}



/**
 * Paging for the committed branch quips.
 *
 * @uri /branch/:branchid/version/:changeid/quip
 */
class BranchObjQuips extends Resource {
    
    /**
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $changeId = $this->validateId($this->changeid, "changeId");
        $this->validate();
    
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        $ret = WebRiffs\QuipLayer::pageCommittedQuips($this->getDB(),
                $userId, $branchId, $changeId);
    
        if (! $ret) {
            return new Tonic\Response(Tonic\Response::NOCONTENT);
        }
        return array(200, $ret);
    }
}


/**
 * Lists all of the branches (and their films) in which the user has a pending
 * change.
 *
 * @uri /branch/pending
 */
class BranchObjUserPendingBranches extends Resource {
    
    /**
     * @method GET
     * @authenticated
     */
    function pageBranches() {
        $userId = $this->container['user']['User_Id'];
        $gaUserId = $this->container['user']['Ga_User_Id'];
        
        // FIXME
    }
}


/**
 * Returns the user's version of the branch on which it was created.  It
 * is also used to merge the pending change, delete, create, and commit.
 *
 * @uri /branch/:branchid/pending
 */
class BranchObjUserPendingVersion extends Resource {
    /**
     * Returns the branch version that
     * the user's pending was branched against, and the (head - N, up to
     * the pending change number) number of changes that have happened on the
     * branch since the user branch.  It also returns the number of branch
     * changes that have happened since the pending change was created.
     *
     * If the user does not have an open change for the branch, then this
     * will return no content.
     *
     * @method GET
     * @authenticated
     */
    function fetchBranchVersion() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $changeCount = $this->loadGetInt("changes", FALSE) || 0;
        $this->validate();
        
        $userId = $this->container['user']['User_Id'];
        $gaUserId = $this->container['user']['Ga_User_Id'];
        
        
        // FIXME call into getBranchQuipChangesFromPending
    }
    
    
    /**
     * @method PUT
     * @csrf create_change
     */
    function createPendingChange() {
        // FIXME
    }
    
    
    /**
     * @method DELETE
     * @csrf delete_change
     */
    function deletePendingChange() {
        // FIXME
    }
    
    
    /**
     * @method UPDATE
     * @csrf update_change
     */
    function updateChange() {
        // FIXME
        
        // This is either a MERGE or COMMIT.
        // MERGE will move the user's branched-from change to the current
        // head, and COMMIT will add these changes into the submission
        // (which will implicitly move these revisions into a new change
        // with a top number).
    }
}


/**
 * Paging for the pending branch quips of the current user, and adding new
 * quips.
 *
 * @uri /branch/:branchid/pending/quip
 */
class BranchObjQuipsPending extends Resource {
    /**
     * Page in the pending and committed quips.  Each returned quip includes
     * a field to indicate whether it is pending or committed.  The whole
     * returned structure includes the version number requested.
     *
     * If the request is not authenticated, or if the user does not have an
     * open change, then this will return an error message.  This is because
     * there is no specific change number in the request, so the client side
     * can potentially get into a bad state during playback if this returned
     * the head revision.
     *
     * @method GET
     */
    function fetch() {
        // FIXME
        
        // pull from V_QUIP_USER_ALL
    }
    
    
    /**
     * Add a (1) new quip.
     *
     * @method PUT
     * @csrf create_quip
     */
    function create() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
                
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        
        // FIXME
    }
    
    
    /**
     * Performs a series of actions over many quips.  The JSon object is broken
     * down into different actions by way of the top-level key.  This allows
     * multiple simulated requests to the /branch/:branchid/pending/quip/:itemid
     * URL, without the need for lots of independent connections.
     *
     * If there are issues with the request, they are returned in the JSon
     * response (just like all errors), with one per request object, in a
     * similar structure to how it was requested.
     *
     * TODO should be investigated into put a maximum size to the request,
     * to eliminate a possible hog of resources in a single request.
     *
     * @method POST
     * @csrf mass_update_quip
     */
    function mass_updates() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
                
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        
        $data = $this->getRequestData();
        if (array_key_exists("create", $data) && is_array($data['create'])) {
            // FIXME
        }
        if (array_key_exists('delete', $data) && is_array($data['delete'])) {
            // FIXME
        }
        if (array_key_exists('update', $data) && is_array($data['update'])) {
            // FIXME
        }
        
    }
}


/**
 *
 * @uri /branch/:branchid/pending/quip/:itemid
 */
class BranchObjQuipItem extends Resource {
    /**
     * Retrieve the detailed information for this one specific quip.  If the
     * quip is not in the pending change for the user, then nothing is
     * returned (no content).
     *
     * @method GET
     */
    function fetch() {
        // FIXME
    }
    
    
    /**
     * Update the information on this pending quip.  If this is a quip in
     * the branch but not in the pending list, then it should be added to
     * the pending list as an update to the quip.
     *
     * This can potentially update the quip tags, so the tag access needs to
     * be checked separately.
     *
     * @method POST
     * @csrf update_quip
     */
    function update() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $quipId = $this->validateId($this->itemid, "itemId");
        $this->validate();
        
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        // FIXME
    }
    
    
    /**
     * Remove the quip.
     *
     * @method DELETE
     * @csrf delete_quip
     */
    function remove() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $quipId = $this->validateId($this->itemid, "itemId");
        $this->validate();
                
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        } else {
            throw new Tonic\UnauthorizedException();
        }
        // FIXME
    }
}


// ---------------------------------------------------------------------------

class Validation {
    
    public static function normalizeBranchName($name, Resource &$res) {
        // Strip out the leading and tailing spaces, and replace all double
        // white space with a single space.
        
        if (! $res->checkThat(!! $name && is_string($name), 'name')) {
            return null;
        }
        
        $ret = preg_replace('/\s+/', ' ', trim($name));
        $res->checkThat(strlen($ret) >= 1, 'name',
                    'must be at least 1 character long');
        
        return $ret;
    }
    
}

