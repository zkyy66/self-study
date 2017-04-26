<?php

/**
 * @description
 * @author by Yaoyuan.
 * @version: 2016-11-21
 * @Time: 2016-11-21 16:30
 */
class UserController extends Controller
{
    public $_redisString = 'main';
    public $_mcUserInfoPrefix = 'ZANZAN::activity::User::';
    //缓存，30分钟
    public $_indexMcTime = 10;
    
    /**
     * 登录验证身份
     */
    public function checkLoginAction()
    {
        //设置客户端票,目的是防止非法访问
        $this->setPortalTicket();
        
        $getParams = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($getParams)) {
            Fn::writeLog("user/checklogin: !is_array(getparam), 参考：" . json_encode($getParams));
            Fn::outputToJson(self::ERR_PARAM, '数据格式错误');
        }
        if (empty($getParams['code'])) {
            Fn::writeLog("user/checklogin:缺少code, 参考：" . json_encode($getParams));
            Fn::outputToJson(self::ERR_PARAM, '缺少必要参数');
        }
        
        $errmsg = $entry = '';
        
        $genre = 'portal';
        $result = Toon::getFeedIdByCode($genre, $getParams['code'], $errmsg);
        
        if (!$result) {
            Fn::writeLog("user/checklogin:解析失败, 参考：genre:" . $genre . "， getParams：" . json_encode($getParams));
            Fn::outputToJson('-1', '解析失败', []);
        }
        
        $userinfo = json_decode($result, true);
        
        if (empty($userinfo)) {
            Fn::writeLog("user/checklogin:解析失败, 参考：genre:" . $genre . "， getParams：" . json_encode($userinfo));
            Fn::outputToJson(self::ERR_PARAM, '用户信息缺失', []);
        }
        if (isset($userinfo['toon_type']) && 113 != $userinfo['toon_type']) {
            Fn::writeLog("user/checklogin:解析失败, 参考：genre:" . $genre . "， getParams：" . json_encode($userinfo));
            Fn::outputToJson(self::ERR_PARAM, '用户类型值Toon_type不合格', []);
        }
        
        $getUser = User::getUserInfoByFeed($userinfo['visitor']['feed_id']);
        
        //生成票,以便下期优化
        $saveUserInfo = Act_Ticket::generateTicket($getUser['userId'], $getUser['schoolId'], $getUser['toonUid']);
        
        if (!$getUser) {
            Fn::outputToJson(self::ERR_PARAM, '获取用户信息失败', []);
        }
        
        $mcKey = $this->_mcUserInfoPrefix . $getUser['userId'];
        $userData = RedisClient::instance($this->_redisString)->get($mcKey);//先读取缓存
        
        if ($userData) {
            
            if (!is_numeric($userData)) {
                $row = unserialize($userData);
            }
            Fn::outputToJson('0', 'ok', $row);
            
        } else {
            $userDetails = User::getUserDetail($getUser['userId']);//获取用户详情信息
            
            if (!$userDetails) {
                Fn::outputToJson(self::ERR_PARAM, '获取用户详情信息失败', []);
            }
            
            $userData = array(
                'feed_id' => $userDetails['feedId'],
                'title' => $userDetails['name'],
                'subtitle' => $userDetails['subtitle'],
                'avatarId' => $userDetails['avatar'],
                'user_id' => $userDetails['userId'],
                'toon_uid' => $userDetails['toon_uid'],
                'u_no' => $userDetails['cardNo'],
                'toon_type' => isset($userinfo['toon_type']) ? $userinfo['toon_type'] : 0,
                'school_id' => $userDetails['school_id'],
                'school_name' => $userDetails['school']['name']
            );
            
            RedisClient::instance($this->_redisString)->setex($mcKey, $this->_indexMcTime, serialize($userData));
            $userData = RedisClient::instance($this->_redisString)->get($mcKey);
            if (false === $userData) {
                Fn::outputToJson(self::ERR_PARAM, "从缓存中获取用户信息失败");
            }
            
            if (!is_numeric($userData)) {
                $row = unserialize($userData);
            }
            
            Fn::outputToJson('0', 'ok', $row);
        }
        
    }
    /****************************************************************************************/
    /**
     * 生成验证用户的校验信息-Ticket
     * @return bool|string
     */
    public function checkTickAction()
    {
        //初始化变量
        $userId = $getCode = $errNum = $errUserNum = '';
        $resultArray = $userInfo = $getLoginUserInfo = [];
        
        $userId = intval($this->request->getQuery('user_id', 0));
        if (empty($userId)) {
            Fn::outputToJson(ResponseCode::NOT_EXIST, '获取用户信息失败', []);
        }
        
        $getCode = Fn::filterString($this->request->getQuery('code', ''));
        
        if (empty($getCode)) {
            Fn::outputToJson(ResponseCode::NOT_EXIST, '获取校验信息失败', []);
        }
        
        $genre = 'portal';
        $resultArray = Toon::getFeedIdByCode($genre, $getCode, $errmsg);
        
        $userInfo = json_decode($resultArray, true);
        
        $errNum = Fn::judgeDataRow($userInfo, 1);
        
        if (ResponseCode::NOT_EXIST == $errNum) {
            Fn::outputToJson($errNum, '获取请求参数失败', []);
        }
        if (ResponseCode::EXP_PARAM == $errNum) {
            Fn::outputToJson($errNum, '获取用户信息失败', []);
        }
        
        $getLoginUserInfo = User::getUserInfoByFeed($userInfo['visitor']['feed_id']);
        $errUserNum = Fn::judgeDataRow($getLoginUserInfo, 2);
        
        if (ResponseCode::EXP_PARAM == $errUserNum) {
            Fn::outputToJson($errUserNum, '获取用户基本信息失败', []);
        }
        
        $generateTicket = Act_Ticket::generateTicket($getLoginUserInfo['userId'], $getLoginUserInfo['schoolId'], $getLoginUserInfo['toonUid']);
        
        if (empty($generateTicket)) {
            Fn::outputToJson(ResponseCode::EXP_PARAM, '生成校验信息失败', []);
        }
        
        unset($errNum, $errUserNum, $resultArray, $userInfo, $getLoginUserInfo);
        return $generateTicket;
    }
    
    /**
     * 根据凭证获取用户详情
     * @return array|bool
     */
    public function getUserDetailByTicket()
    {
        $ticket = '';
        $ticket = Fn::filterString($this->request->getQuery('ticket', ''));
        
        if (empty($ticket)) {
            Fn::outputToJson(ResponseCode::NOT_EXIST, '获取用户校验凭证失败', []);
        }
        $userDetail = User::getUserByTicket($ticket);
        return $userDetail;
    }
}