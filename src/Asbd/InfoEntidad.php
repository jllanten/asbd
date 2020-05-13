<?php

namespace Asbd;

/**
 * Class InfoEntidad
 * Conserva la estructura de una entidad - refiriendose a los nombres de columnas segun las anotaciones
 * @package Asbd
 */
class InfoEntidad
{
    /** @var array */
    private $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Obtiene la propiedad que esta relacionada con una columna:
     * Esto es creado en CacheEntidad y contiene la informacion de las anotaciones
     * @param string $columna
     * @return mixed
     */
    public function getPropiedadDeColumna(string $columna)
    {
       $encontrado = null;
       if (empty($this->data) === false) {
           $encontrado = array_filter($this->data, function ($value, $key) use ($columna) {
               return $value['columna'] === $columna;
           }, ARRAY_FILTER_USE_BOTH);
       }

       if (empty($encontrado) === false) {
           $encontrado = reset($encontrado)['propiedad'];
       }

       return $encontrado;
    }

    /**
     * Obtiene la columna a utilizar para una propiedad dada
     * @param string $propiedad
     * @return mixed
     */
    public function getColumnaDePropiedad(string $propiedad)
    {
        $encontrado = null;
        if (empty($this->data) === false) {
            $encontrado = array_filter($this->data, function ($value, $key) use ($propiedad) {
                return $value['propiedad'] === $propiedad;
            }, ARRAY_FILTER_USE_BOTH);
        }

        if (empty($encontrado) === false) {
            $encontrado = reset($encontrado)['columna'];
        }

        return $encontrado;
    }
}