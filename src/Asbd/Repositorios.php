<?php

namespace Asbd;

use Asbd\Excepciones\ModoInvalido;
use Asbd\Excepciones\NoHayModo;

class Repositorios
{
    /** Modos de operacion aceptados */
    const MODO_OPERACION_SINGLE = 'single';
    const MODO_OPERACION_MULTI = 'multi';

    /** @var array */
    protected static $config = '';

    /** @var string El cliente actual */
    public static $clienteActual = '';

    /** @var string Indica si esta operando en modo multi o single cliente*/
    public static $multiCliente = false;

    /** @var string Indica el modo de operacion (single | multi) */
    public static $modoOperacion = '';

    /** @var string */
    public static $rutaInicial;

    /**
     * Graba la configuracion de la base de datos
     * @param array $config
     * @throws ModoInvalido
     * @throws NoHayModo
     */
    public static function setConfig(array $config)
    {
	    if (empty($config['modo'])) {
	        throw new NoHayModo();
        }
        $modoOperacion = $config['modo'];

        if (in_array($modoOperacion, [self::MODO_OPERACION_MULTI, self::MODO_OPERACION_SINGLE]) === false) {
            throw new ModoInvalido();
        }

        // Por defecto los modelos se encuentran en la raiz en /modelos
        if (array_key_exists('namespace', $config) === false) {
            $config['namespace'] = 'modelos';
        }

        self::$config = $config;

        // La ubicacion de donde se llama el config sera establecida como la raiz de donde estan los modelos
        // Esta informacion tambien se puede setear/cambiar por setRutaBase()
        $infoRuta = debug_backtrace();
        self::$rutaInicial = dirname($infoRuta[0]['file']);

	    self::$modoOperacion = $modoOperacion;
    }

    public function setRutaBase(string $raiz)
    {
        self::$rutaInicial = $raiz;
    }

    /**
     * Devuelve los datos de conexion a la BD. Esto debe ser llamado solamente desde ASBD cuando necesita crear una conexion
     * @return array
     */
    public static function getConfig(): array
    {
        return self::$config;
    }

    /**
     * @param string $nucleo (Opcional) Si es MULTI: Grupo de entidades (Core, base de datos). SI es SINGLE: la entidad
     * @param string $entidad La entidad (modelo) a buscar
     * @param string|bool $cliente (Opcional, default el valor actual de self::cliente actual) El cliente a utilizar
     * @return IEntidad
     * @throws \Exception
     */
    public static function get(string $nucleo = '', string $entidad = '', $cliente = false): IEntidad
    {
        $repositorio = null;
        // Cuando es single, el nucleo en realidad es la entidad deseada.
        if (self::$modoOperacion === self::MODO_OPERACION_SINGLE) {
            $repositorio = self::getSingle($nucleo);
        }

        if (self::$modoOperacion === self::MODO_OPERACION_MULTI) {
            $repositorio = self::getMulti($nucleo, $entidad, $cliente);
        }

        return $repositorio;
    }

    /**
     * Modo: SINGLE
     * Obtiene un repositorio para una entidad en modo SINGLE
     * @param string $entidad
     * @return IEntidad
     */
    private static function getSingle(string $entidad): IEntidad
    {
        // Para evitar problemas cargamos la clase
        // TODO no se como autoload esas clases
        //include_once(self::$config['namespace'] . DIRECTORY_SEPARATOR . $entidad . '.php');

        // Si mandan el namespace completo ignoramos la definicion local del namespace porque asumo que es completo
        if (strpos($entidad, self::$config['namespace']) !== false) {
            $fullEntidad = $entidad;
        } else {
            // De lo contrario lo utilizamos tal cual
            $fullEntidad = self::$config['namespace'] . '\\' . $entidad;
        }

        // Los modelos deben existir en raiz + nombre_clase respetando la estructura del namespace
        // ej: si raiz = /var/coco y piden la clase Modelos/Prueba la clase debe existir en /var/coco/Modelos/Prueba.php
        $ruta = str_replace("\\", DIRECTORY_SEPARATOR, $fullEntidad);
        include_once self::$rutaInicial . DIRECTORY_SEPARATOR . $ruta . '.php';

        /** @var IEntidad $entidad */
        $entidad = new $fullEntidad();
        $entidad->bootstrap();

        return $entidad;
    }

    /**
     * Modo: MULTI
     * Obtiene un repositorio para una entidad para un nucleo epscifico
     * @param string $nucleo
     * @param string $entidad
     * @param bool $cliente
     * @return IEntidad
     */
    public static function getMulti(string $nucleo, string $entidad = '', $cliente = false): IEntidad
    {
        // Para evitar problemas cargamos la clase
        // TODO no se como autoload esas clases
        include_once('modelos/' . ucfirst($nucleo) . '/' . $entidad . '.php');

        /** @var IEntidad $entidad */
        $entidad = new $entidad();

        self::establecerMultiCliente($nucleo, $entidad, $cliente);

        return $entidad;
    }


    /**
     * Modo: MULTI
     * @param string $nucleo
     * @param string $entidad
     * @param bool $cliente
     * @throws \Exception
     */
    private static function establecerMultiCliente(string $nucleo, string $entidad, $cliente = false)
    {
        if ($nucleo === 'Core') {
            $bdCliente = 'Core';
        } else {
            // Si es Cliente y pasaron un cliente manual lo usamos
            // Esto es normalmente usado para queries entre clientes - es pesado
            if ($cliente !== false) {
                $bdCliente = $cliente;
            } else {
                // Si no se paso y hay un cliente definido ok, sino error
                if (self::$clienteActual !== '') {
                    $bdCliente = self::$clienteActual;
                } else {
                    throw new \Exception('Error: no se puede hacer una consulta de cliente sin cliente definido');
                }
            }
        }

        /** @var $entidad IEntidad */
        $entidad->setBasedatos(self::$config['bd_header'] . strtolower($bdCliente));
    }

    /**
     * Modo: MULTI
     * Devuelve un repositorio de de tipo Core
     * @param string $entidad
     * @return IEntidad|string
     */
    public static function getCore(string $entidad)
    {
        return self::get('Core', $entidad);
    }

    /**
     * Modo: MULTI
     * Devuelve un repositorio de tipo Cliente
     * @param string $entidad
     * @param string|bool $cliente (Opcional, default el valor actual de self::cliente actual) El cliente a utilizar
     * @return IEntidad|string
     */
    public static function getCliente(string $entidad, $cliente = false)
    {
        return self::get('Cliente', $entidad, $cliente);
    }

    /**
     * Devuelve una conexion a la base de datos. Esto se usa para hacer queries directamente
     * La razon de este metodo (porque bastaria con un simple asbd) es para controlar cuando es modo multi/single en un solo metodo
     * @param string $nucleo (Opcional) Si es MULTI: Core|Usuarios. Si es SINGLE se deja en blanco
     * @return Asbd
     */
    public static function getDb(string $nucleo = ''): Asbd
    {
        $db = new Asbd();

        if (self::$modoOperacion === self::MODO_OPERACION_MULTI) {
            $db->setBasedatos(self::$config['bd_header'] . strtolower($nucleo));
        }

        return $db;
    }

    /**
     * Modo: MULTI
     * Establece el cliente actual a utilizar cuando se busque un repositorio de tipo Cliente
     * @param string $cliente
     */
    public static function setClienteActual(string $cliente)
    {
        // TODO Hay que confirmar que exista
        self::$clienteActual = $cliente;
    }
}
