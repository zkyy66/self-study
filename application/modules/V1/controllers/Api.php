<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2017-03-06
 * @Time: 2017-03-06 9:03
 */
class ApiController extends Controller {
    /**
     * 初始化
     */
    public $_redisString = 'main';
    public $_mcUserInfoPrefix = 'ZANZAN::activity::User::';
    public function init() {
        header("Access-Control-Allow-Origin:*");
        parent::init();
    }
    
    /**
     * 活动首页Tab页配置
     * 目前可配置Tab
     * 活动类型 精选，热门，娱乐 兴趣，户外，展览 ，演出，会议，运动，沙龙
     */
    
    public function getTabAction () {
        $post = file_get_contents('php://input');
        
        if (empty($post)) {
            Fn::outputToJson(self::ERR_PARAM,'参数缺失');
        }
        
        $info = json_decode($post,true);
        
        if (empty($info['appId'])) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid参数错误',[]);
        }
        $appConfig = Fn::getAppChildConfig('signature');//子应用配置
        if (!$appConfig) {
            Fn::outputToJson(self::ERR_PARAM, '应用配置参数缺失',[]);
        }
        if ($appConfig['appId'] != $info['appId']) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败',[]);
        }
        
        if (empty($info['appSign'])) {
            Fn::outputToJson(self::ERR_PARAM, '签名参数错误',[]);
        }
        if (empty($info['user_id'])) {
            Fn::outputToJson(self::ERR_PARAM, '用户ID参数错误',[]);
        }
        $param = array(
            'user_id' => $info['user_id'],
            'appId' => $info['appId']
        );
        $generateSignature = Fn::generateSignature('signature',$param);//签名
        
        if ($generateSignature != $info['appSign']) {
            Fn::outputToJson(self::ERR_PARAM, '签名认证失败',[]);
        }
        
        $tabArray = array(
            0 => array(
                'id' => '8',
                'name' => '精选',
                'res_type' => 2
            ),
            1 => array(
                'id' => '9',
                'name' => '热门',
                'res_type' => 3
            ),
            2 => array(
                'id' => '0',
                'name' => '娱乐',
                'res_type' => 4
            ),
            3 => array(
                'id' => '1',
                'name' => '兴趣',
                'res_type' => 4
            ),
            4 => array(
                'id' => '2',
                'name' => '户外',
                'res_type' => 4
            ),
            5 => array(
                'id' => '3',
                'name' => '展览',
                'res_type' => 4
            ),
            6 => array(
                'id' => '4',
                'name' => '演出',
                'res_type' => 4
            ),
            7 => array(
                'id' => '5',
                'name' => '会议',
                'res_type' => 4
            ),
            8 => array(
                'id' => '6',
                'name' => '运动',
                'res_type' => 4
            ),
            9 => array(
                'id' => '7',
                'name' => '讲座沙龙',
                'res_type' => 4
            )
        );
        Fn::outputToJson(0,'ok',$tabArray);
    }
    
    /**
     * 根据投票的appid和密钥生成code
     */
    public function generateVoteCodeAction () {
        $getParam = file_get_contents('php://input');
        $errMsg = '';
       
        Fn::writeLog('Api/generateVoteCode:'.var_export($getParam,true));
        if (empty($getParam)) {
            Fn::outputToJson(self::ERR_PARAM,"缺少必要参数");
        }
        
        $info = json_decode($getParam,true);
        
        if (empty($info['param']) || empty($info['user_id'])) {
            Fn::outputToJson(self::ERR_PARAM,"缺少必要参数");
        }
        
        $userInfo = User::getUserDetail(intval($info['user_id']));
        if (!$userInfo['userId']) {
            Fn::writeLog('Api/generateVoteCodeAction !user_id，参考：post:'.json_encode($userInfo));
            Fn::outputToJson(self::ERR_PARAM,'用户信息缺失');
        }
        
        $codeData = array(
            'visitor' => array(
                'feed_id' => $userInfo['feedId'],
                'user_id' => $userInfo['userId']
            ),
            'owner' => array(
                'feed_id' => $userInfo['feedId']
            ),
        
        );
       
        $voteConfig = Toon::getBaseToonParams($info['param']);
        
        if (empty($voteConfig) ) {
            $errMsg = 'toon配置文件基础参数缺失';
            Fn::outputToJson(self::ERR_PARAM,$errMsg);
        }

        $code = Toon::generateCypherText('shequn',$voteConfig['authAppId'],$codeData,$errMsg);

        Fn::writeLog('Api/generateVoteCode:'.var_export($code,true));
        
        if (!$code) {
            Fn::outputToJson(self::ERR_PARAM,"Code生成失败");
        }
        Fn::outputToJson(0,'ok',$code);
    }
    /**
     * 生成晒code
     */
    public function generateShaiCodeAction () {
        $getParam = file_get_contents('php://input');
        $errMsg = '';
        Fn::writeLog('Api/generateShaiCode:'.var_export($getParam,true));
        if (empty($getParam)) {
            Fn::outputToJson(self::ERR_PARAM,"缺少必要参数");
        }
    
        $info = json_decode($getParam,true);
    
        if (empty($info['param']) || empty($info['user_id'])) {
            Fn::outputToJson(self::ERR_PARAM,"缺少必要参数");
        }
        
        $userInfo = User::getUserDetail(intval($info['user_id']));
        if (!$userInfo['userId']) {
            Fn::writeLog('Api/generateShaiCodeAction !user_id，参考：post:'.json_encode($userInfo));
            Fn::outputToJson(self::ERR_PARAM,'用户信息缺失');
        }
        $codeData = array(
            'visitor' => array(
                'feed_id' => $userInfo['feedId'],
                'user_id' => $userInfo['userId']
            ),
            'owner' => array(
                'feed_id' => $userInfo['feedId']
            ),
    
        );
    
        $shaiConfig = Toon::getBaseToonParams($info['param']);
    
        if (empty($shaiConfig) ) {
            $errMsg = 'toon配置文件基础参数缺失';
            Fn::outputToJson(self::ERR_PARAM,$errMsg);
        }
    
        $code = Toon::generateCypherText('shequn',$shaiConfig['authAppId'],$codeData,$errMsg);
    
        Fn::writeLog('Api/generateShaiCode:'.var_export($code,true));
    
        if (!$code) {
            Fn::outputToJson(self::ERR_PARAM,"Code生成失败");
        }
        Fn::outputToJson(0,'ok',$code);
    }
    
    /**
     * 根据活动ID获取所属晒列表
     */
    public function getShaiListAction () {
        $post = file_get_contents('php://input');
        if (empty($post)) {
            Fn::outputToJson(self::ERR_PARAM,'缺少参数',[]);
        }
        $info = json_decode($post,true);
       
        if (empty($info['ac_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少活动标识参数',[]);
        }
        if (empty($info['userId'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少用户标识参数',[]);
        }
        if (empty($info['school_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少学校标识参数',[]);
        }
        $ticket = Act_Ticket::generateTicket($info['userId'],$info['school_id']);
        
        if (!$ticket) {
            Fn::outputToJson(self::ERR_PARAM,'生成Ticket失败',[]);
        }
        
        $shaiConfig  = Yaf_Registry::get('config')->get('shequntoon')->toArray();
        
        if (!$shaiConfig) {
            Fn::writeLog('Api/getShaiList 参数，shaiConfig:'.var_export($shaiConfig,true));
            Fn::outputToJson(self::ERR_PARAM, '晒参数错误');
        }
        
        $param = array(
            'appId' => $shaiConfig['appId'],
            'activityId' => $info['ac_id'],
            'userId' => $info['userId'],
        );
    
        $generateSignature = Fn::generateShaiSignature('shequntoon',$param);//签名

        $apiUrl = $shaiConfig['shaiUrl'].'/v1/shaiinfo/showActivityList?userId='.$info['userId'].'&activityId='.$info['ac_id'].'&appSign='.$generateSignature.'&appId='.$shaiConfig['appId'];
        
        $result  = Curl::callWebServer($apiUrl, [], 'get', 5, true, false);
        
        Fn::writeLog('Api/getShaiList:'.var_export($result,true));
    
        $result = json_decode($result, true);
        
        if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
            Fn::outputToJson(0,'ok',$result['data']);
        }
        Fn::outputToJson(0,'ok',[]);
    }
    
    /**
     * 活动--晒接口
     */
    public function shaiApiByActivityAction () {
        $post = file_get_contents('php://input');
        
        if (empty($post)) {
            Fn::writeLog('api/shaiApiByActivity empty(post)，参考：post:'.json_encode($post));
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }

        $info = json_decode($post,true);

        if (empty($info['ac_id'])|| empty($info['title']) || empty($info['code']) || empty($info['user_id'])) {
            Fn::writeLog('api/shaiApiByActivity 参数，info:'.var_export($info,true));
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        $shaiConfig  = Yaf_Registry::get('config')->get('shequntoon')->toArray();
        if (!$shaiConfig) {
            Fn::writeLog('api/shaiApiByActivity 参数，shaiConfig:'.var_export($shaiConfig,true));
            Fn::outputToJson(self::ERR_PARAM, '晒参数错误');
        }

        $shaiUrl = urlencode($shaiConfig['shaiUrl'].'/release?resId='.$info['ac_id'].'&resName=activity&title='.$info['title']);
        
        
        $param = array(
            'appId' => $shaiConfig['appId'],
            'params[title]' => $info['title'],
            'pageRouter' => 'release',
            'params[resId]' => $info['ac_id'],
            'params[resName]' => 'activity',
            'urlType' => 'shai',
            'code' => $info['code']
        );
        
        $generateSignature = Fn::generateShaiSignature('shequntoon',$param);//签名
        $shaiApiUrl = $shaiConfig['shaiUrl'];
        
        
        $url =  $shaiApiUrl.'/transfer/index?pageRouter=release&params[resId]='.$info['ac_id'].'&params[resName]=activity&params[title]='.$info['title'].'&urlType=shai&appId='.$shaiConfig['appId'].'&appSign='.$generateSignature.'&code='.$info['code'];
        
//        $url  = $shaiApiUrl.'/transfer/index?url='.$shaiUrl.'&urlType=shai&code='.$info['code'].'&appId='.$shaiConfig['appId'].'&appSign='.$generateSignature;
        
        Fn::outputToJson(0,'ok',$url);
        
    }
    /**
     * 根据活动ID获取活动信息
     * @return array|bool
     */
    public function getInfoByIDAction () {
       
        $ac_id = intval($this->request->getQuery('ac_id'));
        $appId = intval($this->request->getQuery('appId'));
        $appSign = $this->request->getQuery('appSign');
        $appConfig = Fn::getAppChildConfig('signature');//子应用配置

        
        if (empty($ac_id)) {
            Fn::outputToJson(self::ERR_PARAM, '活动id参数错误',[]);
        }
        if (empty($appId)) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid参数错误',[]);
        }
    
        if ($appConfig['appId'] != $appId) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败',[]);
        }
        if (empty($appSign)) {
            Fn::outputToJson(self::ERR_PARAM, '签名参数错误',[]);
        }
        $param = array(
            'appId' => $appId,
            'ac_id' => $ac_id
        );
        $generateSignature = Fn::generateSignature('signature',$param);//签名
        
        if ($generateSignature != $appSign) {
            Fn::outputToJson(self::ERR_PARAM, '签名认证失败');
        }
        
        $acModel = new ActivityModel();
        
        $info = $acModel->getActivity($ac_id);
        
        if (empty($info)) {
            Fn::outputToJson(1,'暂无活动信息',[]);
        }
        $extInfo = array();
        $info['url_info'] = Fn::getServerUrl().'/html/src/index.html?entry=3&ac_id='.$ac_id;
        $extInfo = $acModel->getActivityExtInfo($info['activity_id']);
        $info = array_merge($info,$extInfo);

        $ac_img = json_decode($info['img'],true);
        $info['img'] = $ac_img['url'];

        Fn::outputToJson(0,'ok',$info);
    }
    
    /**
     * 我参与，我发布 ，我心动的活动列表
     */
    public function getMyAcInfoAction () {
        
        $page = intval($this->request->getQuery('offset',1));
        $appId = intval($this->request->getQuery('appId'));
        $appSign = $this->request->getQuery('appSign');
        $user_id = intval($this->request->getQuery('user_id'));
        $appConfig = Fn::getAppChildConfig('signature');//子应用配置
        $limit = intval($this->request->getQuery('limit',10));
        
        if (empty($user_id)) {
            Fn::outputToJson(self::ERR_PARAM, '用户ID参数错误');
        }
        if (empty($appId)) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid参数错误');
        }
        if ($appConfig['appId'] != $appId) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败');
        }
        if (empty($appSign)) {
            Fn::outputToJson(self::ERR_PARAM, '签名参数错误');
        }

        $param = array(
            'appId' => $appConfig['appId'],
            'user_id' => $user_id,
            'offset' => $page,
            'limit' => $limit ? $limit : 0
        );
        $generateSignature = Fn::generateSignature('signature',$param);//签名
        
        if ($generateSignature != $appSign) {
            Fn::outputToJson(self::ERR_PARAM, '签名认证失败');
        }

        $acModel = new ActivityModel();
        $myApplyList = $acModel->getMyListData($user_id,2,$page,$limit,1);//我参与的活动
        $myPubList   = $acModel->getMyListData($user_id,1,$page,$limit,1);//我发布的活动
        
        $myHeartBeatList = User::getheartListByUserId($user_id,$page,$limit,1);

//        Fn::writeLog("api/getMyAcInfo:".var_export($myHeartBeatList,true));
        
        if ($myHeartBeatList['objIdList']) {
            $myHeartBeatList = implode(',',$myHeartBeatList['objIdList']);
            $myHeartBeatList = $acModel->getListByAcId($myHeartBeatList,$page,$limit);
        } else {
            $myHeartBeatList = array();
        }
        
        if ($myApplyList && $myHeartBeatList) {//我参与的和我心动的去重
            foreach ($myApplyList as $key => $val) {
                foreach ($myHeartBeatList as $mKey => $mVal) {
                    if ($mVal['activity_id'] == $val['activity_id']) {
                        unset($myHeartBeatList[$mKey]);
                    }
                }
            }
        }
        
        if ($myPubList && $myHeartBeatList) {//我发布的和我心动的去重
            foreach ($myPubList as $key => $val) {
                foreach ($myHeartBeatList as $mKey => $mVal) {
                    if ($mVal['activity_id'] == $val['activity_id']) {
                        unset($myHeartBeatList[$mKey]);
                    }
                }
            }
        }
        //我参与的活动整理数据结构
        $list = $appTempArray = $pubTempArray = $heartTempArray = array();
        
        $appTempArray = $this->_combinationData($myApplyList);
        $pubTempArray = $this->_combinationData($myPubList);
        $heartTempArray = $this->_combinationData($myHeartBeatList);
    
        $appTempArray = $appTempArray ? $appTempArray : array();
        $pubTempArray = $pubTempArray ? $pubTempArray : array();
        $heartTempArray = $heartTempArray ? $heartTempArray : array();
        
        $list = array_merge($appTempArray,$pubTempArray,$heartTempArray);
        //$list = Fn::multiArraySort($list,'start_time',SORT_ASC);
        if ($limit > 0) {
            $total = count($list);
            $totalPage = ceil($total/$limit);
            $result['list']  = array_slice($list, ($page - 1) * $limit, $limit);
        } else {
            $totalPage = 1;
        }
        $result['totalPage'] = $totalPage;
        $result['page'] = $page;
        
        Fn::outputToJson(0,'ok',$result);
        
    }
    
    /**
     * 获取全部资源列表
     */
    public function getAllListAction () {
        $time = time();
        $page = intval($this->request->getQuery('page',1));
        $pageSize = intval($this->request->getQuery('pageSize',10));
        $appID = intval($this->request->getQuery('appId'));
        $appSign = $this->request->getQuery('appSign');
        $appConfig = Fn::getAppChildConfig('signature');
        
        if (empty($appID)) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid参数错误');
        }
        if ($appConfig['appId'] != $appID) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败');
        }
        if (empty($appSign)) {
            Fn::outputToJson(self::ERR_PARAM, '签名参数错误');
        }
        $param = array(
            'appId' => $appID,
            'page' => $page,
            'pageSize' => $pageSize ? $pageSize : 10,
        );
        $generateSignature = Fn::generateSignature('signature',$param);
        if ($generateSignature != $appSign) {
            Fn::outputToJson(self::ERR_PARAM, '签名认证失败');
        }
        
        $acModel       = new ActivityModel();
        
        $panelList = $acModel->getPanelList($page, $pageSize, $time);//精选

        $typeList = $acModel->getList($page, $pageSize,NULL,NULL,NULL,2,1);//获取分类列表
        
        $panelList = $panelList ? $panelList : array();
        $typeList = $typeList ? $typeList : array();
        
        if ($panelList) {
            $panelList = $this->_minRuleDataRow($panelList,$time,1);//组合数据
        }
        $tmpEndAcArr = $list = array();
        if ($typeList) {
            $typeList = $this->_minRuleDataRow($typeList,$time);//组合数据
            foreach ($typeList as $key => $value) {
                // 已结束的活动单独存放，会放在最后
                if ($time >= $value['end_time']) {
                    $tmpEndAcArr[] = $typeList[$key];
                    unset($typeList[$key]);
                }
            }
            if ($tmpEndAcArr) {
                $tmpEndAcArr = Fn::multiArraySort($tmpEndAcArr,'end_time',SORT_DESC);
                
            }
            $list = array_merge($typeList, $tmpEndAcArr);
            $list = $list ? $list : array();
        }
       
        
        if ($panelList && $list) {//精选和分类排重
            foreach ($panelList as $key => $val) {
                foreach ($list as $mKey => $mVal) {
                    if ($mVal['id'] == $val['id']) {
                        unset($list[$mKey]);
                    }
                }
            }
        }
        
        $result = array_merge($panelList,$list);//合并数据
        
