<?php

namespace App\Exceptions;

/**
 * Thrown when a requested resource is permanently gone (HTTP 410).
 * Controllers catch this and respond with HTTP 410.
 */
class GoneException extends \Exception
{
}
