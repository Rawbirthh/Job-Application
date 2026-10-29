<?php

namespace App\Exceptions;

use RuntimeException;

class LastAdministratorException extends RuntimeException
{
    public function __construct(string $message = 'At least one platform administrator must remain.')
    {
        parent::__construct($message);
    }
}
