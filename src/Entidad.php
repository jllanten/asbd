<?php

namespace Asbd;

use Carbon\Carbon;
use Exception;
use Asbd\Excepciones\ErrorInterno;
use ReflectionClass;

class Entidad extends Asbd implements IEntidad
{
    const HIDRATACION_ENTIDAD = 'ENTIDAD';
    const HIDRATACION_ARRAY = 'ARRAY';

    const HIDRATACION_SALIDA_UNO = 'uno';
    const HIDRATACION_SALIDA_ARRAY = 'array';

    const ESTADO_SI = 'S';
    const ESTADO_NO = 'N';

    /** @var String Indica el nucleo (core / bd) de donde obtener la entidad */
    public $nucleo;
    public $tabla;
    public $primaria = null;

    /**
     * Indica si la entidad es de nucleo Core
     * @return bool
     */
    public function esCore()
    {
        return ($this->nucleo === 'Core');
    }

    public function __construct(string $tipoConexion = Asbd::CONEXION_UNICA)
    {
        /// Si no se ha definido una llave primera la definimos nosotros mismos con base en el nombre de la clase/tabla
        if (is_null($this->primaria)) {
            $shortClassName = (new ReflectionClass($this))->getShortName();
            $this->primaria = lcfirst($shortClassName) . 'Id';
        }

        parent::__construct($tipoConexion);
    }

    /**
     * Crear la entidad respectiva usando un array. Se supone que cada campo debe pertenecer a la clase
     * @param array $data
     * @return Entidad
     * @throws ErrorInterno
     */
    public function crearEntidad(array $data): self
    {
        $nuevo = new static();
        $clase = get_called_class();

        foreach ($data as $columna => $valor) {
            $propiedad = $this->columnaToPropiedad($columna);

            if (property_exists($clase, $propiedad) === false) {
                throw new ErrorInterno('No se puede crear una clase de un array: hay propiedades inexistentes');
            }

            $nuevo->$columna = $valor;
        }

        return $nuevo;
    }

    /**
     * Metodo que se ejecuta siempre apenas una entidad es creada. El objetivo es hacer cualquier tipo de inicializacion requerida
     * Esta diseñado para que cada entidad hijo la reemplaza si es necesario
     */
    public function bootstrap()
    {

    }

    /**
     * Obtiene una nueva entidad del tipo solicitado. Si no se da uno sera una copia de la entidad actual.
     * Esto se usa para las hidrataciones principalmente
     * @param string $claseSolicitada
     * @return IEntidad|string
     * @internal param string $entidad
     */
    public function getEntidad(string $claseSolicitada = ''): IEntidad
    {
        $claseEntidad = empty($claseSolicitada) ? QueryBuilder::camelCase($this->tabla) : $claseSolicitada;

        // A 2019/11/07 las entidades y los repositorios son uno solo entonces devolvemos la "entidad" generada por Repositorios.
        // No vemos necesidad de usar repositorios reales por ahora
        if ($this->config['modo'] === Repositorios::MODO_OPERACION_SINGLE) {
            $entidad = Repositorios::get($claseEntidad);
        } else {
            // Si es multi
            if ($this->esCore() === true) {
                $entidad = Repositorios::getCore(ucfirst($claseEntidad));
            } else {
                $entidad = Repositorios::getCliente(ucfirst($claseEntidad));
            }
        }

        return $entidad;
    }

    /**
     * Establece el valor de la llave primaria de la entidad
     * @param $valor
     */
    public function setId($valor)
    {
        $pk = $this->getPrimaria();
        $this->$pk = $valor;
    }

    /**
     * Devuelve el valor de la llave primaria de la entidad
     * @return mixed
     */
    public function getId()
    {
        $pk = $this->getPrimaria();
        return isset($this->$pk) ? $this->$pk : null;
    }

    /**
     * Obtiene la propiedad que es la Primary Key de una tabla. El formato utilizado es nombreTabla_id
     * @return string
     */
    public function getPrimaria()
    {
        return $this->primaria;

    }

