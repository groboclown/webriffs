<?php


namespace Base;

use Tonic;

/**
 * Base resource extension of the tonic resource, to give the common API to
 * all resources.
 */
class Resource extends Tonic\Resource
{
    private $problems = array();
    
    
    function checkThat($valid, $name, $problem = null) {
        if (! $valid) {
            if ($problem == null) {
                $problem = 'incorrect value';
            }
            $this->addValidationError($name, $problem);
        }
        return $valid;
    }
    
    
    protected function assertThat($valid, $name, $problem = null) {
        if (! $this->checkThat($valid, $name, $problem)) {
            $this->validate();
        }
    }
    

    /**
     * Adds a validation problem, that will be reported on the next call to
     * validate().
     */
    protected function addValidationError($name, $problem) {
        $this->problems[$name] = $problem;
    }


    /**
     * If there were any validation errors gatherd in addValidationError,
     * this will throw a ValidationException
     */
    protected function validate() {
        if (sizeof($this->problems) > 0) {
            throw new ValidationException($this->problems);
        }
    }


    /**
     * Validate that the given variable is a non-null number.
     *
     * @return int
     */
    protected function validateId($id, $name) {
        // if the id is a string, verify that it's the correct
        // format, and convert it.
        if ($id !== null && is_numeric($id)) {
            $id = intval($id);
        }
        
        if ($id === null || !is_int($id)) {
            $this->addValidationError($name, "invalid id value");
            return null;
        }
        return $id;
    }


    /**
     * Retrieve the PBO database object.
     */
    protected function getDB() {
        if ($this->container['dataStore']) {
            return $this->container['dataStore'];
        }
        throw new Tonic\NotFoundException;
    }
    
    
    protected function getRequestData() {
        return $this->request->data;
    }
    
    
}
