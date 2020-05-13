<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Asbd\Repositorios;
use Modelos\Prueba;

$config = [
    'usuario' => 'suma',
    'clave' => 'SumaDesarrollo',
    'host' => 'localhost',
    'basedatos' => 'suma',
    'modo' => 'single',
    'namespace' => 'Modelos'
];


Repositorios::setConfig($config);

/** @var Prueba $prueba */
//$prueba =  Repositorios::get(Prueba::class);
$prueba =  Repositorios::get('Prueba');

$a = $prueba->getById(1);

print_r($a);



