<?php

namespace WebRiffs;

use PBO;
use Base;

class DataAccess {


    private static function checkError($errorSource, $exception) {
        if (sizeof($errorSource->errors) > 0) {
            $backtrace = 'Database access error (['.
                implode('], [', $errorSource->errors).']):';
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