//        $result = Fn::multiArraySort($result,'start_time',SORT_ASC);//按照发布时间正序排序
//        $result = Fn::multiArraySort($result,'showTop',SORT_DESC);//精选排序
        unset($typeList,$list);//销毁变量
        
        if ($pageSize > 0) {
            $total = count($result);
            $totalPage = ceil($total/$pageSize);
            $list['list']  = array_slice($result, ($page - 1) * $pageSize, $pageSize);
        } else {
            $totalPage = 1;
        }
    
        $list['totalPage'] = $totalPage;
        $list['page'] = $page;
//        Fn::writeLog("全部数据：".json_encode($list));
        Fn::outputToJson(0,'ok',$list);
        
    }
    
    /**
     * 获取本校接口
     */
    public function getSchoolListAction () {
        $time = time();
        $page = intval($this->request->getQuery('page',1));
        // 每页活动多少条信息
        $pageSize = intval($this->request->getQuery('pageSize',10));
        $school_id = intval($this->request->getQuery('school_id',0));//学校ID
        $appID = intval($this->request->getQuery('appId'));
        $appSign = $this->request->getQuery('appSign');
        $appConfig = Fn::getAppChildConfig('signature');
        
        if (empty($school_id)) {
            Fn::outputToJson(self::ERR_PARAM, '学校参数错误');
        }
        if (empty($appID)) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid参数错误');
        }
        if ($appConfig['appId'] != $appID) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败');
        }
        if (empty($appSign)) {
            Fn::outputToJson(self::ERR_PARAM, '签名参数错误');
        }
    
        $acModel       = new ActivityModel();
        $result = $acModel->getList($page, $pageSize, NULL,NULL,$school_id,2);
    
