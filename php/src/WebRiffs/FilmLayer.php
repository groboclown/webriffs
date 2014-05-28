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

    
    
    public static $FILM_SORT_COLUMNS;
    
    public static $DEFAULT_SORT_COLUMN = "name";
    
    public static $MIN_YEAR_SEARCH_FILTER;
    public static $MAX_YEAR_SEARCH_FILTER;
    
    public static $NAME_SEARCH_FILTER;
    
    public static $FILTERS;
    
    
    /**
     * Returns all the films.  It includes optional filters.
     * The Paging object should be trusted, as it performs all the input
     * validation.
     *
     * @param PBO $db
     * @param Base\PageRequest $paging
     * @return array a page response json array.
     */
    public static function pageFilms($db, Base\PageRequest $paging) {
        $wheres = array(
            new Film\Film_Film_RestrictYear(
                $paging->filters[FilmLayers::$MIN_YEAR_SEARCH_FILTER->name],
                $paging->filters[FilmLayers::$MAX_YEAR_SEARCH_FILTER->name])
        );
        if ($paging->filters[FilmLayers::$NAME_SEARCH_FILTER->name] !== null) {
            $wheres[] = new Film\Film_FuzzyName('%'.
                    $paging->filters[FilmLayers::$NAME_SEARCH_FILTER->name].
                    '%');
        }
        
        
        $data = Film::$INSTANCE->readAll($db, $wheres, $order, $startRow,
                $endRow);
        FilmLayer::checkError($data, new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem finding the films'
            )));
        $rows = $data['result'];
        
        $data = Film::$INSTANCE->countAll($db, $wheres);
        FilmLayer::checkError($data, new Base\ValidationException(array(
                'unknown' => 'there was an unknown problem counting the films'
            )));
        $count = $data['result'];
        
        return Base\PageResponse::createPageResponse($paging, $count, $rows);
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
FilmLayer::$FILM_SORT_COLUMNS = array(
                "name" => "Name",
                "year" => "Release_Year",
                "created" => "Created_On",
                "updated" => "Last_Updated_On"
);

FilmLayer::$DEFAULT_SORT_COLUMN = "name";

FilmLayer::$MIN_YEAR_SEARCH_FILTER = new Base\SearchFilterInt("yearMin",
        0, 0, 9999);
FilmLayer::$MAX_YEAR_SEARCH_FILTER = new Base\SearchFilterInt("yearMax",
        9999, 0, 9999);

FilmLayer::$NAME_SEARCH_FILTER = new Base\SearchFilterString("name",
        null);

FilmLayer::$FILTERS = array(
                FilmLayers::$MIN_YEAR_SEARCH_FILTER,
                FilmLayers::$MAX_YEAR_SEARCH_FILTER,
                FilmLayers::$NAME_SEARCH_FILTER
);
