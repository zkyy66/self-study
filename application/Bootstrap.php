<?php

/**
 * 所有在Bootstrap类中, 以_init开头的方法, 都会被Yaf调用,
 * 这些方法, 都接受一个参数:Yaf_Dispatcher $dispatcher
 * 调用的次序, 和申明的次序相同
 */
class Bootstrap extends Yaf_Bootstrap_Abstract{

    public function _initConfig() {
        //配置保存
        $arrConfig = Yaf_Application::app()->getConfig();
        Yaf_Registry::set('config', $arrConfig);
    }
    /**
     * 关闭view 渲染
     * @param Yaf_Dispatcher $dispatch
     */
    public function _initView(Yaf_Dispatcher $dispatch) {
        $dispatch->disableView();
    }
    
    /**
     * 保存数据库配置
     */
    public function _initDb() {
        $dbConfig = Yaf_Application::app()->getConfig()->get('database')->toArray();
        Yaf_Registry::set('dbConfig', $dbConfig);
    }

    /**
     * 加载公共函数
     */
//    public function _initCommonFunctions(){
//        Yaf_Loader::import(Yaf_Application::app()->getConfig()->application->directory . '/common/functions.php');
//    }

    /**
     * 正则路由
     */
   public function _initRoute() {
        //在这里注册自己的路由协议,默认使用简单路由
        $router = Yaf_Dispatcher::getInstance()->getRouter();
        /**
         * 添加配置中的路由
         */
        $route = new Yaf_Route_Supervar("r");
        $router->addRoute("portal", $route);
   }
    
    /**
     * 加载本地命名空间 local library components文件
     */
    public function _initRegisterLocalNamespace() {
        $loader = Yaf_Loader::getInstance();
        $loader->registerLocalNamespace(
            array('Act')
        );
    }
}