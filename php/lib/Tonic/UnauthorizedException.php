<?php

namespace Tonic;

class UnauthorizedException extends Exception
{
    // Groboclown: Note that we don't want to raise an actual 401 error,
    // because that will trigger a browser to pop up the authentication
    // dialog, when this will most likely occur during a back-end request.
    // Instead, we'll give a pre-conditioned failed response.
    protected $code = 412;
    protected $message = 'The request requires user authentication';
}
