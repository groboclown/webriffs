<?php

namespace WebRiffs;

use Tonic;

/**
 * FIXME include validation of input values
 *
 * @uri /film
 */
class FilmCollection extends Tonic\Resource {
    /**
     * @method GET
     */
    public function list() {
        $db = getDB();
        $stmt = $db->('SELECT Film_Id, Name, Release_Year, Imdb_Url, Wikipedia_Url, Created_On, Last_Updated_On FROM FILM');
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute();
        return new Tonic\Response(200, $stmt->fetchAll());
    }



    /**
     * @method POST
     * @security create
     */
    public function create() {
        $data = $this->request->data;
        $name = $data['Name'];
        $releaseYear = $data['Release_Year'];
        $imdbUrl = $data['Imdb_Url'];
        $wikiUrl = $data['Wikipedia_Url'];

        // FIXME validate input

        $db = getDB();

        // FIXME validate that the name/year do not already exist

        $stmt = $db->('INSERT INTO FILM (Name, Release_Year, Imdb_Url, Wikipedia_Url, Created_On, Last_Updated_On) VALUES (?, ?, ?, ?, NOW(), NULL)');
        $stmt->execute(array($name, $releaseYear, $imdbUrl, $wikiUrl));
        $filmId = $db->lastInsertId();
        return new Tonic\Response(Tonic\Response::CREATED, array(
            'Film_Id' => $filmId,
            'Name' => $name,
            'Release_Year' => $releaseYear,
            'Imdb_Url' => $imdbUrl,
            'Wikipedia_Url' => $wikiUrl
        ));
    }
}



/**
 * FIXME include validation of input values
 *
 * @uri /film/:filmid
 */
class Film extends Tonic\Resource {
    /**
     * @method GET
     */
    public function display() {
        $filmid = $this->filmid;
        $db = getDB();
        $stmt = $db->('SELECT Film_Id, Name, Release_Year, Imdb_Url, Wikipedia_Url, Created_On, Last_Updated_On FROM FILM WHERE Film_Id = ?');
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute(array($filmid));

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

        $stmt = $db->('UPDATE FILM SET Name = ?, Release_Year = ?, Imdb_Url = ?, Wikipedia_Url = ? WHERE Film_Id = ?');
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

        $stmt = $db->('DELETE FROM FILM WHERE Film_Id = ?');
        $stmt->execute(array($filmid));

        return new Tonic\Response(Tonic\Response::NOCONTENT);
    }
}
