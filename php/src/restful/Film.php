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
            'film_id' => $idList[1],
            'branch_id' => $idList[2],
            'change_id' => $idList[3]
        );

        return new Tonic\Response(Tonic\Response::CREATED, $data);
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
        $data = $this->request->data;
        // FIXME validate that the data exists and is correct
        $name = $data['Name'];
        $releaseYear = $data['Release_Year'];
        $this->validate();
        
        $db = $this->getDB();

        //$stmt = $db->prepare('UPDATE FILM SET Name = ?, Release_Year = ?, Imdb_Url = ?, Wikipedia_Url = ? WHERE Film_Id = ?');
        //$stmt->execute(array($name, $releaseYear, $imdbUrl, $wikiUrl, $filmid));

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
    
    
    // TODO delete - delete a branch.
    // TODO put - create a branch.
    // TODO post - update a branch.
}


/**
 * This needs to include the user's pending changes in the results.
 *
 * @uri /film/:filmid/branch/:branchid
 *
 */
class FilmObjBranchObj extends Resource {
    
    /**
     * Get the details for the branch.  If the branch is not visible by the
     * user, then it
     *
     * @method GET
     */
    function fetch() {
        // FIXME
        
        return new Tonic\Response(Tonic\Response::NOCONTENT);

    }
    
}



/**
 * This needs to include the user's pending changes in the results.
 *
 * @uri /film/:filmid/branch/:branchid/tag
 */
class FilmObjBranchObjTag extends Resource {
    
    /**
     * @method GET
     */
    function fetch() {
        $branchId = $this->validateId($this->branchid, "branchId");
        $filmId = $this->validateId($this->filmid, "filmId");
        $this->validate();

        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        
        $db = $this->getDB();
        
        $result = WebRiffs\FilmLayer::getTagsForBranch($db, $userId, $filmId,
            $branchId);
        return array(200, array('tags' => $result));
    }
    
}
