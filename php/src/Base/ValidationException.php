<?php

namespace Base;

use Tonic;

/**
 * Exception to be thrown when user input isn't valid.  Each field that
 * fails validation needs to be put into the $problems hashmap.
 */
class ValidationException extends Tonic\Exception
{
    protected $code = 406;
    public $problems;
    protected $message = 'Validation of input data failed';


    public function __construct(array $problems)
    {
        parent::__construct();
        $this->problems =& $problems;
        if ($problems) {
            $this->message .= ': ' . print_r($problems, true);
        }
    }
}
