<?php

namespace Asbd;

use Asbd\Excepciones\ErrorInterno;

class QueryBuilder
{
    const ORDER_BY_DESC = 'desc';
    const ORDER_BY_ASC = 'asc';

    protected $select = '';
    protected $from = [];
    protected $and = [];
    protected $or = [];
    protected $groupBy = [];
    protected $orderBy = [];
    protected $expresiones = [];
    protected $joins = [];
    protected $query = '';
    protected $limit = null;
    protected $offset = null;

    /**
     * Devuelve todos los aliases del query (from y joins)
     * @return array
     */
    public function getAliases()
    {
        $aliases = $this->from;
        if (empty($this->joins) === false) {
            foreach ($this->joins as $join) {
                $aliases[] = [
                    'tabla' => $join['tabla'],
                    'alias' => $join['alias']
                ];
            }
        }

        return $aliases;
    }

    /**
     * Devuelve el nombre completo con namespace de una clase segun la tabla dada
     * @param string $tabla
     * @return string
     */
    private function getClase(string $tabla): string
    {
        $dbConfig = Repositorios::getConfig();
        $clase = sprintf('%s\%s', $dbConfig['namespace'], self::camelCase($tabla));

        return $clase;
    }

    public function select($campos)
    {
        $this->select = $campos;
        return $this;
    }

    /**
     * La clausula FROM del query
     * @param string $tabla
     * @param string $alias (Opcional default vacio) Si se agrega es para hacer aliases
     * @return $this
     */
    public function from(string $tabla, string $alias = '')
    {
        $this->from = [
            'tabla' => $tabla,
            'alias' => $alias,
            'clase' => $this->getClase($tabla)
        ];
        return $this;
    }

    /**
     * Agrega una tabla para hacer join
     * @param string $tabla
     * @param string $alias
     * @param string $condicion
     * @param string $tipo left, right, inner, full
     * @return self
     */
    public function join(string $tabla, string $alias, string $condicion, string $tipo)
    {
        $this->joins[] = [
            'tabla' => $tabla,
            'alias' => $alias,
            'condicion' => $condicion,
            'tipo' => $tipo,
            'clase' => $this->getClase($tabla)
        ];
        return $this;
    }

    /**
     * Usado para hacer left join
     * Se puede usar solo indicando el primer parametro con la estructura completa: tabla t1 ON cond1 = cond2
     * O se puede usar discriminando cada campo: tabla, t1, cond1 = cond2
     * @param string $tabla
     * @param string $alias
     * @param string $condicion
     * @return QueryBuilder
     */
    public function leftJoin(string $tabla, string $alias = '', string $condicion = '')
    {
        $partes = explode(' ', $tabla);
        if (empty($alias)) {
            $tabla = $partes[0];
            $alias = $partes[1];
            $condicion = substr($tabla, strlen($partes[0]) + strlen($partes[1]) + 2);
        }

        return $this->join($tabla, $alias, $condicion, 'left');
    }

    /**
     * Usado para hacer left join
     * Se puede usar solo indicando el primer parametro con la estructura completa: tabla t1 ON cond1 = cond2
     * O se puede usar discriminando cada campo: tabla, t1, cond1 = cond2
     * @param string $tabla
     * @param string $alias
     * @param string $condicion
     * @return QueryBuilder
     */
    public function innerJoin(string $tabla, string $alias = '', string $condicion = '')
    {
        $partes = explode(' ', $tabla);
        if (empty($alias)) {
            $tabla = $partes[0];
            $alias = $partes[1];
            $condicion = substr($tabla, strlen($partes[0]) + strlen($partes[1]) + 2);
        }

        return $this->join($tabla, $alias, $condicion, 'inner');
    }

    /**
     * Agrega la condicion WHERE
     * @param string $campos
     * @return QueryBuilder
     */
    public function where(string $campos)
    {
        $this->and[] = $campos;
        return $this;
    }

    /**
     * Agrega una condicion AND
     * @param string $campos
     * @return mixed
     */
    public function and(string $campos)
    {
        $this->and[] = $campos;
        return $this;
    }

    /**
     * Agrega una condicion OR. Es poco probable que se use porque implica parentesis y no los manejamos
     * @param string $campos
     * @return $this
     */
    public function or(string $campos)
    {
        $this->or[] = $campos;
        return $this;
    }

    /**
     * Agrega un group by
     * @param string $campos
     * @return QueryBuilder
     */
    public function groupBy(string $campos): self
    {
        $this->groupBy[] = $campos;
        return $this;
    }

