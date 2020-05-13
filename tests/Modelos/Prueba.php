<?php
namespace Modelos;

use Asbd\Entidad;

class Prueba extends Entidad
{
    public $tabla = 'prueba';
    public $primaria = 'pruebaId';

    /**
     * @columna integer
     */
    public $pruebaId;
}