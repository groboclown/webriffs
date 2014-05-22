<?php

namespace WebRiffs;

use PBO;
use Base;
use Tonic;

/**
 * Manages the business logic related to the films.
 */
class FilmLayer {
    /**
     * Creates the film, and adds a new, empty film version.
     *
     * @param userId int; must be taken from the user session, so we don't
     *      check its value.
     */
    public static function createFilm($db, $userId, $name, $year,
            $imdbUrl, $wikipediaUrl) {



    }


    /**
     * Returns all the films visible by the user.  It includes optional
     * filters.
     */
    public static function findFilms($db, $userId, $sortBy, $rowCount,
            $startRow = 0, $nameFilter = null, $minYear = 0, $maxYear = 9999) {

    }


    //public static
}