    /**
     * Agrega un Order BY con una direccion especifica
     * @param string $campo
     * @param string $direccion
     * @return $this
     * @internal param string $campos
     */
    public function orderBy(string $campo, string $direccion = self::ORDER_BY_DESC)
    {
        $this->orderBy[] = [
            'campo' => $campo,
            'direccion' => $direccion
        ];
        return $this;
    }

    /**
     * @param int $limite
     * @return $this
     */
    public function limit(int $limite, int $offset = 0)
    {
        $this->limit = $limite;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Agrega una expresion SQL. Normalmente solo se utiliza una sola que tiene la totalidad del codigo aqui
     * Las expresiones TIENEN que empezar con AND o con OR
     * @param string $sql
     */
    public function expresion(string $sql)
    {
        $this->expresiones[] = $sql;
    }

    protected function getTablaModelo($model): string
    {
        // NO se usa porque necesito cargar los modelos de los joins y prefiero esperar a tener adaptadores
        // Aparte de eso necesitaria saber como saber si es core o cliente
        return (new $model)->tabla;
    }

    /**
     * Convierte un nombre (de columna) en formato snake
     * @param string $value
     * @param string $delimiter
     * @return string
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
        if (!ctype_lower($value)) {
            $value = strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value));
        }

        return $value;
    }

    /**
     * Convierte un snake-case en camel case
     * @param string $value
     * @param string $delimiter
     * @return string
     */
    public static function camelCase(string $value, string $delimiter = '_'): string
    {
        $string = str_replace('_', '', ucwords($value, '_'));

        return $string;
    }

    /**
     * Construye el query
     * @param bool $entidades (Opcional default false) Si se pasa TRUE asume que los valores de los AND/OR son propiedades de Entidades
     * @return string
     */
    public function build(bool $entidades = false)
    {
        // El from se hace primero para poder calcular, si hay joins, el select despues
        // Si no hay alias asignamos uno nosotros mismos
        if ($this->from['alias'] === '') {
            $this->from['alias'] = 't1';
        }

        //$tablaFrom = $this->getTablaModelo($this->from['tabla']);
        $from = ' FROM  ' . self::snake($this->from['tabla']) . ' AS ' . $this->from['alias'] . ' ';

        // La lista de tablas involucradas en este query
        $tablasInvolucradas = array_merge([$this->from], $this->joins);

        // Joins
        if (empty($this->joins) === false) {
            foreach ($this->joins as $join) {
                // Expandir la condicion
                $tmpCondicion = $this->propiedadToColumna($join['condicion'], $tablasInvolucradas);

                $from .= sprintf(
                    ' %s JOIN %s AS %s ON %s' ,
                    strtoupper($join['tipo']),
                    self::snake($join['tabla']),
                    $join['alias'],
                    $tmpCondicion
                );

                // Cada tabla join se agrega al select (Si agregaron manualmente algo al select esto es ignorado)
                $selects[] = $join['alias'] . '.*';
            }
        }

        // Select
        // Si el select le fue entregado algo lo usamos (en exclusiva). De lo contrario se agregan TODOS los from y joins
        if ($this->select === '') {
            $selects[] = $this->from['alias'] . '.*';
            $select = 'SELECT ' . implode(', ', $selects);
        } else {
            $select = 'SELECT ' . $this->propiedadToColumna($this->select, $tablasInvolucradas);
        }

        $where = ' WHERE ';
        $primeraCondicion = true;

        // Si hay expresiones sql van primero. Normalmente no se mezclan con AND/OR pero es posible,
        // el problema son los parentesis que no se como manejarlos
        // TODO: Agregar codigo para discriminar estos casos
        if (count($this->expresiones) > 0) {
            foreach ($this->expresiones as $record) {
                $where .= '(' . $record . ') ';
            }
        }

        // Agregar todos los AND
        if (count($this->and) > 0) {
            foreach ($this->and as $record) {
                if ($primeraCondicion === true) {
                    $primeraCondicion = false;
                    $wherePreparado = $this->and[0];
                } else {
                    $wherePreparado = ' AND  ' . $record . ' ';
                }

                //$wherePreparado = $this->sqlAColumna($wherePreparado, $tablasInvolucradas);
                // Los queries DEBEN contener la forma alias.campo donde alias es OBLIGATORIO y campo puede ser propiedad o columna
                $wherePreparado = $this->propiedadToColumna($wherePreparado, $tablasInvolucradas);
                $where .= $wherePreparado;
            }
        }

        // Agregar todos los OR
        if (count($this->or) > 0) {
            foreach ($this->or as $record) {
                if ($primeraCondicion === true) {
                    $primeraCondicion = false;
                    $orPreparado = $this->or[0];
                } else {
                    $orPreparado = ' OR ' . $record . ' ';
                }

                $orPreparado = $this->propiedadToColumna($orPreparado, $tablasInvolucradas);
                $where .= $orPreparado;
            }
        }

        // Si primera condicion nunca cambio es porque no hay ningun where
        if ($primeraCondicion) {
            $where = '';
        }

        // GroupBy
        $groupBy = '';
        if (count($this->groupBy) > 0) {
            $groupBy .= ' GROUP BY ' ;
            foreach ($this->groupBy as $campoGroupBy) {
                $groupByPreparado = $this->propiedadToColumna($campoGroupBy, $tablasInvolucradas);
                $groupBy .= $groupByPreparado;
            }
        }

        // Order by
        $orderBy = '';
        if (count($this->orderBy) > 0) {
            $orderBy .= ' ORDER BY ' ;
            foreach ($this->orderBy as $campoOrder) {
                $orderByPreparado = sprintf('%s %s', $this->propiedadToColumna($campoOrder['campo'], $tablasInvolucradas), $campoOrder['direccion']);
                $orderBy .= $orderByPreparado;
            }
        }

        // Limit

        $limit = '';
        if (is_null($this->limit) === false) {
            $limit = ' LIMIT ' . $this->limit;

            if (is_null($this->offset) === false) {
                $limit .= ' OFFSET ' . $this->offset;
            }
        }

        $this->query = $select . $from . $where . $groupBy . $orderBy . $limit;
        return $this->query;
    }

    /**
     * Convierte una propiedad en una columna sql
     * @param string $cadena
     * @param array $tablas
     * @return string
     * @throws ErrorInterno
     */
    private function propiedadToColumna(string $cadena, array $tablas): string
    {
        // Convierte objeto.propiedad en tabla.columna
        $salida = $cadena;

        // Busca todas las partes de la cadena que sean algo.algo ya que esos son campos a cambiar (los alias son obligatorios)
        preg_match_all('/([\w]*\.[\w]*)/', $cadena, $matches);

        if (count($matches) > 0) {
            if (count($matches) === 2) {
                $matches = $matches[0];
            }

//            $encontradas = [];
            foreach ($matches as $match) {
                // El regex esta malo: encuentra varias veces el mismo. No afecta las cadena sino el rendimiento
//                if (is_array($match)) {
//                    $match = $match[0];
//                }
//
//                if (in_array($match, $encontradas)) {
//                    continue;
//                }
//                $encontradas[] = $match;

                // Si hay un match siempre habra un reemplazo
                list($alias, $propiedad) = explode('.', $match);

                // Entre la lista de tablas que participan en el query buscamos la que coincidan (solo debe ser una)
                $tmpTablas = array_filter($tablas, function($value, $key) use ($alias) {
                    return ($value['alias'] === $alias);
                }, ARRAY_FILTER_USE_BOTH);

                if (count($tmpTablas) !== 1) {
                    throw new ErrorInterno(sprintf('QueryBuilder: ASBD no encuentra match el convertir propiedad %s a columna', $propiedad));
                }

                //$infoTabla = $tmpTablas[0];
                $infoTabla = current($tmpTablas);

                // Hay un match de alias, eso quiere decir que debemos cambiar la propiedad por la columna
                // TODO: en lugar de usar snake deberiamos utilizar el mapa de la entidad
                $nueva = sprintf('%s.%s', $infoTabla['alias'], $this->obtenerColumnaDePropiedad($propiedad, $infoTabla));
                $salida = str_replace($match, $nueva, $salida);
            }
        }

        return $salida;
    }

    private function obtenerColumnaDePropiedad(string $propiedad, array $infoTabla): string
    {
        // Como BuildQuery acepta tanto notacion de entidades (alias.NombrePropiedad) como notacion SQL (alias.nombre_columna)
        // cuando es entidad necesitamos verificar si hay una anotacion para reemplazar la propiedad
        $propiedadesClase = get_class_vars($infoTabla['clase']);
        if (array_key_exists($propiedad, $propiedadesClase)) {
            // Como es una propiedad vamos a buscar las anotaciones de la entidad
            $anotacion = new \DocBlockReader\Reader($infoTabla['clase'], $propiedad, 'property');
            $nombreColumna = $anotacion->getParameter('nombreColumna');
            if (is_null($nombreColumna) === false) {
                return $nombreColumna;
            }

            return QueryBuilder::snake($propiedad);
        }

        // Si no existia el campo asumimos que es SQL entonces lo dejamos pasar
        return $propiedad;

    }

    /**
     * Devuelve el query calculado
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }
}