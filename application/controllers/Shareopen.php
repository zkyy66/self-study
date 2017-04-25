<?php
/**
 * @description公开活动
 * @author by Yaoyuan.
 * @version: 2017-01-12
 * @Time: 2017-01-12 16:23
 */
class ShareopenController extends Controller {
    //公开活动
    public function openActivityListAction() {
        $queryResult = array();
        $getParam = $this->request->getQuery();
        
        if (!$getParam['fid'] && !$getParam['mark']) {
            exit($this->_mixDataForJosn(self::ERR_PARAM,'缺少必要参数','','',''));
        }
        Fn::writeLog("shareopen/openactivitylist: 记录公开活动参数:".json_encode($getParam));
        
//        if (1 == $getParam['mark']) {
//            $getBaseToonParams = Toon::getBaseToonParams('portal');
//        } else if (2 == $getParam['mark']) {
//            $getBaseToonParams = Toon::getBaseToonParams('group');
//        }
        
        $getBaseToonParams = Toon::getBaseToonParams('portal');
        
        if (!$getBaseToonParams) {
            Fn::writeLog('shareopen/openactivitylist:获取toon平台通用参数失败， $getBaseToonParams:'.json_encode($getBaseToonParams));
            exit($this->_mixDataForJosn(self::ERR_PARAM,'获取toon平台通用参数失败','','',''));
        }
        
        $url = Yaf_Registry::get('config')->get('site.info.url');
        if (empty($url)) {
            Fn::writeLog('shareopen/openactivitylist:获取conf.ini的site.info.url参数失败， $url:'.$url);
            exit($this->_mixDataForJosn(self::ERR_PARAM,'请求地址为空','','',''));
        }
        
        //增加判断 URL是否为空的 判断
        $url = $url."/html/src/index.html?entry=104&mark={$getParam['mark']}";
        if (1 == $getParam['mark']) {
            $checkSign = Toon::checkSign('portal');
        } else if (2 == $getParam['mark']) {
            $checkSign = Toon::checkSign('group');
        }
        if (empty($checkSign)) {
            exit($this->_mixDataForJosn(self::ERR_PARAM,'密钥不能为空','','',''));
        }
        //缺少判断 checkSign 的返回判断
        if ($getParam['authSign'] != $checkSign) {
            exit($this->_mixDataForJosn(-15,'验证密钥失败','','',''));
        }
        
        if (!in_array($getParam['frame'], array('sf','ff','af'))) {
            exit($this->_mixDataForJosn(self::ERR_PARAM,'缺少必要参数','','',''));
        }
        
        $frame = $getParam['frame'];
        $time  = time();
        if ('sf' == $getParam['frame']) {
            exit($this->_mixDataForJosn(0,'success',[],[],[]));
        }
        
        $shareModel = new ShareModel();
        
        $fid = Fn::filterString($getParam['fid']);
        if (1 == $getParam['mark']) {
            $result = $shareModel->getByOpenFeedList($fid,$time,$frame);
        } else {
            $result = $shareModel->getByOpenGroupList($fid,$time,$frame);
        }
        if ($result) {
            foreach ($result as $key => $val) {
                $queryResult = $shareModel->getApplierCount($val['id']);
                $result[$key]['applierCount'] = empty($queryResult) ? 0 : $queryResult;
                $image = json_decode($val['image'],true);
                $image = $image ? $image : [];
                $imgUrl = $image;
                $result[$key]['image'] = $imgUrl['url'];
                $result[$key]['url'] = $url;
                $result[$key]['appId'] = $getBaseToonParams['authAppId'];
            }
            exit($this->_mixDataForJosn(0,'success',$url,$getBaseToonParams['authAppId'],$result));
        } else {
            exit($this->_mixDataForJosn(0,'success','','',''));
        }
    }
    
    
    /**
     * 组织json格式信息
     * @param $code
     * @param $message
     * @param $url
     * @param $appId
     * @param $list
     * @return string
     */
    private function _mixDataForJosn($code,$message,$url,$appId,$list) {
        if (empty($list)) {
            $jsonArray = array(
                'meta' => array(
                    'code' => $code,
                    'message' => $message
                ),
                'data' => array()
            );
            Fn::writeLog('公开活动返回结果：'.json_encode($jsonArray,JSON_FORCE_OBJECT));
            return json_encode($jsonArray,JSON_FORCE_OBJECT);
        } else {
            $jsonArray = array(
                'meta' => array(
                    'code' => $code,
                    'message' => $message
                ),
                'data' => array(
                    'url' => $url,
                    'appId' => $appId,
                    'list' => $list
                )
            );
        }
        Fn::writeLog('公开活动返回结果：'.json_encode($jsonArray));
        return json_encode($jsonArray);
    }
}