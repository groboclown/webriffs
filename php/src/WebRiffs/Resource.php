<?php


namespace WebRiffs;

use Tonic;

class Resource extends Tonic\Resource
{
    protected function getDB() {
        try {
            $conn = new PDO($this->container['db_config']['dsn'],
                $this->container['db_config']['username'],
                $this->container['db_config']['password']);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch (Exception $e) {
            throw new Tonic\NotFoundException;
        }
    }



    protected function fetchSingleRow($statement) {
        $row = $statement->fetch();
        if (!$row) {
            throw new Tonic\NotFoundException;
        }
        $second = $statement->fetch();
        if (!$second) {
            return $row;
        }
        throw new Tonic\ConditionException;
    }


    function formatted() {
        // TODO extend this to check the requested type, and convert to
        // XML or JSON as needed.


        $this->before(function ($request) {
            if ($request->contentType == "application/json") {
                $request->data = json_decode($request->data);
            }
        });
        $this->after(function ($response) {
            $response->contentType = "application/json";
            $response->body = json_encode($response->body);
        });
    }

}
