<?php

namespace App\Exceptions;

use Exception;

class SelfFollowException extends Exception
{
    public function __construct()
    {
        parent::__construct('Você não pode seguir a si mesmo.');
    }
}
