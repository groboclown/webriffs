<?php

namespace Base;

use PBO;
use Tonic;


/**
 * Common API for all the data access classes.
 */
class BaseDataAccess {

    // ----------------------------------------------------------------------
    public static function checkError($returned, $exception) {
        if ($returned["haserror"]) {
            $backtrace = 'Database access error (' . $returned["errorcode"] . ' ' .
                 $returned["error"] . '):';
            foreach (debug_backtrace() as $stack) {
                $args = array();
                foreach ($stack['args'] as $arg) {
                    $args[] = print_r($stack['args'], TRUE);
                }
                $backtrace .= "\n    " . $stack['function'] .
                     '(' . implode(', ', $args) .
                     ') [' . $stack['file'] .
                     ' @ ' . $stack['line'] . ']';
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