//        Fn::writeLog("API/getSchoolList:".var_export($result,true));
        if (empty($result)) {
            Fn::outputToJson(self::OK, 'ok', []);
        }
    
        $result = $this->_minRuleDataRow($result,$time);
        $tmpEndAcArr = array();
        foreach ($result as $key => $value) {
            // 已结束的活动单独存放，会放在最后
            if ($time >= $value['end_time']) {
                $tmpEndAcArr[] = $result[$key];
                unset($result[$key]);
            }
        }
        
        if ($tmpEndAcArr) {
            $tmpEndAcArr = Fn::multiArraySort($tmpEndAcArr,'end_time',SORT_DESC);
        }
        
        $result = array_merge($result, $tmpEndAcArr);

    
        if ($pageSize > 0) {
            $total = count($result);
            $totalPage = ceil($total/$pageSize);
            $list['list']  = array_slice($result, ($page - 1) * $pageSize, $pageSize);
        } else {
            $totalPage = 1;
        }
        
        $list['totalPage'] = $totalPage;
        $list['page'] = $page;
//        Fn::writeLog("获取本校接口数据：".var_export($list,true));
        Fn::outputToJson(0,'ok',$list);
    }
    /**
     * 获取其他学校活动列表
     */
    public function getOtherSchoolListAction () {
        $time = time();
        $page = intval($this->request->getQuery('page',1));
        // 每页活动多少条信息
        $pageSize = intval($this->request->getQuery('pageSize',10));
        $school_id = intval($this->request->getQuery('school_id',0));//学校ID
        $appID = intval($this->request->getQuery('appId'));
        $appSign = $this->request->getQuery('appSign');
        $appConfig = Fn::getAppChildConfig('signature');
        
        if (empty($school_id)) {
            Fn::outputToJson(self::ERR_PARAM, '学校参数错误');
        }
        if (empty($appID)) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid参数错误');
        }
        if ($appConfig['appId'] != $appID) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败');
        }
        if (empty($appSign)) {
            Fn::outputToJson(self::ERR_PARAM, '签名参数错误');
        }
        $param = array(
            'appId' => $appID,
            'page' => $page,
            'pageSize' => $pageSize ? $pageSize : 10,
            'school_id' => $school_id ? $school_id : 0
        );
        $generateSignature = Fn::generateSignature('signature',$param);
        if ($generateSignature != $appSign) {
            Fn::outputToJson(self::ERR_PARAM, '签名认证失败');
        }
        $acModel       = new ActivityModel();
        $result = $acModel->getOtherSchoolList($page, $pageSize,$school_id,2);
        
