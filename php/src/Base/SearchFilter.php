<?php

namespace Base;

/**
 * Defines a single search filter that the user can pass to the server.
 */
public class SearchFilter {
    public final $name; // string
    public final $defaultValue; // mixed
    
    public function __construct(string $name, mixed $defaultValue = null) {
        //parent::__construct();
        $this->name = $name;
        $this->defaultValue = $defaultValue;
    }
    
    
    /**
     * @return mixed the parsed value for the filter.
     */
    public abstract function parseValue($v) {
        throw new \Exception("not implemented");
    }
}


public class SearchFilterInt extends SearchFilter {
    private final int $minValue;
    private final int $maxValue;
    
    public function __construct(string $name, int $defaultValue = null,
            int $minValue = null, int $maxValue = null) {
        parent::__construct($name, $defaultValue);
        $this->minValue = $minValue;
        $this->maxValue = $maxValue;
    }
    
    
    public function parseValue($v) {
        if (is_numeric($v) &&
                ($v*1 == (int) ($v*1))) {
            $x = (int) ($v * 1);
        } else {
            $x = $this->defaultValue;
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



public class SearchFilterString extends SearchFilter {
    public function __construct(string $name, string $defaultValue) {
        parent::__construct($name, $defaultValue);
    }
    
    public function parseValue($v) {
        return (string) $v;
    }
}


