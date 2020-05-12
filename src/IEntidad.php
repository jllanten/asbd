<?php

namespace Asbd;

Interface IEntidad
{
    public function setBaseDatos(string $basedatos);
    public function hidratar(array $datos, QueryBuilder $queryBuilder = null);
    public function bootstrap();
}