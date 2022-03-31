<?php
/**
 * Bootstrap common for MVC & CLI applications
 *
 * $di MUST be defined before including this script.
 * $di MUST instantiate \Phalcon\Di\FactoryDefault
 *
 * BASE_PATH & APP_PATH constants MUST be defined before
 * including this script
 *
 *
 * @var FactoryDefault $di
 */

use Phalcon\Config\Adapter\Ini;
use Phalcon\Db\Adapter\Pdo\Postgresql;
use Phalcon\Di\FactoryDefault;
use Phalcon\Logger\Adapter\Syslog;

#------------------------------------------------------------------------------
$config = new Ini(APP_PATH . '/config.ini');

$di->set('config', $config);
$di->set('log', function () use ($config) {
    return new Syslog($config->path('app.ident'));
});
$di->set('db', function () use ($config) {
    $cnf = (array)$config->get('db');
    $psql = new Postgresql($cnf);
    \Phalcon\Db::setup(['forceCasting' => true]);
    return $psql;
});
$di->set('crypt', function () use ($config) {
    $c = new Phalcon\Crypt;
    $c->setCipher('aes-256-ctr');
    $c->setHashAlgo('sha3-256');
    $c->setKey($config->path('app.crypt_key'));
    $c->setPadding($c::PADDING_ANSI_X_923);
    $c->useSigning(true);
    return $c;
});