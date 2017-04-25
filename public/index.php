<?php
/**
 * 框架入口文件
 * @description Yaf 入口文件
 * @author zhaowei
 * @version 2016-05-24
 */

//error_reporting(E_ALL);
error_reporting(0);
define( "APP_PATH",  realpath( dirname(__FILE__) . '/../' ) );
//
//if ($_SERVER['HTTP_HOST'] == 't100devactivity.systoon.com' || $_SERVER['SERVER_ADDR'] == '172.28.50.174') {
//   define("APP_ENV", 'develop');
//} else {
//   define("APP_ENV", 'product');
//}

if (! defined('APP_ENV')) {
    $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';
    if (preg_match("/t100|p100/i", $serverName)) {
        define('APP_ENV', 'develop');
        error_reporting(E_ALL);
    } else {
        define('APP_ENV', 'product');
    }
}
//定义全局library
ini_set('yaf.library', APP_PATH . '/application/library');

$app  = new Yaf_Application( APP_PATH . "/conf/application.ini", APP_ENV);
$app->bootstrap()->run(); 