//        Fn::writeLog("API/getOtherSchoolList:".var_export($result,true));
        
        if (empty($result)) {
            Fn::outputToJson(self::OK, 'ok', []);
        }
        $result = $this->_minRuleDataRow($result,$time);
        $tmpEndAcArr = array();
        foreach ($result as $key => $value) {
            // 已结束的活动单独存放，会放在最后
            if ($time >= $value['end_time']) {
                $tmpEndAcArr[] = $result[$key];
                unset($result[$key]);
            }
        }
        if ($tmpEndAcArr) {
            $tmpEndAcArr = Fn::multiArraySort($tmpEndAcArr,'end_time',SORT_DESC);
        } else {
            $tmpEndAcArr = array();
        }
        
        $result = array_merge($result, $tmpEndAcArr);
        if ($pageSize > 0) {
            $total = count($result);
            $totalPage = ceil($total/$pageSize);
            $list['list']  = array_slice($result, ($page - 1) * $pageSize, $pageSize);
        } else {
            $totalPage = 1;
        }
    
        $list['totalPage'] = $totalPage;
        $list['page'] = $page;
//        Fn::writeLog("获取外校接口数据：".var_export($list,true));
        Fn::outputToJson(0,'ok',$list);
    }
    /**
     * 获取资源列表
     * @res_type 1标示获取学校列表；2标示获取精选列表；3标示获取热门列表；4标示获取分类列表
     */
    public function getResListAction() {
        $res_mark = intval($this->request->getQuery('res_type',1));
        $time = time();
        $page = intval($this->request->getQuery('page',1));
        // 每页活动多少条信息
        $pageSize = intval($this->request->getQuery('pageSize',10));
        
        if (!in_array($res_mark,array(1,2,3,4,5))) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        $type = intval($this->request->getQuery('type',0));//活动类型
        $school_id = intval($this->request->getQuery('school_id',0));//学校ID
        $appID = intval($this->request->getQuery('appId'));
        $appSign = $this->request->getQuery('appSign');
        $appConfig = Fn::getAppChildConfig('signature');
        
        if (empty($res_mark)) {
            Fn::outputToJson(self::ERR_PARAM, '资源类型参数错误');
        }
        if (empty($appID)) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid参数错误');
        }
        if ($appConfig['appId'] != $appID) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败');
        }
        if (empty($appSign)) {
            Fn::outputToJson(self::ERR_PARAM, '签名参数错误');
        }
        $param = array(
            'appId' => $appID,
            'page' => $page,
            'pageSize' => $pageSize ? $pageSize : 10,
            'res_type' => $res_mark,
            'type' => $type ? $type : 0,
            'school_id' => $school_id ? $school_id : 0
            );
        $generateSignature = Fn::generateSignature('signature',$param);
        if ($generateSignature != $appSign) {
            Fn::outputToJson(self::ERR_PARAM, '签名认证失败');
        }
        $acModel       = new ActivityModel();
    
        $mark = '';
        //getList($offset,$limits,$type = NULL,$poi = NULL,$school_id = NULL,$is_admin=NULL)
        
        if (1 == $res_mark) {//学校
            $result = $acModel->getList($page, $pageSize, NULL,NULL,$school_id,2);
//            Fn::writeLog('API/学校:'.var_export($result,true));
            $mark = 1;
        } else if (2 == $res_mark) {//精选
            $result = $acModel->getPanelList($page, $pageSize, $time);
//            Fn::writeLog('API/精选:'.var_export($result,true));
        } else if (3 == $res_mark) {//热门
            $result = $acModel->getHotList($page,$pageSize,$time);
//            Fn::writeLog('API/热门:'.var_export($result,true));
        } else if (4 == $res_mark) {//分类
            $result = $acModel->getList($page, $pageSize, $type);
            $mark = 1;
//            Fn::writeLog('API/分类:'.var_export($result,true));
        } else if (5 == $res_mark) {
            $mark = 1;
            $result = $acModel->getOtherSchoolList($page, $pageSize,$school_id,2);
        }
       
        if (empty($result)) {
            Fn::outputToJson(self::OK, 'ok', []);
        }
        
        $result = $this->_minRuleDataRow($result,$time);
        $tmpEndAcArr = array();
        if ($mark) {
            foreach ($result as $key => $value) {
                // 已结束的活动单独存放，会放在最后
                if ($time >= $value['end_time']) {
                    $tmpEndAcArr[] = $result[$key];
                    unset($result[$key]);
                }
            }
            $tmpEndAcArr = Fn::multiArraySort($tmpEndAcArr,'create_time',SORT_ASC);
            $result = array_merge($result, $tmpEndAcArr);
        }
    
        if ($pageSize > 0) {
            $total = count($result);
            $totalPage = ceil($total/$pageSize);
            $list['list']  = array_slice($result, ($page - 1) * $pageSize, $pageSize);
        } else {
            $totalPage = 1;
        }
    
        $list['totalPage'] = $totalPage;
        $list['page'] = $page;
        
        Fn::outputToJson(0,'ok',$list);
    }
    //荐你玩儿
    public function getRecommendListAction() {
        $time = time();
        $page = intval($this->request->getQuery('page',1));
        // 每页活动多少条信息
        $pageSize = intval($this->request->getQuery('pageSize',10));
        $appID = intval($this->request->getQuery('appId'));
        $appSign = $this->request->getQuery('appSign');
        $appConfig = Fn::getAppChildConfig('signature');

        if (empty($appID)) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid参数错误');
        }
        if ($appConfig['appId'] != $appID) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败');
        }
        if (empty($appSign)) {
            Fn::outputToJson(self::ERR_PARAM, '签名参数错误');
        }
        $param = array(
            'appId' => $appID,
            'page' => $page ? $page : 1,
            'pageSize' => $pageSize ? $pageSize : 10,
            
        );
        $generateSignature = Fn::generateSignature('signature',$param);
       if ($generateSignature != $appSign) {
           Fn::outputToJson(self::ERR_PARAM, '签名认证失败',[]);
       }
        $acModel       = new ActivityModel();
        

        $result = $acModel->getList($page, $pageSize, NULL,'index',NULL,1);
        
        $list = $this->_minRuleDataRow($result,$time);
        unset($result);
        if ($pageSize > 0) {
            $total = count($list);
            $totalPage = ceil($total/$pageSize);
            $result['list']  = array_slice($list, ($page - 1) * $pageSize, $pageSize);
        } else {
            $totalPage = 1;
        }
    
        $result['totalPage'] = $totalPage;
        $result['page'] = $page;
