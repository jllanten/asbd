<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Asbd\Repositorios;
use Modelos\Prueba;

$config = [
    'usuario' => 'username',
    'clave' => 'password',
    'host' => 'localhost',
    'basedatos' => 'mydb',
    'modo' => 'single',
    'namespace' => 'models'
];


Repositorios::setConfig($config);

/** @var Prueba $prueba */
$prueba =  Repositorios::get(Prueba::class);

$data = $prueba->getById(1);

print_r($data);



