<?php

namespace Asbd\Excepciones;

use Exception;

class NoHayModo extends Exception
{
    protected $code = 100;
    protected $message = 'No hay modo definido de operacion';
}