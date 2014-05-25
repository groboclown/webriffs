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
     * @param int userId must be taken from the user session, so we don't
     *      check its value.
     */
    public static function createFilm($db, $userId, $name, $year,
            $imdbUrl, $wikipediaUrl) {

        // FIXME

    }

    
    
    public static $FILM_SORT_COLUMNS = array(
        "name" => "Name",
        "year" => "Release_Year",
        "created" => "Created_On",
        "updated" => "Last_Updated_On"
    );

    /**
     * Returns all the films.  It includes optional filters.
     */
    public static function findFilms($db, $sortBy, $sortOrder,
            $rowCount, $startRow = 0, $nameFilter = null, $minYear = 0,
            $maxYear = 9999) {
        $order = false;
        if (!! $sortBy) {
            if (! array_key_exists($sortBy, FilmLayer::$FILM_SORT_COLUMNS)) {
                throw new Base\ValidationException(array(
                                "sortBy" => "invalid column sort"
                ));
            }
            $order = FilmLayer::$FILM_SORT_COLUMNS[$sortBy];
            if ($sortOrder == 1) {
                $order .= " ASC";
            } elseif ($sortOrder == 2) {
                $order .= " DESC";
            }
        }
        if (! is_integer($rowCount) || ! is_integer($startRow) ||
                $rowCount <= 0 || $startRow < 0) {
            throw new Base\ValidationException(array(
                "rows" => "invalid row selection"
            ));
        }
        if (! is_integer($minYear) || ! is_integer($maxYear)) {
            throw new Base\ValidationException(array(
                            "years" => "invalid year range"
            ));
        }
        if ($minYear > $maxYear) {
            $x = $minYear;
            $minYear = $maxYear;
            $maxYear = $x;
        }
        
        $endRow = $startRow + $rowCount;
        
        $wheres = array(
            new Film\Film_Film_RestrictYear($minYear, $maxYear)
        );
        if ($nameFilter !== null) {
            $wheres[] = new Film\Film_FuzzyName('%'.$nameFilter.'%');
        }
        
        
        $data = Film::$INSTANCE->readAll($db, $wheres, $order, $startRow,
                $endRow);
        FilmLayer::checkError($data, new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem finding the films'
            )));
        return $data['result'];
    }
    
    
    public static function getFilmCount($db, $nameFilter = null, $minYear = 0,
            $maxYear = 9999) {
        if (! is_integer($rowCount) || ! is_integer($startRow) ||
                $rowCount <= 0 || $startRow < 0) {
            throw new Base\ValidationException(array(
                "rows" => "invalid row selection"
            ));
        }
        if (! is_integer($minYear) || ! is_integer($maxYear)) {
            throw new Base\ValidationException(array(
                            "years" => "invalid year range"
            ));
        }
        if ($minYear > $maxYear) {
            $x = $minYear;
            $minYear = $maxYear;
            $maxYear = $x;
        }
        
        $endRow = $startRow + $rowCount;
        
        $wheres = array(
            new Film\Film_Film_RestrictYear($minYear, $maxYear)
        );
        if ($nameFilter !== null) {
            $wheres[] = new Film\Film_FuzzyName('%'.$nameFilter.'%');
        }
        
        $data = Film::$INSTANCE->countAll($db, $wheres);
        FilmLayer::checkError($data, new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem finding the films'
            )));
        return $data['result'];
    }
    
    
    /**
     * Returns all the films with branches that are visible by the user.
     */
    public static function findFilmsAndBranches($db, $userId, $sortBy, $rowCount) {

        // FIXME
        
    }
    
    
    
    
    // ----------------------------------------------------------------------


    private static function checkError($returned, $exception) {
        if ($returned["haserror"]) {
            $backtrace = 'Database access error ('.
                    $returned["errorcode"].' '.$returned["error"].'):';
            foreach (debug_backtrace() as $stack) {
                $backtrace .= '\n    '.$stack['function'].'('.
                        implode(', ', $stack['args']).') ['.
                        $stack['file'].' @ '.$stack['line'].']';
            }
            error_log($backtrace);
    
            // TODO make the error messages language agnostic.
    
            // can have special logic for the $errorSource->errnos
            // error codes, to have friendlier messages.
    
            // 1062: already in use.
    
            throw $exception;
        }
    }
}
