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
                $f = "?";
                if (array_key_exists('file', $stack)) {
                    $f = $stack['file'];
                    if (array_key_exists('line', $stack)) {
                        $f = $f . ' @ ' . $stack['line'];
                    }
                }
                
                $args = array();
                foreach ($stack['args'] as $arg) {
                    $args[] = print_r($stack['args'], TRUE);
                }
                $backtrace .= "\n    " . $stack['function'] .
                     '(' . implode(', ', $args) .
                     ') [' . $f . ']';
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