    /**
     * Obtiene la columna que es la primary Key de una tabla.
     * Ojo que nombreTabla debe ser en formato snake
     * @return string
     */
    public function getPrimariaColumna()
    {
        //$shortClassName = (new ReflectionClass($this))->getShortName();
        //return $shortClassName . '_id';
        return QueryBuilder::snake($this->getPrimaria());
    }


    /**
     * Devuelve un objeto por id
     * @param int $id El id a buscar
     * @param string $tipoHidratacion
     * @return array | boolean | mixed
     */
    public function getById(int $id, string $tipoHidratacion = self::HIDRATACION_ENTIDAD)
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from($this->tabla, 't')
            ->where('t.' . $this->getPrimariaColumna() . ' = :id')
            ->build();

        $data = $this->query($sql, [':id' => $id]);

        $salida = $this->hidratarSet($data, $tipoHidratacion);

        // Si el query es vacio devuelve falso
        if (empty($salida)) {
            return false;
        } else {
            // Si contiene un solo registro retornamos la entidad, de lo contrario la coleccion
            return (count($salida) === 1) ? $salida[0] : $salida;
        }
    }

    /**
     * Hace un query basico por un campo
     * @param string $campo
     * @param string $valor
     * @param bool $tipoHidratacion
     * @return array|bool|mixed
     */
    public function getByCampo(string $campo, string $valor, string $tipoHidratacion = self::HIDRATACION_ENTIDAD, string $tipoSalida = self::HIDRATACION_SALIDA_UNO)
    {
        if (in_array($tipoHidratacion, [self::HIDRATACION_ENTIDAD, self::HIDRATACION_ARRAY]) === false) {
            throw new Exception('Tipo invalido de hidratacion');
        }

        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from($this->tabla, 't')
            ->where('t.' . $campo . ' = :valor')
            ->build();

        $data = $this->query($sql, [':valor' => $valor]);

        $salida = $this->hidratarSet($data, $tipoHidratacion);

        // Salida en array o en entidad
        if ($tipoSalida === self::HIDRATACION_SALIDA_UNO) {
            // Si el query devuelve un solo registro retornamos la entidad, de lo contrario la coleccion
            return (count($salida) === 1) ? $salida[0] : $salida;
        }

        return $salida;
    }

    /**
     * Hace un SELECT *
     * @param string $tipoHidratacion
     * @return array
     */
    public function getAll(string $tipoHidratacion = self::HIDRATACION_ENTIDAD): array
    {
        $qb = new QueryBuilder();
        $sql = $qb->select('*')
            ->from($this->tabla, 't')
            ->build();

        $data = $this->query($sql);

        return $this->hidratarSet($data, $tipoHidratacion);
    }

    /**
     * Devuelve un array de entidades segun los ids pasados por parametro
     * @param array $ids
     * @return array
     */
    public function getInArray(array $ids): array
    {
        $expandidos = implode(',', $ids);

        $qb = new QueryBuilder();
        $sql = $qb->select('t.*')
            ->from($this->tabla, 't')
            ->where(sprintf('t.%s IN (%s)', $this->getPrimariaColumna(), $expandidos) )
            ->build();

        $salida = $this->query($sql);

        $hidratada = $this->hidratarSet($salida);

        return $hidratada;
    }

    /**
     * Hace un query (seguro) con nomenclatura de prepared statements
     * @param array $condiciones
     * @param array $campos
     * @param string $tipoHidratacion
     * @param string $uno
     * @return array
     */
    public function getWhere(array $condiciones, array $campos = [], string $tipoHidratacion = self::HIDRATACION_ENTIDAD, string $uno = self::HIDRATACION_SALIDA_ARRAY)
    {
        $qb = new QueryBuilder();
        $qb->select('*')
            ->from($this->tabla, 't');

        foreach ($condiciones as $condicion) {
            $qb->where($condicion);
        }

        $sql = $qb->build();
        $data = $this->query($sql, $campos);

        return $this->hidratarSet($data, $tipoHidratacion, $uno);
    }

    /**
     * Hidrata toda una respuesta ya sea en entidad o como array
     * @param array $data Los datos a hidratar. Debe ser el resultado del query
     * @param string $tipoHidratacion
     * @param string $uno
     * @return array | Entidad
     */
    public function hidratarSet(array $data, string $tipoHidratacion = self::HIDRATACION_ENTIDAD, string $uno = self::HIDRATACION_SALIDA_ARRAY)
    {
        $salida = [];
        if ((is_array($data) === true) && (empty($data) === false)) {
            foreach ($data as $record) {
                if ($tipoHidratacion === self::HIDRATACION_ENTIDAD) {
                    /** @var \Asbd\IEntidad $tmp */
                    $tmp = $this->getEntidad();
                    // Cada valor retornado por el query lo metemos en las propiedades de la entidad
                    $tmp->hidratar($record);
                    $salida[] = $tmp;

                    unset($tmp);
                } else {
                    $salida[] = $record;
                }
            }
        }

        if ($uno === self::HIDRATACION_SALIDA_UNO) {
            $salida = (count($salida) === 1) ? $salida[0] : $salida;
        }

        return $salida;
    }

    /**
     * Hace un query con una sentencia sql nativa
     * Es necesario que el codigo prevenga inyecciones sql. Puede ser preferible un QueryBuilder con expresion()
     * @param string $sql
     * @param array $valores El listado de valores a utilizar en el query
     * @return array|bool|mixed
     */
    public function sql(string $sql, array $valores)
    {
        $data = $this->query($sql, $valores);

        $salida = false;
        if ((is_array($data) === true) && (empty($data) === false)) {
            foreach ($data as $record) {
                /** @var \Asbd\IEntidad $tmp */
                $tmp = $this->getEntidad();

                // Cada valor retornado por el query lo metemos en las propiedades de la entidad
                $tmp->hidratar($record);

                $salida[] = $tmp;
                unset($tmp);
            }
        }

        // Si el query devuelve un solo registro retornamos la entidad, de lo contrario la coleccion
        return (count($salida) === 1) ? $salida[0] : $salida;
    }

    /**
     * Toma una entidad y devuelve un array que contiene solamente las propiedades indicadas
     * @param array $propiedades (Opcional) Si se pasa solamente estas propiedades seran retornadas
     * @return array
     * @throws Exception
     */
    public function toArray(array $propiedades = []): array
    {
        $propiedades = (empty($propiedades) || ($propiedades === 'all')) ? $this->getAllPropiedades() : $propiedades;

        $salida = [];
        foreach ($propiedades as $propiedad) {
            if (property_exists($this, $propiedad) === false) {
                throw new Exception(sprintf('ASBD: propiedad %s no existe', $propiedad));
            }

            $salida[$propiedad] = $this->$propiedad;
        }

        return $salida;
    }

    /**
     * Crear un registro nuevo de la entidad actual (INSERT INTO). Devuelve el ID de la entidad creada.
     * @return string
     * @throws Exception
     */
    public function crear(bool $actualizaFecha = true): string
    {
        $propiedades = $this->getAllPropiedades();

        // ID no puede estar seteado
        if ($this->getId() !== null) {
            throw new Exception('Intentando crear entidad con id ya existente');
        }

        $propiedadesValores = [];
        foreach ($propiedades as $propiedad) {
            $propiedadesValores[$propiedad] = $this->$propiedad;
        }

        // Algunas tablas no tienen llave primaria (tablas detalle generalmente)
        $primaria = $this->getPrimaria();
        if (array_key_exists($primaria, $propiedadesValores)) {
            unset ($propiedadesValores[$primaria]);
        }

        if ($actualizaFecha) {
            // Colocar dinamicamente fechaActualizacion si es que existe y no lo han mandado ya
            if ((array_key_exists('fechaCreacion', $propiedadesValores)) && (empty($propiedadesValores['fechaCreacion']))) {
                $propiedadesValores['fechaCreacion'] = Carbon::now()->format('Y-m-d H:i:s');
            }
        }

        $insertId = $this->insert($this->tabla, $propiedadesValores);
        $this->setId($insertId);

        return $insertId;
    }

    /**
     * Actualiza un registro de la base de datos. El registro actualizado es el actual (this). Si no se pasan condiciones
     * se asume que es la llave primaria.
     * @param array $values
     * @param array $condiciones
     * @param bool $actualizaFecha
     */
    public function actualizar(array $values, array $condiciones = [], bool $actualizaFecha = true)
    {
        // TODO: Deberiamos ser capaces de auto generar "values" comparando con cuales valores han cambiado y cuales no
        // Esto implicaria que hagamos una carga de la entidad antes del update

        if (empty($condiciones)) {
            $condiciones = [
                sprintf('%s', $this->getPrimaria()) => $this->getId()
            ];
        }

        if ($actualizaFecha) {
            // Colocar dinamicamente fechaActualizacion si es que existe y no lo han mandado ya
            if (property_exists(get_called_class(), 'fechaActualizacion')
                && (array_key_exists('fechaActualizacion', $values) === false) && (array_key_exists('t.fechaActualizacion', $values) === false)
            ) {
                $values['fechaActualizacion'] = date('Y-m-d H:i:s');
            }
        }

        $this->update($this->tabla, $values, $condiciones);

        // La propiedad es dinamicamente actualizada para no tener que hacer un reload()
        foreach ($values as $propiedad => $valor) {
            $this->$propiedad = $valor;
        }
    }

    /**
     * Actualiza UN SOLO campo para la entidad actual (el ID debe estar seteado)
     * El campo fechaActualizacion cambia automaticamente si es que existe
     * @param string $campo
     * @param $valor
     * @param bool $actualizaFecha
     * @throws ErrorInterno
     */
    public function actualizarCampo(string $campo, $valor, bool $actualizaFecha = true)
    {
        if (is_null($this->getId())) {
            throw new ErrorInterno('ASBD: intentando actualizar una tabla sin pk');
        }

        $this->actualizar(
            [$campo => $valor]
        , [
            $this->getPrimaria() => $this->getId()
        ], $actualizaFecha);
    }

    /**
     * Obtiene un listado de todas las propiedades de la clase hijo (las que son inherentes a la entidad)
     * @return array
     */
    public function getAllPropiedades(): array
    {
        $propiedadesAIgnorar = ['tabla', 'primaria'];

        $infoClase = new ReflectionClass($this);
        $salida = [];
        foreach ($infoClase->getProperties() as $propiedad) {
            // Solamente incluimos propiedades publicas
            // Que pertenezcan a la entidad destino (que no sean de ASBD ni de Entidad)
            // Que no esten en la lista de propiedades ignoradas
            if (    $propiedad->isPublic()
                && ($propiedad->class == $infoClase->name)
                && (in_array($propiedad->name, $propiedadesAIgnorar) === false)) {
                    $salida[] = $propiedad->name;
            }
        }

        return $salida;
    }

    /**
     * Usado internamente: obtiene los nombres de los campos de la tabla usando las propiedades de la entidad
     * Devuelve un mapa de propiedad => columna
     * @return array
     */
    private function mapearPropiedadesACampos(): array {
        $propiedades = $this->getAllPropiedades();

        // Snake case se dejan como estan
        // Camel case se vuelve snake case
        $salida = [];
        foreach ($propiedades as $propiedad) {
            $salida[$propiedad] = QueryBuilder::snake($propiedad);
        }

        return $salida;
    }

    /**
     * Recarga cada una de los datos de la entidad haciendo una lectura de la BD. Esto se equipara burdamente a un Active Record
     */
    public function reload()
    {
        /** @var Entidad $data */
        $data = $this->getById($this->getId());
        foreach ($this->getAllPropiedades() as $propiedad) {
            $this->$propiedad = $data->$propiedad;
        }
    }

    // ------------------------------------METODOS GENERICOS DE UTILIDAD PARA ENTIDADES ------------------
    public function esActivo(): bool
    {
        return ($this->estado === static::ESTADO_ACTIVO);
    }

    /**
     * Verifica si un ID existe y si no lanza una excepcion
     * @param int $id
     * @param string $claseExcepcion
     * @return Entidad
     * @internal param Exception $e
     */
    public function validarIdExiste(int $id, string $claseExcepcion): Entidad
    {
        $entidad = $this->getById($id);

        if (empty($entidad)) {
            throw new $claseExcepcion();
        }

        return $entidad;
    }

    /**
     * Un metodo generico para cambiar el valor del campo estado
     * @param string $estado
     */
    public function cambiarEstado(string $estado)
    {
        $this->actualizarCampo('estado', $estado);
    }
}