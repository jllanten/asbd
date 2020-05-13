<?php
namespace Asbd;

use Asbd\Excepciones\ConfigInvalida;
use Exception;
use PDO;

class Asbd
{
    /** Indica que la conexion es la misma que usa todo el sistema. Puede manejar transacciones entre varias entidades de la misma conexion */
    const CONEXION_UNICA = 'unica';

    /** Indica que la conexion es propia, no es reutilizada. Las transacciones son unicas por conexion */
    const CONEXION_INDEPENDIENTE = 'propia';

    /** @var array  */
    protected $config;

	/** @var string La base de datos a conectar */
	private $basedatos;

	/** @var PDO El handler de la bd */
	private $_dbh;

	/** @var string Indica el tipo de conexion que este objeto utuliza */
	private $tipoConexion;

	/** @var string La conexion cuando es unica */
	public static $conexionUnica = false;

	/** @var CacheEntidad */
	private static $cacheEntidades;

    /**
     * Asbd constructor. Si no se pasa nada son casos especiales requeridos que no tendran acceso a bd
     *  TODO: cambiar esto por un adaptador
     * @param string $host
     * @param string $usuario
     * @param string $clave
     */
	public function __construct(string $conexion = self::CONEXION_UNICA)
    {
        $this->tipoConexion = $conexion;

        $config = Repositorios::getConfig();
        if (empty($config) || empty($config['modo']) || empty($config['usuario']) || empty($config['clave']) || empty($config['host'])) {
            throw new ConfigInvalida();
        }

        if (($config['modo']) && ($config['modo'] === Repositorios::MODO_OPERACION_SINGLE) && (empty($config['basedatos']) === false)) {
            $this->setBaseDatos($config['basedatos']);
        }

        $this->config = $config;
	    $this->_dbh = false;

	    self::$cacheEntidades = new CacheEntidad();
	}

    /**
     * Establece la base de datos a utilizar:
     * Si es MULTI debe ser llamado desde Repositorios::establecerMultiCliente
     * Si es SINGLE debe ser llamado desde que ASBD es creado
     * @param string $basedatos
     */
	public function setBaseDatos(string $basedatos)
    {
        $this->basedatos = $basedatos;
    }

    /**
     * Abre una conexion a la bd. Sera reutilizada todo el tiempo
     * @return PDO
     * @throws \Exception
     */
	private function getConexion() : PDO
    {
        if ($this->tipoConexion === self::CONEXION_UNICA) {
            $conexion = $this->getConexionUnica();
        } else {
            $conexion = $this->getConexionIndependiente();
        }

        return $conexion;
    }

    /**
     * Devuelve o crear una conexión unica a la BD: la conexión se almacena en la clase (singleton)
     * @return bool|PDO|string
     * @throws Exception
     */
    protected function getConexionUnica(): PDO
    {
        // Si existe la reutilizamos
        if (self::$conexionUnica !== false) {
            return self::$conexionUnica;
        }

        self::$conexionUnica = $this->getConexionBD($this->config, $this->basedatos);

        return self::$conexionUnica;
    }

    /**
     * Devuelve o crea una conexion independiente
     * @return PDO
     * @throws Exception
     */
    protected function getConexionIndependiente(): PDO
    {
        // Si existe la reutilizamos
        if ($this->_dbh !== false) {
            return $this->_dbh;
        }

        $this->_dbh = $this->getConexionBD($this->config, $this->basedatos);

        return $this->_dbh;
    }

    /**
     * Genera la conexion a la base de datos usando la informacion
     * @return PDO
     * @throws Exception
     */
    private function getConexionBD(array $config, string $basedatos): PDO
    {
        if ($this->basedatos === '') {
            throw new \Exception('No se puede conectar a la BD sin haber definido base de datos');
        }

        $stringConexion = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $config['host'], $basedatos);

        $conexion = new PDO($stringConexion, $config['usuario'], $config['clave']);
        $conexion->setAttribute(PDO::ATTR_EMULATE_PREPARES, false );
        $conexion->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //        $this->_dbh->exec('set names utf8');

