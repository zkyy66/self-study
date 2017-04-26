<?php
/**
 * @description 用户
 * @author by Yaoyuan.
 * @version: 2017-03-06
 * @Time: 2017-03-06 18:41
 */
class User {
    
    private static $_appId;
    private static $_apiUrl;
    private static $_resquestApi;
    
    /**
     * 根据Ticket获取用户详细信息
     */
    public static function getUserByTicket ($string) {
        
        if (empty($string)) {
            return false;
        }
        $ticketInfo = Act_Ticket::getContentByTicket($string);
        
        if (!$ticketInfo) {
            Fn::writeLog("User/getUserByTicket:非法请求");
            return false;
        }
        
        $userDetail = self::getUserDetail($ticketInfo['userId']);
        
        if (!$userDetail || empty($userDetail['userId'])) {
            Fn::writeLog("User/getUserByTicket:获取用户详情信息失败");
            return false;
        }
        if (!is_array($userDetail)) {
            Fn::writeLog("User/getUserByTicket:返回数据格式错误");
            return false;
        }
        
        $userData = array(
            'feed_id' => $userDetail['feedId'],
            'title' => $userDetail['name'],
            'subtitle' => $userDetail['subtitle'],
            'avatarId' => $userDetail['avatar'],
            'user_id' => $userDetail['userId'],
            'toon_uid' => $userDetail['toon_uid'],
            'u_no' => $userDetail['cardNo'],
            'school_id' => $userDetail['school_id'],
            'school_name' => $userDetail['school']['name']
        );
        return $userData;
    }
    /**
     * 获取子应用配置
     * @param $plugin
     * @return array
     */

    public static function getAppConfig ($plugin) {
        $appConfig = Fn::getAppChildConfig();
        
        if (!$appConfig) {
            return false;
        }
        self::$_appId = $appConfig['appId'];
        self::$_apiUrl = $appConfig['feedApiUrl'];
        self::$_resquestApi = $plugin;
        $param = array(
            'appId' => self::$_appId,
            'apiUrl' => self::$_apiUrl,
            'requestApi' => self::$_resquestApi,
            'time' => Fn::getMillisecond()
        );
        return $param;
    }
    /**
     * 根据feedId获取用户id和学校ID
     * @param $feed_id
     * @return bool
     */
    public static function getUserInfoByFeed($feed_id) {
        if (!$feed_id) {
            Fn::writeLog('User/getUserInfoByFeed:'.$feed_id);
            return false;
        }
        $param = self::getAppConfig('v1-plugin-feed');
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/getUserInfoByFeed:获取配置文件失败：".var_export($param,true));
            return false;
        }
    
