<?php

namespace Asbd\Excepciones;

use Exception;

class ErrorInterno extends Exception
{
    protected $code = 500;
    protected $message = 'Error interno: %s';

    public function __construct(string $detalleError)
    {
        $this->message = sprintf($this->message, $detalleError);
        parent::__construct($this->message, $this->code);
    }
}