<?php

namespace Base;

/**
 * Defines a single search filter that the user can pass to the server.
 */
abstract class SearchFilter {
    public $name; // string
    public $defaultValue; // mixed
    
    /**
     *
     * @param string $name
     * @param mixed $defaultValue
     */
    public function __construct($name, $defaultValue = null) {
        //parent::__construct();
        $this->name = $name;
        $this->defaultValue = $defaultValue;
    }
    
    
    /**
     * @return mixed the parsed value for the filter.
     */
    public abstract function parseValue($v);
}


class SearchFilterInt extends SearchFilter {
    private $minValue; // int
    private $maxValue; // int
    
    // FIXME arguemnt type string, int, int, int
    public function __construct($name, $defaultValue = null,
            $minValue = null, $maxValue = null) {
        parent::__construct($name, $defaultValue);
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
    }
    
    
    public function parseValue($v) {
        if (is_numeric($v) &&
                ($v*1 == (int) ($v*1))) {
            $x = (int) ($v * 1);
        } else {
            return $this->defaultValue;
        }
        if ($x !== null && $x < $this->minValue) {
            $x = $this->minValue;
        }
        if ($x !== null && $x > $this->maxValue) {
            $x = $this->maxValue;
        }
        return $x;
    }
}



class SearchFilterString extends SearchFilter {
    // FIXME string, string
    public function __construct($name, $defaultValue) {
        parent::__construct($name, $defaultValue);
    }
    
    public function parseValue($v) {
        return (string) $v;
    }
}


