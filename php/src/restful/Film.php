<?php

namespace WebRiffsRest;

require_once(__DIR__.'/Resource.php');

use Tonic;
use WebRiffs;


/**
 * FIXME include validation of input values
 *
 * @uri /film
 */
class FilmCollection extends Resource {
    public static $FILM_FILTERS = array("name", "yearMin", "yearMax");
    
    
    /**
     * "GET" returns the records, and includes paging.  All films are public,
     * while the individual branches are not.
     *
     * @method GET
     */
    public function fetch() {
        $paging = $this->getPageRequest(FilmCollection::$FILM_FILTERS,
            FilmLayer::$DEFAULT_SORT_COLUMN);
        
        $db = $this->getDB();
        
        $result = FilmLayer::pageFilms($db);
        
        return array(200, $result);
    }



    /**
     * @method PUT
     * @security create
     * @csrf create_film
     */
    public function create() {
        $data = getRequestData();

        $db = getDB();
        
        $idList = FilmLayer::createFilm($db, $this->container['user'],
            $data['name'], $data['year']);

        $data = array(
            'film_id' => $data[1],
            'branch_id' => $data[2],
            'change_id' => $data[3]
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
        $filmid = $this->filmid;
        $db = getDB();
        
        
        
        if (!$row) {
            throw new Tonic\NotFoundException;
        }

        return new Tonic\Response(200, $stmt->fetch());
    }



    /**
     * @method POST
     * @secure create
     * @csrf update_film
     */
    public function update() {
        $filmid = $this->filmid;
        $db = getDB();

        $data = $this->request->data;
        $name = $data['Name'];
        $releaseYear = $data['Release_Year'];
        $imdbUrl = $data['Imdb_Url'];
        $wikiUrl = $data['Wikipedia_Url'];

        // FIXME validate data

        $stmt = $db->prepare('UPDATE FILM SET Name = ?, Release_Year = ?, Imdb_Url = ?, Wikipedia_Url = ? WHERE Film_Id = ?');
        $stmt->execute(array($name, $releaseYear, $imdbUrl, $wikiUrl, $filmid));

        return $this->display();
    }



    /**
     * @method DELETE
     * @secure delete
     * @csrf delete_film
     */
    function remove() {
        $filmid = $this->filmid;
        $db = getDB();

        $stmt = $db->prepare('DELETE FROM FILM WHERE Film_Id = ?');
        $stmt->execute(array($filmid));

        return new Tonic\Response(Tonic\Response::NOCONTENT);
    }
    
    
    /**
     * @method PUT
     * @secure branch
     * @csrf branch_film
     */
    function createBranch() {
        // FIXME
    }
}
