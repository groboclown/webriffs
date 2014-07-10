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
 * FIXME include validation of input values
 *
 * @uri /film
 */
class FilmCollection extends Resource {
    
    
    /**
     * "GET" returns the records, and includes paging.  All films are public,
     * while the individual branches are not.
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
        $this->secure(WebRiffs\Access::$FILM_CREATE, $PRIVILEGE_AUTHORIZED);
        
        $data = $this->getRequestData();

        $db = $this->getDB();
        
        $idList = WebRiffs\FilmLayer::createFilm($db, $this->container['user'],
            $data->name, $data->year,
            WebRiffs\FilmLayer::$DEFAULT_TEMPLATE_ACCESS_NAME);

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
        
        $this->assertThat(array_key_exists("Name", $_GET), 'Name');
        $this->assertThat(array_key_exists("Release_Year", $_GET) &&
                is_numeric($_GET['Release_Year']),
                'Release_Year');
        $this->validate();
        
        $name = $_GET['Name'];
        $year = intval($_GET['Release_Year']);
        
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
        $this->secure(WebRiffs\Access::$FILM_MODIFICATION, $PRIVILEGE_AUTHORIZED);
        
        $filmId = $this->validateId($this->filmid, "filmId");
        $data = $this->getRequestData();
        $this->checkThat(!! $data->{'Name'} && is_string($data->{'Name'}),
            'Name', "Name must be specified as a string");
        $this->checkthat( !! $data->{'Release_Year'} &&
                is_numeric($data->{'Release_Year'}),
            'Release_Year', "Release_Year must be specified as a number");
        $this->validate();
        $name = $data->{'Name'};
        $this->assertThat($releaseYear >= 1800 && $releaseYear <= 9999,
            'Release_Year', "Release_Year must be within the bounds [1800, 9999]");
        $releaseYear = intval($data->{'Release_Year'});
        
        $db = $this->getDB();
        WebRiffs\FilmLayer::updateFilm($db, $filmId, $name, $releaseYear);
        
        return $this->display();
    }



    /**
     * @method DELETE
     * @csrf delete_film
     */
    function remove() {
        $this->secure(WebRiffs\Access::$FILM_DELETE, $PRIVILEGE_AUTHORIZED);
        
        $filmId = $this->validateId($this->filmid, "filmId");
        $this->validate();
        
        $db = $this->getDB();

        //$stmt = $db->prepare('DELETE FROM FILM WHERE Film_Id = ?');
        //$stmt->execute(array($filmid));

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
        $data = $this->getRequestData();
        if (! $data->{'Uri'} || ! is_string($data->{'Uri'})) {
            $this->addValidationError('Uri', 'must specify the uri suffix');
        } else {
            $uri = $data->{'Uri'};
        }
        
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
        
        $result = WebRiffs\FilmLayer::pageBranches($db, $userId, $filmId);
        
        return array(200, $result);
    }
    
    
    /**
     * @method PUT
     * @csrf create_branch
     */
    function create() {
        $filmId = $this->validateId($this->filmid, "filmId");
            
        $data = $this->getRequestData();
        $this->assertThat(
            !! $data->{'name'} && is_string($data->{'name'}),
            'name');
        $branchName = Validation::normalizeBranchName($data->{'name'}, $this);
        $desc = '';
        if (!! $data->{'description'}) {
            $this->assertThat(is_string($data->{'description'}), 'description');
            $desc = $data->{'description'};
        }
        $this->validate();
        
        // Any user can create a new branch.
        $this->secure(WebRiffs\Access::$FILM_BRANCH,
                WebRiffs\Access::$PRIVILEGE_USER);
        $userInfo = $this->container['user'];
        
        $db = $this->getDB();
        
        $result = WebRiffs\FilmLayer::createBranch($db, $filmId,
            $userInfo['User_Id'], $userInfo['Ga_User_Id'],
            $branchName, $description, null);
        
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
 * @uri /film/:filmid/branchexists
 */
class FilmObjBranchName extends Resource {
    /**
     * @method GET
     */
    public function fetch() {
        $filmId = $this->validateId($this->filmid, "filmId");
        $this->assertThat(array_key_exists("Name", $_GET), 'Name');
        $this->validate();

        $name = $_GET['Name'];

        return array(200, array(
            'exists' => WebRiffs\FilmLayer::doesBranchExist(
                    $this->getDB(), $filmId, $name)
        ));
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
        
        $result = WebRiffs\FilmLayer::pageBranchVersions($this->getDB(),
            $userId, $branchId, null);
        
        return array(200, $result);
    }
    
    
    /**
     * @method POST
     */
    function update() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $this->validate();
        
        $userId = null;
        $gaUserId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
            $gaUserId = $this->container['user']['Ga_User_Id'];
        }
        
        $data = $this->getRequestData();
        $branchName = Validation::normalizeBranchName($data->{'name'}, $this);
        $desc = '';
        if (!! $data->{'description'}) {
            $this->assertThat(is_string($data->{'description'}), 'description');
            $desc = $data->{'description'};
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
        
        WebRiffs\FilmLayer::updateBranchHeader($this->getDB(), $branchId,
            $userId, $gaUserId, $branchName, $desc, $tags);
        
        return new Tonic\Response(Tonic\Response::ACCEPTED);
    }
    
    
    /**
     * Calls the post method.  Conceptually, the user is updating a branch,
     * but it can also be viewed as adding a new change to the branch.  So
     * we'll allow both.
     *
     * @method PUT
     */
    function create() {
        return $this->update();
    }
}


/**
 * All committed changes for the branch.
 *
 * @uri /branch/:branchid/version/:changeid
 */
class BranchObjChangeVersion extends Resource {
    
    /**
     * Get the details for the branch.  If the branch is not visible by the
     * user, then it rturns a "no content" response.
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
        $ret = WebRiffs\FilmLayer::getBranchDetails($this->getDB(),
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
        $ret = WebRiffs\FilmLayer::pageCommittedQuips($this->getDB(),
                $userId, $branchId, $changeId);
    
        if (! $ret) {
            return new Tonic\Response(Tonic\Response::NOCONTENT);
        }
        return array(200, $ret);
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
     * Page in the pending quips.  These should be merged with the head
     * quips on the client side.
     *
     * @method GET
     */
    function fetch() {
        // FIXME
    }
    
    
    /**
     * @method PUT
     */
    function create() {
        // FIXME
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
     */
    function update() {
        // FIXME
    }
    
    
    /**
     * Remove the quip.
     *
     * @method DELETE
     */
    function remove() {
        // FIXME
    }
}


// ---------------------------------------------------------------------------

class Validation {
    
    public static function normalizeBranchName($name, Resource &$res) {
        // Strip out the leading and tailing spaces, and replace all double
        // white space with a single space.
        
        if (! $res->checkThat(!! $data->{'name'} && is_string($data->{'name'}),
                'name')) {
            return null;
        }
        
        $ret = preg_replace('/\s+/', ' ', trim($name));
        $res->checkThat(strlen($ret) >= 1, 'name',
                    'must be at least 1 character long');
        
        return $ret;
    }
    
}