        return $conexion;
    }

    /**
     * Ejecuta un query (sql) en la bd con prepared statements (seguro)
     * @param string $query El query a ejecutar (preferiblemente creado con BuildQuery)
     * @param array $parametros (Opcional default vacio) Los parametros del query
     * @param bool $uno (opcional default false) Indica si espera un solo resultado o varios (un array)
     * @return array
     * @throws \Exception
     */
	public function query(string $query, array $parametros = [], $uno = false) : array
    {
        $conexion = $this->getConexion();

        $sth = $conexion->prepare($query);
        if ($sth === false) {
            $error = $conexion->errorInfo();
            throw new \Exception(sprintf('Error BD (%d) : %s',  $error[1], $error[2]));
        } else {
            $sth->execute($parametros);
            $result = $sth->fetchAll(PDO::FETCH_ASSOC);

            // Este caso especial es para que cuando el query devuelve un solo resultado, lo tire de una en el primer query.
            if (($uno === true) && (empty($result) === false) && (count($result) === 1)) {
                $result = $result[0];
            }
        }

		return $result;
	}

    /**
     * Actualiza varios campos de una tabla al mismo tiempo
     * @param string $tabla La tabla a actualizar
     * @param array $infoValues { campo1 => value1, ... }    --> los valores a cambiar
     * @param array $infoAnd { campo1 => value1, ... }    -> se usa en el WHERE
     * @param array $infoOr { campo1 => value1, ... }    -> se usa en el WHERE
     * @return bool
     */
	public function update(string $tabla, array $infoValues, array $infoAnd, array $infoOr = []) : bool
    {
        // ESTE METODO SOLO FUNCIONA CON UN WHERE POR EL MOMENTO
		$parametros = [];
		$sql = 'UPDATE ' . $tabla;

		// SET
		$sql .= ' SET ';
		$tmpSql = [];
		$i = 0;
		foreach ($infoValues as $key => $value) {
			$i++;
			$tmpSql[] = $this->propiedadToColumna($key) . ' = :value' . $i;
			$parametros[':value' . $i] = $value;
		}
		$sql .= implode (', ', $tmpSql);

		// WHERE
		$sql .= ' WHERE ';
		$tmpSql = [];
		$i = 0;
		foreach ($infoAnd as $key => $value) {
			$i++;
			$tmpSql[] = $this->propiedadToColumna($key) . ' = :id' . $i;
			$parametros[':id' . $i] = $value;
		}
		$sql .= implode (', ', $tmpSql);

        $conexion = $this->getConexion();
		$sth = $conexion->prepare($sql);
		if (!$sth) {
			echo $sth->errorInfo();
			return false;
		}

		$sth->execute($parametros);
		return true;
	}

    /**
     * Inserta un nuevo registro en la base de datos.
     * NO HAY VALIDACION. El llamante debe verificar que no exista el registro (PK)
     * @param string $tabla
     * @param array $campos
     * @return string
     * @throws Exception
     */
    public function insert(string $tabla, array $campos): string
    {
        $conexion = $this->getConexion();

        $listaCampos = [];
        $parametros = [];
        $listaValores = [];
        $i = 0;
        foreach ($campos as $campo => $valor) {
            $i++;
            $listaCampos[] = $this->propiedadToColumna($campo);
            $listaValores[] = ':value' . $i;
            $parametros[':value' . $i] = $valor;
        }

        $camposSql = implode(', ', $listaCampos);
        $parametrosSql = implode (', ', $listaValores);


        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $tabla, $camposSql, $parametrosSql);

        $sth = $conexion->prepare($sql);
