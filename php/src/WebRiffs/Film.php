<?php

namespace WebRiffs;

use Tonic;


class FilmResource extends Resource {
    protected function getRequestData() {
        $data = $this->request->data;
        return array(
            'Name' => validateFilmName($data['Name']),
            'Release_Year' => validateReleaseYear($data['Release_Year']),
            'Imdb_Url' => validateUrl($data['Imdb_Url']),
            'Wikipedia_Url' => validateUrl($data['Wikipedia_Url']),
        );
    }


    private function validateFilmName($name) {
        if ($name == null || !is_string($name)) {
            throw new Tonic\NotAcceptableException;
        }

        $name = trim($name);

        if (strlen($name) <= 0) {
            throw new Tonic\NotAcceptableException;
        }

        return $name;
    }

    private function validateReleaseYear($year) {
        if ($year == null || !is_int($year)) {
            throw new Tonic\NotAcceptableException;
        }
        if ($year < 1830 || year > 3000) {
            throw new Tonic\NotAcceptableException;
        }
        return $year;
    }

    /**
     * Validates that the given url is a url path.
     */
    private function validateUrl($url) {
        if (ereg(":?&\\s", $url)) {
            throw new Tonic\NotAcceptableException;
        }
    }


}



/**
 * FIXME include validation of input values
 *
 * @uri /film
 */
class FilmCollection extends FilmResource {
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
        $data = getRequestData();

        $db = getDB();

        // validate that the name/year do not already exist
        $stmt = $db->('SELECT COUNT(*) FROM FILM WHERE Name = :Name AND Release_Year = :Release_Year');
        $stmt->execute($data);
        if ($stmt->fetchColumn() > 0) {
            throw new Tonic\ConditionException;
        }


        $stmt = $db->('INSERT INTO FILM (Name, Release_Year, Imdb_Url, Wikipedia_Url, Created_On, Last_Updated_On) VALUES (:Name, :Release_Year, :Imdb_Url, :Wikipedia_Url, NOW(), NULL)');
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
