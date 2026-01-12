<?php

namespace App\Exceptions;

class ApplicationException extends \Exception
{
    const RANKING_PERSISTENCE_TIMEOUT = 1001;
    const DAILY_UPDATE_EXCEPTION_ERROR_CODE = 2001;
}