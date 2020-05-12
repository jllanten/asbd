<?php

namespace Asbd\Excepciones;

use Exception;

class ModoInvalido extends Exception
{
    protected $code = 101;
    protected $message = 'Modo invalido: debe ser uno de single|multi';
}