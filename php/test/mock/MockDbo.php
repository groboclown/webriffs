<?php


/**
 * Handles the checking of a mock DBO call, and constructing the mocked-up
 * PBO statement for use by the DBO layer.
 *
 * This acts as the "$db" in the calls to the DBO layer.  These should not
 * be shared across test methods.
 */
class MockDbo {
    private $testClass;
    
    private $expectedCalls = array();
    
    
    public function __construct($testClass) {
        $this->testClass = $testClass;
    }
    
    
    // ---------------------------------------------------------------------
    // Setup methods.  These setup the expectations for the order of calls,
    // and the return values for use in the tests.
    


    public function addExpectation($mock, $expectation) {
        $mock->RETURNS[] = $expectation;
    }
    
    
    
    // --------------------------------------------------------------------
    // Ensures that all the invocations that ran were done correctly.
    // Specifically called to check if there were any additional expected
    // calls that weren't performed.
    
    public function assertCompleted() {
        // FIXME
    }
    
    
    // -------------------------------------------------------------------
    // Simulation of the calls that the DBO layer makes to the $db object.
    
    public function lastInsertId() {
        // FIXME return the last inserted ID.
    }
    
    
    // -------------------------------------------------------------------
    // Calls made from the mocked-up DBO layer.
    
    public function generate($methodName, $methodArgs, $sql, $data) {
        // FIXME return a MockDboStatement
    }
}


/**
 * Internal class created by MockDbo to simulate the PBO Statement object.
 * It will perform validation that it is used correctly as per the invocation
 * by the
 */
class MockDboStatement {
    
    public function fetchColumn() {
        // FIXME return the first column, first value.
    }
    
    
    public function fetchAll() {
        // FIXME return all the values in an array of an associative array.
    }
    
    public function rowCount() {
        // FIXME return the number of rows fetched.
    }
    
    public function errorInfo() {
        // FIXME return the array(0, error_code or null, error_string or null)
    }
}