        $signatureParam = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'feedId' => $feed_id,
            'requestApi' => $param['requestApi']
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);
        
        //根据Feed_id获取用户信息接口
        $feedApiUrl = $param['apiUrl'].'/api/route/index';
        $feedParam = array(
            'appId' => $param['appId'],
            'appSign' => $appSign,
            'time' => $param['time'],
            'feedId' => $feed_id,
            'requestApi' => $param['requestApi']

        );
        $result = array();
        try {
            $result  = Curl::callWebServer($feedApiUrl, json_encode($feedParam), 'post', 5, true, false);
            $result = json_decode($result, true);
            Fn::writeLog("User/getUserInfoByFeed:解析返回值查看：".var_export($result,true));
            if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
                return $result['data'];
            }
            return false;
        } catch (Exception $e) {
            Fn::writeLog('User/getUserInfoByFeed:'.var_export($e->getMessage(),true));
            return $result;
        }
        
    }
    
    /**
     * 获取用户详细信息
     * @param $user_id
     * @param null $viewUserId
     * @return bool
     */
    public static function getUserDetail($user_id,$viewUserId=NULL) {
        if (!$user_id) {
            return false;
        }
        $param = self::getAppConfig('index-user-detail');
        
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/getUserDetail:获取配置文件失败：".var_export($param,true));
            return false;
        }
        $signatureParam = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'loginUserId' => $user_id,
            'requestApi' => $param['requestApi'],
            'userId' => $viewUserId ? $viewUserId : 0
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);
        
        $feedApiUrl = $param['apiUrl'].'/api/route/index';
        $feedParam = array(
            'appId' => $param['appId'],
            'appSign' => $appSign,
            'time' => $param['time'],
            'loginUserId' => $user_id,
            'requestApi' => $param['requestApi'],
            'userId' => $viewUserId ? $viewUserId : 0
    
        );
        $result = array();
        
        try {
            $result  = Curl::callWebServer($feedApiUrl, json_encode($feedParam), 'post', 5, true, false);
            
            $result = json_decode($result, true);
    
            Fn::writeLog('User/getUserDetail:'.var_export($result,true));
            
            if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
                return $result['data'];
            }
            
            return false;
        } catch (Exception $e) {
            Fn::writeLog('User/getUserDetail:错误信息：'.var_export($e->getMessage(),true));
            return $result;
        }

    }
    
    /**
     * 心动接口
     * @param $user_id
     * @param $type
     * @param $ac_id
     * @return bool
     */
    public static function heartBeat($user_id,$type,$ac_id) {
        
        if (empty($user_id) || !isset($type) || empty($ac_id)) {

            Fn::writeLog('User/heartBeat:用户ID：'.var_export($user_id,true).'--类型ID：'.var_export($type,true).'--活动ID：'.var_export($ac_id,true));
            return false;
        }
        $param = self::getAppConfig('index-love-love');
        
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/heartBeat:获取配置文件失败：".var_export($param,true));
            return false;
        }
        
        $signatureParam = array(
            'object_type' => 1,
            'appId' => $param['appId'],
            'time' => $param['time'],
            'type' => $type,
            'object_id' => $ac_id,
            'userId' => $user_id,
            'requestApi' => $param['requestApi']
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);
        $heartApiUrl = $param['apiUrl'].'/api/route/index';
        $dataRow = array(
            'object_type' => 1,
            'appId' => $param['appId'],
            'appSign' => $appSign,
            'time' => $param['time'],
            'type' => $type,
            'object_id' => $ac_id,
            'userId' => $user_id,
            'requestApi' => $param['requestApi']
        );
        
        $result = array();
        try {
            $result  = Curl::callWebServer($heartApiUrl, json_encode($dataRow), 'post', 5, true, false);
            
            $result = json_decode($result, true);
    
            Fn::writeLog('User/heartBeat:'.var_export($result,true));
            
            if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
                return true;
            }
            return false;
        } catch (Exception $e) {
            Fn::writeLog('User/heartBeat:错误信息：'.var_export($e->getMessage(),true));
            return $result;
        }

    }
    
    /**
     * 判断当前登录用户是否对资源心动接口
     */
    public static function getUserIsFollow($ac_id,$userId) {
    
        if (empty($ac_id) || empty($userId)) {
            Fn::writeLog('User/getUserIsFollow:用户ID：'.var_export($userId,true).'--活动ID：'.var_export($ac_id,true));
            return false;
        }
        $param = self::getAppConfig('index-love-islove');
        
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/getUserIsFollow:获取配置文件失败：".var_export($param,true));
            return false;
        }
        
        $signatureParam = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'userId' => $userId,
            'object_type' => 1,
            'object_id' => $ac_id
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);
    
        $heartApiUrl = $param['apiUrl'].'/api/route/index';
        $dataRow = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'userId' => $userId,
            'object_type' => 1,
            'object_id' => $ac_id,
            'appSign' => $appSign
        );
    
        $result = array();
        
        try {
            $result  = Curl::callWebServer($heartApiUrl, json_encode($dataRow), 'post', 5, true, false);
            $result = json_decode($result, true);
            
            Fn::writeLog('User/getUserIsFollow:'.var_export($result,true));
            
            if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
                return $result['data'];
            }
            
            return false;
        } catch (Exception $e) {
            Fn::writeLog('User/getUserIsFollow:错误信息：'.var_export($e->getMessage(),true));
            return $result;
        }
        
    }
    
    /**
     * 根据活动获取心动人员列表
     * @param $ac_id
     * @param $user_id
     * @param $page
     * @return bool
     */
    public static function heartBeatList($ac_id,$user_id,$page = NULL,$pageLimit = NULL) {
        
        $page = intval($page) ? intval($page) : 1;
        $pageLimit = intval($pageLimit) ? intval($pageLimit) : 10;
        //$offset = ($page - 1) * $pageLimit;
        
        
        if (empty($ac_id) || empty($user_id)) {
            Fn::writeLog('User/heartBeatList:活动ID：'.var_export($ac_id,true).'--用户ID：'.var_export($user_id,true));
            return false;
        }
    
        $param = self::getAppConfig('index-love-listuser');
    
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/heartBeatList:获取配置文件失败：".var_export($param,true));
            return false;
        }

        //签名参数
        $signatureParam = array(
            'appId' => $param['appId'],
            'userId' => $user_id,
            'page' => $page,
            'pageLimit' => $pageLimit,
            'object_type' => 1,
            'object_id' => $ac_id,
            'time' => $param['time'],
            'requestApi' => $param['requestApi']
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);
        
        $heartApiUrl = $param['apiUrl'].'/api/route/index';
        $dataRow = array(
            'object_type' => 1,
            'appId' => $param['appId'],
            'appSign' => $appSign,
            'time' => $param['time'],
            'object_id' => $ac_id,
            'userId' => $user_id,
            'requestApi' => $param['requestApi'],
            'page' => $page,
            'pageLimit' => $pageLimit
        );
        
        $result  = Curl::callWebServer($heartApiUrl, json_encode($dataRow), 'post', 5, true, false);
        
        Fn::writeLog('User/heartBeatList:'.var_export($result,true));
    
        $result = json_decode($result, true);

        if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
            
            if (empty($result['data'])) {
                return array();
            }

            
            foreach ($result['data']['dataList'] as $key => $val) {
                $userInfo = self::getUserDetail($val['userId']);
                $result['data']['dataList'][$key] = array(
                    'userId' => $val['userId'],
                    'feedId' => $val['feedId'],
                    'name' => $val['name'],
                    'subtitle' => $val['subtitle'],
                    'avatar' => $val['avatar'],
                    'is_follow' => $val['is_follow'],
                    'school_name' => $val['school']['name'],
                    'toon_uid' => $userInfo['toon_uid']
                );
            }
            $result['total'] = $result['data']['total'];
            return $result['data'];
        }
        return false;
    }
    /**
     * 根据用户ID查询心动活动列表
     */

    public static function getheartListByUserId ($user_id,$page=NULL,$pageLimit=NULL) {

        if (empty($user_id)) {
            Fn::writeLog('User/getheartListByUserId:活动ID：'.var_export($user_id,true));
            return false;
        }
        $param = self::getAppConfig('index-love-list');
    
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/getheartListByUserId:获取配置文件失败：".var_export($param,true));
            return false;
        }

        $page = intval($page) ? intval($page) : 1;
        $pageLimit = $pageLimit ? $pageLimit : 10;
        //签名参数
        $signatureParam = array(
            'appId' => $param['appId'],
            'userId' => $user_id,
            'page' => $page,
            'pageLimit' => $pageLimit,
            'object_type' => 1,
            'time' => $param['time'],
            'requestApi' => $param['requestApi']
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);

    
        $heartApiUrl = $param['apiUrl'].'/api/route/index';
        $dataRow = array(
            'object_type' => 1,
            'appId' => $param['appId'],
            'appSign' => $appSign,
            'time' => $param['time'],
            'userId' => $user_id,
            'requestApi' => $param['requestApi'],
            'page' => $page,
            'pageLimit' => $pageLimit
        );
        $result  = Curl::callWebServer($heartApiUrl, json_encode($dataRow), 'post', 5, true, false);
    
        Fn::writeLog('User/getheartListByUserId:'.var_export($result,true));
        
        $result = json_decode($result, true);
        
        if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
            return $result['data'];
        }
        return false;
    }
    /**
     * 关注接口
     * @param $user_id
     * @param $toUser_id
     * @param $type
     * @return bool
     */
    
    public static function attentionUser ($user_id,$toUser_id,$type) {
        
        if (empty($user_id) || empty($toUser_id) || !isset($type)) {

            Fn::writeLog('User/attentionUser:用户ID：'.var_export($user_id,true).'--被关注用户ID：'.var_export($toUser_id,true));
            return false;
        }
        $param = self::getAppConfig('index-follow-follow');
    
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/attentionUser:获取配置文件失败：".var_export($param,true));
            return false;
        }

        //签名参数
        $signatureParam = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'loginUserId' => $user_id,
            'toUserId' => $toUser_id,
            'type' => $type ? $type : 0

        );
        $appSign = Fn::generateSignature('signature',$signatureParam);
        $heartApiUrl = $param['apiUrl'].'/api/route/index';
        $dataRow = array(
            'appId' => $param['appId'],
            'appSign' => $appSign,
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'loginUserId' => $user_id,
            'toUserId' => $toUser_id,
            'type' => $type ? $type : 0
        );
        
        $result  = Curl::callWebServer($heartApiUrl, json_encode($dataRow), 'post', 5, true, false);
        
        Fn::writeLog('User/attentionUser:'.var_export($result,true));
    
        $result = json_decode($result, true);
        
        if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
            return $result['meta']['code'];
        } else if (2000 == $result['meta']['code']){
            return $result['meta']['code'];
        }
        return false;
    }
    
    /**
     * 根据用户uid获取关注好友列表接口
     * @param $loginUserId
     * @param null $userId
     * @param null $page
     * @param null $pageLimit
     * @return bool
     */
    public static function attentionUserList ($loginUserId,$userId=NULL,$page=NULL,$pageLimit=NULL) {
        
        $page = intval($page) ? intval($page) : 1;
        $pageLimit = intval($pageLimit) ? intval($pageLimit) : 10;
        
        if (empty($loginUserId) ) {
            Fn::writeLog('User/attentionUserList:当前用户ID：'.var_export($loginUserId,true));
            return false;
        }
        
        $param = self::getAppConfig('index-follow-listfollow');
    
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/attentionUserList:获取配置文件失败：".var_export($param,true));
            return false;
        }

    
        //签名参数
        $signatureParam = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'loginUserId' => $loginUserId,
            'userId' => intval($userId) ? intval($userId) : 0,
            'page' => $page,
            'pageLimit' => $pageLimit
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);//生成签名
        
        $heartApiUrl = $param['apiUrl'].'/api/route/index';
        
        $dataRow = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'appSign' => $appSign,
            'loginUserId' => $loginUserId,
            'userId' => intval($userId) ? intval($userId) : 0,
            'page' => $page,
            'pageLimit' => $pageLimit
        );
    
        $result  = Curl::callWebServer($heartApiUrl, json_encode($dataRow), 'post', 5, true, false);
    
        Fn::writeLog('User/attentionUserList:'.var_export($result,true));
    
        $result = json_decode($result, true);
        
        if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
            return $result['data'];
        }
        return false;
    }

    
    /**
     * 校验用户toon_uid 是否在业务中重复报名
     * @param $loginUserId
     * @param null $uids
     * @return bool
     */
    public static function isRepetitionByUserId ($loginUserId,$uids=NULL) {
        
        if (empty($loginUserId)) {
            Fn::writeLog("User/isRepetitionByUserId:当前用户ID：".var_export($loginUserId,true).";ID串：".var_export($uids,true));
            return false;
        }
        $param = self::getAppConfig('index-user-chkuser');
    
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/isRepetitionByUserId:获取配置文件失败：".var_export($param,true));
            return false;
        }

        //签名参数
        $signatureParam = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'loginUserId' => $loginUserId,
            'uids' => $uids ? $uids : '',

        );
        $appSign = Fn::generateSignature('signature',$signatureParam);//生成签名
        
        $heartApiUrl = $param['apiUrl'].'/api/route/index';
        $dataRow = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'appSign' => $appSign,
            'loginUserId' => $loginUserId,
            'uids' => $uids ? $uids : '',
        );

        $result  = Curl::callWebServer($heartApiUrl, json_encode($dataRow), 'post', 5, true, false);
       
        Fn::writeLog('User/isRepetitionByUserId:'.var_export($result,true));
    
        $result = json_decode($result, true);
    
        if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
            return $result['data'];
        }
        return false;
    }
    
    /**
     *批量获取用户
     */
    public static function getUserList ($loginUserId,$userIdStr) {
        
        if (empty($userIdStr)) {
            Fn::writeLog("User/getUserList:用户ID：".var_export($userIdStr,true));
            return false;
        }
        $param = self::getAppConfig('index-user-getuserlist');
    
        if (!$param || empty($param['appId']) || empty($param['time']) || empty($param['requestApi'])) {
            Fn::writeLog("User/getUserList:获取配置文件失败：".var_export($param,true));
            return false;
        }

        //签名参数
        $signatureParam = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'loginUserId' => $loginUserId,
            'uids' => $userIdStr ? $userIdStr : '',
    
        );
        $appSign = Fn::generateSignature('signature',$signatureParam);//生成签名
        $heartApiUrl = $param['apiUrl'].'/api/route/index';
        $dataRow = array(
            'appId' => $param['appId'],
            'time' => $param['time'],
            'requestApi' => $param['requestApi'],
            'appSign' => $appSign,
            'loginUserId' => $loginUserId,
            'uids' => $userIdStr ? $userIdStr : '',
        );
        $result  = Curl::callWebServer($heartApiUrl, json_encode($dataRow), 'post', 5, true, false);
    
        Fn::writeLog('User/isRepetitionByUserId:'.var_export($result,true));
    
        $result = json_decode($result, true);
    
        if ($result && isset($result['meta']['code']) && $result['meta']['code'] == 0) {
            return $result['data'];
        }
        return false;
    }
}