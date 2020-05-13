<?php

namespace Asbd\Excepciones;

use Exception;

class ConfigInvalida extends Exception
{
    protected $code = 102;
    protected $message = 'Configuracion invalida de la BD';
}