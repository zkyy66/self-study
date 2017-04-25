<?php
/**
 * toon接口类库
 * @author lifuqiang
 *
 */
class Toon {
    
    const IM_MSG_TYPE_NOTICE            = 51;   //个人业务通知消息
    const IM_MSG_TYPE_SINGLE            = 52;   //单聊消息
    const IM_MSG_TYPE_MULTI             = 53;   //群聊消息
    const IM_MSG_TYPE_SYNC              = 54;   //同步业务
    const IM_MSG_TYPE_FRIEND            = 55;   //朋友圈消息
    
    const IM_HEADFLAG_AVATAR_NO         = 0;    //不显示头像
    const IM_HEADFLAG_AVATAR_LEFT       = 1;    //左边显示头像
    const IM_HEADFLAG_AVATAR_DOUBLE     = 2;    //显示左右两个头像
    
    const IM_FINISHFLAG_UNFINISHED      = 0;    //业务未完成
    const IM_FINISHFLAG_FINISHED        = 1;    //业务完成
    
    const IM_BUBBLEFLAG_UNREAD          = 0;    //冒泡
    const IM_BUBBLEFLAG_READ            = 1;    //不冒泡
    
    const IM_SHOWFLAG_NOTICE            = 2;    //通知中心显示
    
    
    
    /**
     * 获取toon平台通用参数
     * 
     * @return array
     */
    public static function getBaseToonParams( $appType ) {
        $prefix = 'site.info.toon.' . $appType;
        if (! $toonConfig = Yaf_Registry::get('config')->get($prefix)) {
            return array();
        }

        return $toonConfig->get('params')->toArray();
    }

    /**
     * @param $params
     * @return bool
     */
    private static function parseParams( &$params ) {
        
        $authAppSecret = $params['authAppSecret'];
        unset($params['authAppSecret'], $params['registerScend']);
        
        ksort($params);
        $combString = '';
        foreach ($params as $key=>$val) {
            $combString .= $key.$val;
        }
        $params['authSign'] = strtoupper(md5($authAppSecret.$combString.$authAppSecret));
        
        return true;
    }
    
    /**
     * 获取FeedInfo信息
     * @param array $feedIdList
     * @return array
     */
    public static function getListFeedInfo( array $feedIdList, $appType, &$errMsg ) {
        if (empty($feedIdList)) {
            $errMsg = '参数错误';
            return array();
        }
        
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.getListFeedInfo');
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);        
        
        if (! $queryUrl) {
            $errMsg = 'getListFeedInfo url 不存在';
            return array();
        }
        
        $baseParams = self::getBaseToonParams($appType);
        if ( empty($baseParams) ) {
            $errMsg = 'toon配置文件基础参数缺失';
            return array();
        }
        
        $params = array(
            'feedIdList' => json_encode($feedIdList),
        );
        
        $params = array_merge($params, $baseParams);
        self::parseParams($params);
        $result   = Curl::callWebServer($queryUrl, $params, 'post', 30, false, false);
        if ($result) {
            $resultData = json_decode($result, true);
            if (is_array($resultData) && isset($resultData['meta']['code']) && $resultData['meta']['code'] == 0) {
                return $resultData['data'];
            }
            
            $errMsg = isset($resultData['meta']['message']) ? $resultData['meta']['message'] : '网络错误';    
        } else {
            $errMsg = '网络错误';
        }
        
        Fn::writeLog("[getListFeedInfo]： " .$result, "/logs/toon_err.log");
             
