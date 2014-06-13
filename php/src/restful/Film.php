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
     * @security create
     * @csrf create_film
     */
    public function create() {
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
        $filmId = $this->filmid;
        
        // FIXME convert filmId to an integer with correct error checking
        $filmId = intval($filmId);
        
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
     * @secure create
     * @csrf update_film
     */
    public function update() {
        $filmid = $this->filmid;
        
        // FIXME convert filmId to an integer with correct error checking
        $filmId = intval($filmId);
        
        $db = $this->getDB();

        $data = $this->request->data;
        $name = $data['Name'];
        $releaseYear = $data['Release_Year'];
        $imdbUrl = $data['Imdb_Url'];
        $wikiUrl = $data['Wikipedia_Url'];

        // FIXME validate data

        //$stmt = $db->prepare('UPDATE FILM SET Name = ?, Release_Year = ?, Imdb_Url = ?, Wikipedia_Url = ? WHERE Film_Id = ?');
        //$stmt->execute(array($name, $releaseYear, $imdbUrl, $wikiUrl, $filmid));

        return $this->display();
    }



    /**
     * @method DELETE
     * @secure delete
     * @csrf delete_film
     */
    function remove() {
        $filmid = $this->filmid;
        $db = $this->getDB();

        //$stmt = $db->prepare('DELETE FROM FILM WHERE Film_Id = ?');
        //$stmt->execute(array($filmid));

        return new Tonic\Response(Tonic\Response::NOCONTENT);
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
        $userId = null;
        if ($this->isUserAuthenticated()) {
            $userId = $this->container['user']['User_Id'];
        }
        
        $db = $this->getDB();
        
        $result = WebRiffs\FilmLayer::pageBranches($db, $userId, $this->filmid);
        
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
        $branchId = $this->branchid;
        $filmId = $this->filmid;

        // FIXME convert filmId to an integer with correct error checking
        $filmId = intval($filmId);
        $branchId = intval($branchId);
        
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
