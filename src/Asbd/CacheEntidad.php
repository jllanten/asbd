<?php

namespace Asbd;


class CacheEntidad
{
    /** @var array */
    private $cache = [];

    /**
     * Obtiene la informacion de una entidad del cache o lo genera
     * @param string $clase
     * @return array
     */
    public function getInfoEntidad(string $clase): InfoEntidad
    {
        if (array_key_exists($clase, $this->cache) === false) {
            $this->calcularCacheEntidad($clase);
        }

        return $this->cache[$clase];
    }

    /**
     * Calcula el cache de una entidad.
     * @param string $clase
     */
    private function calcularCacheEntidad(string $clase)
    {
        $propiedades = $this->getPropiedadesDeClase($clase);
        $infoPropiedades = [];
        if (empty($propiedades) === false) {
            foreach ($propiedades as $propiedad) {
                $anotacion = new \DocBlockReader\Reader($clase, $propiedad, 'property');
                $nombreColumna = $anotacion->getParameter('nombreColumna');

                if (is_null($nombreColumna) === false) {
                    $infoPropiedades[] = [
                        'propiedad' => $propiedad,
                        'columna' => QueryBuilder::snake($nombreColumna)
                    ];
                }
            }
        }

        $this->cache[$clase] = new InfoEntidad($infoPropiedades);
    }

    /**
     * Obtiene la lista de todas las propiedades de una clase
     * @param string $clase
     * @return array
     */
    private function getPropiedadesDeClase(string $clase): array
    {
        $output = [];
        $rc = new \ReflectionClass($clase);
        $propiedades = $rc->getProperties();
        foreach ($propiedades as $propiedad) {
            $output[] = $propiedad->getName();
        }

        return $output;
    }
}