//        Fn::writeLog("api/荐你玩儿列表:".var_export($result,true));
        Fn::outputToJson(0,'ok',$result);
        
    }
    
    
    /**
     * 根据活动ID获取心动总数
     */
    public function totalLoveNumAction () {
        $post = file_get_contents('php://input');
        if (empty($post)) {
            Fn::outputToJson(self::ERR_PARAM,'缺少参数',[]);
        }
        
        $info = json_decode($post,true);
        
        if (empty($info['appId'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少AppId参数',[]);
        }
    
        if (empty($info['appSign'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少签名参数',[]);
        }
        $appConfig = Fn::getAppChildConfig('signature');
        if ($appConfig['appId'] != $info['appId']) {
            Fn::outputToJson(self::ERR_PARAM, 'Appid认证失败');
        }
    
        $signatureParam = array(
            'ac_id' => $info['ac_id'],
            'appId' => $appConfig['appId'],
            'type' => $info['type'] ? $info['type'] : 0
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);
        
        if ($info['appSign'] != $appSign) {
            Fn::outputToJson(self::ERR_PARAM, '签名认证失败');
        }
        $acstatMoel = new AcstatModel();
        if (1 == $info['type']) {
            $result = $acstatMoel->incrLoveNum($info['ac_id']);
        } else if (0 == $info['type']) {
            $result = $acstatMoel->decrLoveNum($info['ac_id']);
        }
        Fn::outputToJson(0,'ok',$result);
    }
    /**
     * 整理活动数据结构
     * @param array $list
     * @return array
     */
    private function _combinationData (array $list) {
        $tempArray = array();
        if (empty($list)) {
            $tempArray = array();
        }
        
        foreach ($list as $key => $val) {
            $img = json_decode($val['img'],true);

            $tempArray[] = array(
                'id' => $val['activity_id'],
                'title' => $val['title'],
                'img' => $img['url'],
                'start_time' => $val['start_time'],
                'end_time' => $val['end_time'],
                'create_time' => $val['create_time'],
                'skipUrl' => Fn::getServerUrl().'/html/src/index.html?entry=3&ac_id='.$val['activity_id']
            );
        }
        return $tempArray;
    }



    /**
     * 整理数据
     * @param array $list
     * @param $time
     * @return array
     */
    private function _minRuleDataRow(array $list,$time,$tab=NULL) {
        if ($list) {
            foreach ($list as $key => $val) {
                if ($time >= $val['start_time'] && $time <= $val['end_time']) {
                    $status = '进行中';
                }
                if ($val['switch_status'] & 1) {
                    if ($time < $val['apply_end_time']) {
                        $status = '报名中';
                    }
                    if ($val['apply_end_time']< $time && $time < $val['start_time']) {
                        $status = '报名截止';
                    }
                } else if ($time < $val['start_time']) {
                    $status = '未开始';
                }
    
                if ($time >= $val['end_time']) {
                    $status = '已结束';
                }
                if ($val['price'] == 0.00 || $val['price'] == 0) {
                    $val['price'] = 0;
                }
                
                $list[$key]['id'] = $val['activity_id'];
                $list[$key]['price'] = floatval($val['price']);
                $list[$key]['img'] = json_decode($val['img'],true);
                $list[$key]['ac_status'] = $status;
                $list[$key]['user_id'] = $val['user_id'];
                $list[$key]['applierCount'] = $val['applierCount'] ? $val['applierCount'] : 0;
                $list[$key]['allow_apply'] = $val['switch_status'] & 1;
                $list[$key]['skipUrl'] = Fn::getServerUrl().'/html/src/index.html?entry=3&ac_id='.$val['activity_id'];
                if (1 == $tab) {
                    $list[$key]['showTop'] = 1;
                    $list[$key]['tab_name'] = "精选";
                } else {
                    $tab_name = "";
                    //活动类型 0娱乐 1兴趣  2户外   3展览  4演出  5会议 6运动 7-沙龙
                    switch ($val['type']) {
                        case 1:
                            $tab_name = '兴趣';
                            break;
                        case 2:
                            $tab_name = '户外';
                            break;
                        case 3:
                            $tab_name = '展览';
                            break;
                        case 4:
                            $tab_name = '演出';
                            break;
                        case 5:
                            $tab_name = '会议';
                            break;
                        case 6:
                            $tab_name = '运动';
                            break;
                        case 7:
                            $tab_name = '讲座沙龙';
                            break;
                        default:
                            $tab_name = '娱乐';
                            break;
                    }
                    $list[$key]['tab_name'] = $tab_name;
                }
                
            }
        } else {
            $list = [];
        }
        
        return $list;
    }
    
}