/*
        // Agregar todos los parametros
        foreach ($parametros as $key => $valor) {
            $sth->bindParam($key, $valor);
        }
*/
        $sth->execute($parametros);
        if ($sth->errorCode() !== '00000') {
            throw new Exception(sprintf('ASBD: (%s): %s', $sth->errorCode(), $sth->errorInfo()[2]));
        }

        return $this->lastInsert();
    }

    public function iniciarTransaccion()
    {
        $this->getConexion()->beginTransaction();
    }

    public function rollbackTransaction()
    {
        $this->getConexion()->rollBack();
    }

    public function commitTransaction()
    {
        $this->getConexion()->commit();
    }

    /**
     * Obtiene el nombre de una columna tomando como base una propiedad
     * @param string $propiedad
     * @return string
     */
    private function propiedadToColumna(string $propiedad): string
    {
        // Si hay anotacion la usamos
        $clase = $this->getClaseActual(true);
        $infoEntidad = self::$cacheEntidades->getInfoEntidad($clase);
        $propiedadAnotacion = $infoEntidad->getColumnaDePropiedad($propiedad);

        if (empty($propiedadAnotacion)) {
            // Si la propiedad no tiene reemplazo (anotacion), devolvemos la actual convertida
            return QueryBuilder::snake($propiedad);
        }

        // En cambio si la propiedad SI tiene reemplazo, ya nos devuelven la columna lista
        return $propiedadAnotacion;
    }

    protected function columnaToPropiedad(string $columna): string
    {
        // Si hay anotacion la usamos
        $clase = $this->getClaseActual(true);
        $infoEntidad = self::$cacheEntidades->getInfoEntidad($clase);
        $columnaAnotacion = $infoEntidad->getPropiedadDeColumna($columna);
        if (empty($columnaAnotacion)) {
            // Si la columna no tiene reemplazo (anotacion), devolvemos la actual convertida
            $camelCase = QueryBuilder::camelCase($columna);
            return lcfirst($camelCase);
        }

        // En cambio si la columna SI tiene reemplazo, ya nos devuelven la propiedad full
        return $columnaAnotacion;
    }

    /**
     * Obtiene el nombre completo de la clase padre/hijo actual
     * @param bool $fullNameSpace
     * @return string
     */
    private function getClaseActual(bool $fullNameSpace = false): string
    {
        $class = get_class($this);
        if ($fullNameSpace) {
            return $class;
        }

        $partes = explode('\\', $class);
        return end($partes);
    }

    /**
     * Usado internamente: obtiene los nombres de los campos de la tabla usando las propiedades de la entidad
     * Devuelve un mapa de propiedad => columna
     * @return array
     */
    private function mapearPropiedadesACampos(array $propiedades): array {
        // Snake case se dejan como estan
        // Camel case se vuelve snake case
        $salida = [];
        foreach ($propiedades as $propiedad) {
            $salida[$propiedad] = QueryBuilder::snake($propiedad);
        }

        return $salida;
    }

    /**
     * El ID del ultimo insert
     * @return string
     */
	public function lastInsert() : string
    {
        $conexion = $this->getConexion();

		return $conexion->lastInsertId();
	}

    /**
     * Hidrata una entidad, desde un array con informacion, hasta objetos de la entidad respectiva
     * @param array $data Los datos a hidratar. Normalmente proviene de un query.
     * @param QueryBuilder $queryBuilder (Opcional default false) Si es pasado se intentara hidratar con modelos
     */
    public function hidratar(array $data, QueryBuilder $queryBuilder = null)
    {
        if (is_null($queryBuilder) === true) {
            foreach ($data as $columna => $valor) {
                $propiedad = $this->columnaToPropiedad($columna);
                $this->$propiedad = $valor;
            }
        } else {
            // Hidratacion segun los modelos
            $joins = $queryBuilder->getAliases();
            // TODO NO se como hidratar esto

        }
    }

    /**
     * Reemplaza un query con valores.
     * ADVERTENCIA: los valores deben ser seguros, esto se salta la seguridad de prepared statements. Usar solo para HAVING, LIMIT
     *              los cuales no aceptan parametros
     * @param string $sql
     * @param array $parametros
     * @return mixed|string
     */
    public function parametrizar(string $sql, array $parametros)
    {
        foreach ($parametros as $buscado => $reemplazo) {
            $sql = str_replace('%' . $buscado . '%', $reemplazo, $sql);
        }

        return $sql;
    }
}
