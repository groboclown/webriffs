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
     *
     * @param string $name
     * @param bool $required
     * @return string
     */
    protected function loadRequestString($name, $required = TRUE) {
        // inline of $this->getRequestData()
        return $this->loadArrayString($name, $this->request->data, $required);
    }
    
    
    /**
     *
     * @param string $name
     * @param string $required
     * @return int
     */
    protected function loadRequestInt($name, $required = TRUE) {
        // inline of $this->getRequestData()
        return $this->loadArrayInt($name, $this->request->data, $required);
    }


    /**
     *
     * @param string $name
     * @param string $required
     * @return int
     */
    protected function loadRequestId($name, $required = TRUE) {
        // inline of $this->getRequestData()
        return $this->loadArrayId($name, $this->request->data, $required);
    }
    

    /**
     *
     * @param string $name
     * @param bool $required
     * @return string
     */
    protected function loadGetString($name, $required = TRUE) {
        return $this->loadArrayString($name, $_GET, $required);
    }
    
    
    /**
     *
     * @param string $name
     * @param string $required
     * @return int
     */
    protected function loadGetInt($name, $required = TRUE) {
        return $this->loadArrayInt($name, $_GET, $required);
    }


    /**
     *
     * @param string $name
     * @param string $required
     * @return int
     */
    protected function loadGetId($name, $required = TRUE) {
        return $this->loadArrayId($name, $_GET, $required);
    }
    

    /**
     *
     * @param string $name
     * @param bool $required
     * @return string
     */
    protected function loadArrayString($name, &$data, $required) {
        if (array_key_exists($name, $data)) {
            $value = $data[$name];
            if (is_string($value)) {
                return $value;
            }
            $this->addValidationError($name, "not string");
            return NULL;
        }
        if ($required) {
            $this->addValidationError($name, "no specified value");
        }
        return NULL;
    }


    /**
     *
     * @param string $name
     * @param string $required
     * @return int
     */
    protected function loadArrayInt($name, &$data, $required) {
        if (array_key_exists($name, $data)) {
            $value = $data[$name];
            if (is_numeric($value) &&
                    $value*1 == (int)($value*1)) {
                return intval($value);
            }
            $this->addValidationError($name, "not integer");
            return NULL;
        }
        if ($required) {
            $this->addValidationError($name, "no specified value");
        }
        return NULL;
    }
    
    
    /**
     *
     * @param string $name
     * @param string $required
     * @return int
     */
    protected function loadArrayId($name, &$data, $required) {
        return $this->loadArrayInt($name, $data, $required);
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
        if ($id !== null && is_numeric($id) &&
                    $id*1 == (int)($id*1)) {
            $id = intval($id);
        }
        
        if ($id === null || !is_int($id)) {
            $this->addValidationError($name, "invalid id value");
            return NULL;
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
