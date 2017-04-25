<?php
/**
 * 默认Controller
 * @description Yaf 入口文件
 * @author zhaowei
 * @version 2016-05-24
 */
class IndexController extends Controller {

    // public function init() {
    //     header("Access-Control-Allow-Origin:*");
    //     parent::init();
    //     $this->checkPortalTicket = false;
    // }
    
    //默认Action
    public function indexAction()
    {
        echo '---';
        $getParams = $this->getRequest()->getQuery('r');
        var_dump($getParams);
        echo 'activity-index';
    }
    
    
}