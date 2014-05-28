<?php

namespace WebRiffs;

require_once(__DIR__.'/Resource.php');

use Tonic;


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
        
        $totalCount = FilmLayer::getFilmCount($db, $paging['filters']['name'],
            $paging['filters']['yearMin'], $paging['filters']['yearMax']);
        
        $result = FilmLayer::findFilms($db, $paging['sort_by'],
            $paging['sort_order'], $paging['row_count'],
            $paging['start_row'], $paging['filters']['name'],
            $paging['filters']['yearMin'], $paging['filters']['yearMax'])
        
        
        return new Tonic\Response(200, $stmt->fetchAll());
    }



    /**
     * @method POST
     * @security create
     */
    public function create() {
        $data = getRequestData();

        $db = getDB();

        // validate that the name/year do not already exist
        // This should be part of the SQL constraints.
        $stmt = $db->prepare('SELECT COUNT(*) FROM FILM WHERE Name = :Name AND Release_Year = :Release_Year');
        $stmt->execute($data);
        if ($stmt->fetchColumn() > 0) {
            throw new Tonic\ConditionException;
        }


        $stmt = $db->prepare('INSERT INTO FILM (Name, Release_Year, Imdb_Url, Wikipedia_Url, Created_On, Last_Updated_On) VALUES (:Name, :Release_Year, :Imdb_Url, :Wikipedia_Url, NOW(), NULL)');
        $stmt->execute($data);
        $filmId = $db->lastInsertId();
        $data['Film_Id'] = $filmId;


        // FIXME create initial version



        return new Tonic\Response(Tonic\Response::CREATED, $data);
    }
}



/**
 * FIXME include validation of input values
 *
 * @uri /film/:filmid
 */
class FilmObj extends Tonic\Resource {
    /**
     * @method GET
     */
    public function display() {
        $filmid = $this->filmid;
        $db = getDB();
        $stmt = $db->prepare('SELECT Film_Id, Name, Release_Year, Imdb_Url, Wikipedia_Url, Created_On, Last_Updated_On FROM FILM WHERE Film_Id = ?');
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(array($filmid));

        $row = $stmt->fetch();

        if (!$row) {
            throw new Tonic\NotFoundException;
        }

        return new Tonic\Response(200, $stmt->fetch());
    }



    /**
     * @method POST
     * @secure create
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
     */
    function remove() {
        $filmid = $this->filmid;
        $db = getDB();

        $stmt = $db->prepare('DELETE FROM FILM WHERE Film_Id = ?');
        $stmt->execute(array($filmid));

        return new Tonic\Response(Tonic\Response::NOCONTENT);
    }
}
