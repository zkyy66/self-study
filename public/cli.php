<?php
error_reporting(E_ALL);
define( "APP_PATH",  realpath( dirname(__FILE__) . '/../' ) );
if (! defined('APP_ENV')) {
    
    define('APP_ENV', 'product');
    
}

$app = new Yaf_Application(APP_PATH . "/conf/application.ini", YAF_ENVIRON);

// $app->bootstrap()->getDispatcher()->dispatch(new Yaf_Request_Simple("CLI", 'crontab', 'changewalltype', 'index'));
$app->bootstrap()->getDispatcher()->dispatch(new Yaf_Request_Simple());