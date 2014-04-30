<?php

namespace WebRiffs;

/**
 * Parent secure resource class.  All classes that require user authentication
 * should inherit this rather than the Tonic\Resource class.
 */
class SecureResource extends Resource {
    function setup() {
        // FIXME perform authentication checking here
        $db = $this->getDB();

        if (!isset($_SERVER['cookie check here'])) {
            throw new Tonic\UnauthorizedException;
        }
    }
}

