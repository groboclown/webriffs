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
        $this->checkThat(is_string($linkTypeName), "linktypename");
        
        $uri = $this->loadRequestString("Uri");
        $isPlaybackMedia = $this->loadRequestString("Is_Playback_Media");
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

        $branchName = Validation::normalizeBranchName(
                $this->loadRequestString("Name"), $this);
        $desc = $this->loadRequestString("Description", FALSE) || "";
        $this->validate();
        
        $userInfo = $this->container['user'];
        
        $db = $this->getDB();
        
        $result = WebRiffs\BranchLayer::createBranch($db, $filmId,
            $userInfo['User_Id'], $userInfo['Ga_User_Id'],
            $branchName, $desc, null);
        
        $data = array(
            'Branch_Id' => $result
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
 * FIXME this could be like BitBucket or GitHub, where each user can have
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