        return array();
    }
    
    /**
     * 发送IM消息
     * @param string  $appType   //客户端类型
     * @param unknown $msgId
     * @param unknown $from
     * @param unknown $to
     * @param string $toClient
     * @param number $msgType  消息类型：50-群通知， 51-个人通知， 52-单聊消息， 53-群消息
     * @param unknown $content
     * @param unknown $appId
     * @param unknown $appCode
     * @param string $pushInfo
     * @param number $priority
     * @param unknown $errMsg
     */
    public static function sendImMsg( $appType, $fromFeedId, $toFeedId, $toClient, $content = array(), $msgType = 51, &$errMsg ) {

        if (! $fromFeedId || ! $toFeedId || ! $toClient || ! $content) {
            $errMsg = '参数错误';
            return false;
        }
        
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.sendmsg');

        if (! $queryUrl) {
            $errMsg = 'sendmsg url 不存在';
            return array();
        }
        
        $baseParams = self::getBaseToonParams($appType);

        if ( empty($baseParams) ) {
            $errMsg = 'toon配置文件基础参数缺失';
            return false;
        }
        
        //处理content数组
        if (! isset($content['headFlag']))   $content['headFlag'] = Toon::IM_HEADFLAG_AVATAR_DOUBLE;
        if (! isset($content['finishFlag'])) $content['finishFlag'] = Toon::IM_FINISHFLAG_UNFINISHED;
        if (! isset($content['bubbleFlag'])) $content['bubbleFlag'] = Toon::IM_BUBBLEFLAG_UNREAD;
        if (! isset($content['showFlag']))   $content['showFlag'] = Toon::IM_SHOWFLAG_NOTICE;
        if (! isset($content['expireTime'])) $content['expireTime'] = -1;   //-1表示永不失效
        if (! isset($content['bizNo']))      $content['bizNo'] = $appType . time();
            
        //通知消息
        $content['contentType'] = 6;   //表示通知消息
        
        $params = array(
            'msgid'     => Fn::getUuid(),
            'from'      => $fromFeedId,
            'to'        => $toFeedId,
            'toClient'  => $toClient,
            'pushInfo'  => '您收到一条活动通知',
            'content'   => json_encode($content),
            'msgType'   => $msgType,
            'priority'  => 1,
            'appid'     => $baseParams['authAppId'],
            'appcode'   => $baseParams['authAppSecret'],
        );
        
        $params = array_merge($params, $baseParams);
        self::parseParams($params);        
        
        $result   = Curl::callWebServer($queryUrl, $params, 'post', 30, false, false);
        
        if ($result) {
            $resultData = json_decode($result, true);
            if (is_array($resultData) && isset($resultData['status']) && $resultData['status'] == 0) {
                return true;
            }
            
            $errMsg = isset($resultData['message']) ? $resultData['message'] : '网络错误';
        } else {
            $errMsg = '网络错误';
        }
        
        Fn::writeLog("[sendImMsg]：result: " .var_export($result, true).'errMsg:'.$errMsg);
         
        return false;        
    }
    
    /**
     * 通过code获取用户feedId信息
     * @param unknown $code
     * @param unknown $errMsg
     */
    public static function getFeedIdByCode( $appType, $code, &$errMsg ) {
        if( empty( $code ) ) {
            $errMsg = 'code不能为空';
            return false;
        }
        Fn::writeLog( $code );
        //获取客户端配置
        $params = self::getBaseToonParams($appType);

        if ( empty( $params ) ) {
            $errMsg = '获取不到客户端配置信息';
            return false;
        }
        
        $key    = substr( md5( $params['authAppSecret'] ), 8, 8 );
        $res    =  rtrim( mcrypt_decrypt( MCRYPT_DES, $key, base64_decode( str_replace( ' ', '+',  $code ) ), MCRYPT_MODE_ECB ), "\x00..\x1F" );
        return $res;
    }
    
    /**
     * 获取应用AccessToken等信息
     * @param unknown $clientId
     * @param unknown $clientSecret
     * @param unknown $redirectUrl
     * @param unknown $code
     * @param unknown $errMsg
     */
    public static function getAccessToken( $clientId, $clientSecret, $redirectUrl, $code, &$errMsg ) {
        
        $postData = array(
            'client_id'         => $clientId,
            'client_secret'     => $clientSecret,
            'grant_type'        => 'authorization_code',
            'redirect_uri'      => $redirectUrl,
            'code'              => $code,
        );

        $queryUrl  = Yaf_Registry::get('config')->get('toon.apiurl.oauthToken');
        $queryUrl  = self::getOpenUrlByDomain($queryUrl, $errMsg);
        
        if (! $queryUrl) {
            return false;
        }
        
        $retData   = Curl::callWebServer($queryUrl, $postData, 'post', 30, false, false);
        if (! $retData) {
            $errMsg = '获取AccessToken失败';
            return false;
        }
        
        $retData = json_decode($retData, true);
        
        if (isset($retData['code']) && $retData['code'] == '10001') {
            $errMsg = 'code无效';
            
            return false;
        }
        
        return $retData;
    }
        
    /**
     * 注册应用
     * @param unknown $appType
     * @param unknown $code
     * @param unknown $errMsg
     * @return boolean|mixed  注册失败返回false， 注册成功返回用户的feedId
     */
    public static function registerApp( $appType, $feedId, $accessToken, &$errMsg ) {
        
        $baseParams = self::getBaseToonParams($appType);
        if ( empty($baseParams) ) {
            $errMsg = 'toon配置文件基础参数缺失';
            return false;
        }
        
        $prefix = 'toon.appregister.' . $appType;
        if (! $registerConfig = Yaf_Registry::get('config')->get($prefix)) {
            $errMsg = '应用安装参数缺失';
            
            return false;
        }
        
        $registerParams = $registerConfig->toArray(); 
        
        $registerParams['access_token'] = $accessToken;
        $registerParams['feedId']       = $feedId;
        $registerParams['appId']        = $baseParams['authAppId'];
        
        
        if (in_array($baseParams['registerScend'], [1, 2, 7])) {            
            $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.addRegisteredApp');
        } else {
            $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.addOrgRegisteredApp');
        }      
        
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);
        if (! $queryUrl) {
            return false;
        }
        
        $retData  = Curl::callWebServer($queryUrl, $registerParams, 'post', 30, false, false);
        
        if (! $retData) {
            $errMsg = '网络错误';
            return false;
        }
        
        Fn::writeLog("retData:".$appType.$queryUrl.$retData);
        
        $retData  = json_decode($retData, true);
        
        if (is_array($retData) && isset($retData['meta']['code']) && $retData['meta']['code'] == 0) {
            //注册成功
            
            return $feedId;
        }
        
        $errMsg = isset($retData['meta']['message']) ? $retData['meta']['message'] : '网络错误';
        return false;
    }
    
    /**
     * 管理员--注册应用
     * @param unknown $appType
     * @param unknown $companyId
     * @param unknown $accessToken
     * @param unknown $errMsg
     */
    public static function addCompanyOrgRegisteredApp( $appType, $companyId, $accessToken, &$errMsg ) {
        $baseParams = self::getBaseToonParams($appType);
        if ( empty($baseParams) ) {
            $errMsg = 'toon配置文件基础参数缺失';
            return false;
        }
    
        $prefix = 'toon.appregister.' . $appType;
        if (! $registerConfig = Yaf_Registry::get('config')->get($prefix)) {
            $errMsg = '应用安装参数缺失';
    
            return false;
        }
    
        $registerParams = $registerConfig->toArray();
    
        //$registerParams['access_token'] = $accessToken;
        $registerParams['companyId']    = $companyId;
        $registerParams['appId']        = $baseParams['authAppId'];

    
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.addCompanyOrgRegisteredApp');
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);
        
        if (! $queryUrl) {
            return false;
        }
        
        $queryUrl .= '?access_token='.$accessToken;
        $retData  = Curl::callWebServer($queryUrl, $registerParams, 'post', 30, true, false);
    
        if (! $retData) {
            $errMsg = '网络错误';
            return false;
        }
    
    
        $retData  = json_decode($retData, true);
    
        if (is_array($retData) && isset($retData['meta']['code']) && $retData['meta']['code'] == 0) {
            //注册成功
    
            return $retData['data']['appRegisterId'];
        }
    
        $errMsg = isset($retData['meta']['message']) ? $retData['meta']['message'] : '网络错误';
        return false;
    }
    
    /**
     * 查询一个或多个公司名片详情
     * @param unknown $accessToken  访问令牌
     * @param unknown $feedId  一个或多个公司名片feedId拼接成的字符串，逗号分隔	
     * @return boolean|mixed
     */
    public static function getOrgOauthListOrgCard( $accessToken , $feedId, &$errMsg ) {
    
        if (! $accessToken || ! $feedId) {
            $errMsg = '参数错误';
            return false;
        }
    
        $params = array(
            'access_token'  => $accessToken,
            'feedIdStr'     => $feedId,
        );
    
        
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.orgOauth.getListOrgCard');
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);
        
        if (! $queryUrl) {
            return false;
        }
    
        $retData  = Curl::callWebServer($queryUrl, $params, 'get', 10, false, false);
    
    
        if (! $retData) {
            $errMsg = '网络错误';
            return false;
        }
    
        $retData = json_decode($retData, true);
    
        if (is_array($retData) && isset($retData['meta']['code']) && $retData['meta']['code'] == 0) {
            //是组织名片
    
            return $retData['data'];
        }
    
        $errMsg = isset($retData['meta']['message']) ? $retData['meta']['message'] : '网络错误';
        return false;
    
    }
    
    /**
     * 查询一个或多个公司名片详情
     * @param unknown $accessToken
     * @param unknown $feedId
     * @return boolean|mixed
     */
    public static function getUserOauthListOrgCard( $accessToken , $feedId, &$errMsg ) {
    
        if (! $accessToken || ! $feedId) {
            $errMsg = '参数错误';
            return false;
        }
    
        $params = array(
            'access_token'  => $accessToken,
            'feedIdStr'     => $feedId,
        );
    
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.userOauth.getListOrgCard');
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);
        
        if (! $queryUrl) {
            return false;
        }
        
        $retData  = Curl::callWebServer($queryUrl, $params, 'get', 10, false, false);
    
        Fn::writeLog("[getListOrgCard] url={$queryUrl}?" . http_build_query($params). "\t" . $retData);
    
        if (! $retData) {
            $errMsg = '网络错误';
            return false;
        }
    
        $retData = json_decode($retData, true);
    
        if (is_array($retData) && isset($retData['meta']['code']) && $retData['meta']['code'] == 0) {
            //是组织名片
    
            return $retData['data'];
        }
    
        $errMsg = isset($retData['meta']['message']) ? $retData['meta']['message'] : '网络错误';
        return false;
    
    }
    
    /**
     * 查询一个或多个员工名片详情
     * @param unknown $accessToken
     * @param unknown $feedIdStr
     * @return boolean|mixed
     */
    public static function getStaffCard($accessToken, $feedIdStr, &$errMsg) {
    
        $params = array(
            'access_token' => $accessToken,
            'feedIdStr'    => $feedIdStr,
        );
    
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.userOauth.getListStaffCard');
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);
        if (! $queryUrl) {
            return false;
        }
        
        $retData  = Curl::callWebServer($queryUrl, $params, 'get', 10, false, false);
    
        if (! $retData) {
            $errMsg = '网络错误';
            return false;
        }
    
        $retData = json_decode($retData, true);
        if (is_array($retData) && isset($retData['meta']['code']) && $retData['meta']['code'] == 0) {
    
            return $retData['data'];
        }
    
        $errMsg = isset($retData['meta']['message']) ? $retData['meta']['message'] : '网络错误';
        return false;
    }
    
    /**
     * 根据公司ID，查询公司名片
     * @param unknown $accessToken
     * @param unknown $comId
     * @param unknown $errMsg
     */
    public static function getListOrgByComId( $accessToken, $comId, &$errMsg ) {
        if (empty($accessToken) || empty($comId)) {
            $errMsg = '参数错误';
            return false;
        }
    
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.orgOauth.getListOrgByComId');
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);
        
        if (! $queryUrl) {
            return false;
        }
        
        $params   = array(
            'access_token'  => $accessToken,
            'comId'         => $comId,
        );
    
        $retData  = Curl::callWebServer($queryUrl, $params, 'get', 30, false, false);
    
        if (! $retData) {
            $errMsg = '网络错误';
            return false;
        }
    
        $retData = json_decode($retData, true);
        if (is_array($retData) && isset($retData['meta']['code']) && $retData['meta']['code'] == 0) {
    
            return $retData['data'];
        }
    
        $errMsg = isset($retData['meta']['message']) ? $retData['meta']['message'] : '网络错误';
        return false;
    
    }
    
    
    /**
     * 通过POI信息获取对应公司资源池信息
     * @param unknown $poi poi信息Json字符串
     * @param string $errMsg 错误信息
     */
    public static function resourcePoolApi( $poi, &$errMsg = '' ) {
    
        if( ! $poiAry = json_decode( $poi, true ) ) {
            $errMsg = 'poi 信息有误！';
            return false;
        }
    
        $params['poi_id']  = $poiAry['uid'];
        $params['poi_json'] = $poi;
    
        if (! $registerConfig = Yaf_Registry::get('config')->get( 'toon.resourcePoll' ) ) {
            $errMsg = '应用安装参数缺失';
    
            return false;
        }
        $params['appKey']     = $registerConfig['appKey'];
        $appSecret  = $registerConfig['appSecret'];
        $params['timestamp']  = Fn::getMillisecond();
        ksort( $params );
        $str    =   [];
        foreach ( $params as $k => $v ) {
            $str[]= $k . '=' . $v;
        }
        
        $params['signature']    = hash_hmac( 'md5',  implode( '&', $str ), $appSecret );
        $paramsStr  = http_build_query( $params );
        $requestUrl = $registerConfig['requestUrl'] . '/service/getResId/interface';
        $res    = Fn::curlPost( $requestUrl , $paramsStr, 30, ["content-type: application/x-www-form-urlencoded;charset=UTF-8"] );
        if( !$res ) return false;
        Fn::writeLog( 'resourcePoolApi-return:' . $res );        
        $res    = json_decode( $res, true );
        if( isset( $res['code'] ) && $res['code'] == 301 ) {
            return $res['jsonData'];
        }
        else{
            Fn::writeLog( json_encode( 'resourcePoolApi:' . json_encode( $res ) ) );
            return false;
        }
    }
    
    /**
     * 根据appid及plaintext生成code码
     * @param unknown $appId
     * @param array $data
     * @param unknown $errMsg
     * @return boolean|string
     */
    public static function generateCypherText( $appType, $appId, array $data, &$errMsg) {
        
        if (! $appType || ! $appId || empty($data)) {
            $errMsg = '参数错误';
            return false;
        }
        
        $baseParams = self::getBaseToonParams($appType);
       
        if ( empty($baseParams) ) {
            $errMsg = 'toon配置文件基础参数缺失';
            return false;
        }
        
        $params = [
            'appId'     => $appId,
            'plainText' => json_encode($data),
        ];
        
        $params = array_merge($params, $baseParams);
        self::parseParams($params);
        
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.generateCypherText');
        
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);
        
        if (! $queryUrl) {
            return false;
        }
        
        $retData  = Curl::callWebServer($queryUrl, $params, 'get', 10, false, false);
        
        if (! $retData) {
            $errMsg = '网络错误';
            return false;
        }
        
        Fn::writeLog($retData);
        
        $retData  = json_decode($retData, true);
        
        if (is_array($retData) && isset($retData['meta']['code']) && $retData['meta']['code'] == 0) {
            //返回code
            return $retData['data'];
        }
        
        $errMsg = isset($retData['meta']['message']) ? $retData['meta']['message'] : '网络错误';
        
        return false;
    }
    
    /**
     * 域名路由转换
     * @param unknown $domain
     * @param unknown $errMsg
     * @return boolean|mixed
     */
    public static function getOpenUrlByDomain($url, &$errMsg) {
        if (empty($url)) {
            $errMsg = '域名参数错误';
            return false;
        }
        
        $domain = parse_url($url, PHP_URL_HOST);
        if (! $domain) {
            $errMsg = '域名参数错误';
            return false;
        }
        
        $param = [
            'domain' => $domain
        ];
        $queryUrl = Yaf_Registry::get('config')->get('toon.apiurl.openurl');
        
        $retData  = Curl::callWebServer($queryUrl, $param, 'get', 10, false, false);
        
        if (! $retData) {
            $errMsg = '网络错误';
            Fn::writeLog($domain, "/logs/toon_domain_err.log");
            return false;
        }
        
        
        $retData  = json_decode($retData, true);
        
        if (is_array($retData) && isset($retData['code']) && $retData['code'] == 0) {
            //返回code
            return str_replace($domain, $retData['data'], $url);
        }
        
        $errMsg = isset($retData['message']) ? $retData['message'] : '网络错误';
        Fn::writeLog("{$domain} \t" . var_export($retData, true), "/logs/toon_domain_err.log");
        return false;
        
    }

    /**
     * 验证密钥
     * @param $appType
     * @return array|string
     */
    public static function checkSign($appType) {
        $prefix = 'site.info.toon.' . $appType;
        
        if (! $toonConfig = Yaf_Registry::get('config')->get($prefix)) {
            return array();
        }
    
        $authConfig = $toonConfig->get('params')->toArray();
       
        $authAppSecret = $authConfig['authAppSecret'];
        unset($authConfig['callbackUrl'],$authConfig['authAppSecret'],$authConfig['registerScend']);
    
        ksort($authConfig);
        $combString = '';
    
        foreach ($authConfig as $key => $val) {
            $combString .= $key.$val;
        }
        
        $authKey = strtoupper( md5( $authAppSecret.$combString.$authAppSecret ) );
        
        return $authKey;
    }
    
    /**
     * 创建活动时，添加到动态
     * @param string $feedId 发布者feedId
     * @param string  $linkUrl // 活动地址
     * @param int $diffusionType // 传播类型 1 好友 2 群成员 3 同事  (如果是个人发的，就是好友，如果是群组活动，就是群成员，如果是员工名片发的，就是同事)
     * @param array $rssContentArr  // 动态展示内容的数组
     * @param array $sharContentArr // 分享内容的数组 array('title', 'text', 'imgurl')
     * @param string $apptype 
     * @param string $errMsg 错误信息
     * @return boolean true|false
     */
    public static function addToTrends($feedId, $linkUrl, $diffusionType, $rssContentArr, $shareContentArr, $appType='portal', &$errMsg)
    {
        $baseParams = self::getBaseToonParams($appType);

        if ( empty($baseParams) ) {
            $errMsg = 'toon配置文件基础参数缺失';
            return false;
        }
        unset($baseParams['callbackUrl']);
        
        self::parseParams($baseParams);

        $queryUrl = Yaf_Registry::get('config')->get('toon.apiUrl.addToTrends');
        if (!$queryUrl) {
            Fn::writeLog('域名获取失败');
            return false;
        }
        $queryUrl = $queryUrl.'/open/addTrends?'.http_build_query($baseParams);
        $queryUrl = self::getOpenUrlByDomain($queryUrl, $errMsg);
        if (!$queryUrl) {
            Fn::writeLog('域名参数错误');
            return false;
        }

        $params = array(
            'feedId'        => $feedId, // 作者feedId
            'appId'         => $baseParams['authAppId'], // 应用Id（toon应用或者第三方应用在toon平台开发者对应的应用Id）
            'channelId'     => '', // 数据聚合Id 可以是群组的feedId
            'diffusionId'   => $feedId, // 传播Id，可以是群组Id、名片feedId、组织Id
            'diffusionType' => $diffusionType, // 传播类型 1 好友 2 群成员 3 同事  (如果是个人发的，就是好友，如果是群组活动，就是群成员，如果是员工名片发的，就是同事)
            'appInstanceId' => 0, // 应用实例Id
            'linkUrl'       => $linkUrl, // 链接地址
            'rssContent'    => $rssContentArr, // rss内容
            'shareInfo' => array( // 分享对象
                    'shareMessageText'=>'', // 消息分享的文字
                    
                    // 图片
                    'shareToonCircleImageUrl'   => $shareContentArr['imgUrl'], // toon动态分享图片地址
                    'shareToonImageUrl'         => $shareContentArr['imgUrl'], // toon分享图片地址
                    'shareWeChatImageUrl'       => $shareContentArr['imgUrl'], // 微信分享图片地址
                    'shareWeChatCircleImageUrl' => $shareContentArr['imgUrl'], // 微信朋友圈分享标题
                    'shareWeiBoImageUrl'        => $shareContentArr['imgUrl'], // 微博分享图片
                    'shareQQImageUrl'           => $shareContentArr['imgUrl'], // QQ分享图片地址
                    'shareQQSpaceImageUrl'      => $shareContentArr['imgUrl'],// QQ空间分享图片地址
                    // url设置
                    'shareToonCircleUrl'   => $linkUrl, // toon动态分享跳转地址
                    'shareToonUrl'         => $linkUrl, // toon分享跳转地址
                    'shareWeChatUrl'       => $linkUrl, // 微信分享跳转地址
                    'shareWeChatCircleUrl' => $linkUrl, // 微信朋友圈分享跳转地址
                    'shareQQUrl'           => $linkUrl, // QQ分享跳转地址
                    'shareQQSpaceUrl'      => $linkUrl, // QQ空间分享跳转地址
                    // title设置
                    'shareToonCircleTitle'   => $shareContentArr['title'], // toon动态分享标题
                    'shareToonTitle'         => $shareContentArr['title'], // toon分享标题
                    'shareWeChatTitle'       => $shareContentArr['title'], // 微信分享标题
                    'shareWeChatCircleTitle' => $shareContentArr['title'], // 微信朋友圈分享标题
                    'shareQQTitle'           => $shareContentArr['title'], // QQ分享文本
                    'shareQQSpaceTitle'      => $shareContentArr['title'], // QQ空间分享标题
                    // text 设置
                    'shareToonText'    => $shareContentArr['text'], // toon分享文本内容
                    'shareWeChatText'  => $shareContentArr['text'], // 微信分享文本
                    'shareWeiBoText'   => $shareContentArr['text'].":".$linkUrl, // 微博分享文本
                    'shareQQText'      => $shareContentArr['text'], // QQ分享文本
                    'shareQQSpaceText' => $shareContentArr['text'], // QQ空间分享文本
            ),
            'showType'=>2, // 展示类型: 2 活动 6 群主博客
            'srcType'=>2, // 源类型 : 1 社交圈 2 活动 3 群组
        );

         $result   = Curl::callWebServer($queryUrl, $params, 'post', 30, true, false);
        if (!$result) {
            Fn::writeLog('动态日志：请求地址错误');
            return false;
        }
            
         $result = json_decode($result,true);
    
        if (is_array($result)) {
            if (!isset($result['meta']['code']) && !isset($result['meta']['message'])) {
                Fn::writeLog("返回结果：缺少code状态码或者返回信息");
            } else if (0 == $result['meta']['code'] && 'success' == $result['meta']['message']){
                return true;
            }
        
        } else {
            Fn::writeLog('返回结果：结果失败');
            return false;
        }


    }
}