<?php
/**
 * @description 讨论区
 * @author by Yaoyuan.
 * @version: 2017-02-28
 * @Time: 2017-02-28 11:23
 */
class CommentController extends Controller {
    
    protected $_commentAppId;
    protected $_commentSecret;
    protected $_commentToken;
    protected $_apiUrl;
    /**
     * 初始化
     */
    public function init() {
        header("Access-Control-Allow-Origin:*");
        parent::init();

        $commentConfigArray    = Fn::getCommentConfig();//获取评论点赞配置
        
        $this->_commentAppId  = $commentConfigArray['appID'];
        $this->_commentAppKey = $commentConfigArray['appKey'];
        $this->_apiUrl         = $commentConfigArray['apiUrl'];
        $this->_commentToken  = $this->generateCommentTokenAction();
        
        
    }
    
    /**
     * 生成Token以便验证
     */
    public function generateCommentTokenAction () {
        $time =  date('YmdH');//时间精确到小时
        if (!$this->_commentAppKey) {
            Fn::writeLog('Comment/generateCommentToken缺少令牌');
            return false;
        }
        $ua = '';
        Fn::writeLog($this->_commentAppKey . $ua . $time . $this->_commentAppKey);
        $tokenString = md5($this->_commentAppKey . $ua . $time . $this->_commentAppKey);
       
        Fn::writeLog('generateCommentToken: '.$tokenString);
        
        return $tokenString;
    }
    
    /**
     * 发布评论
     * @param int ac_id
     * @param int userId
     * @param varchar content
     * @return bool
     */
    public function addAction () {
        $post = file_get_contents('php://input');
        
        $info = json_decode($post, true);
        
        if (empty($post)) {
            Fn::outputToJson(self::ERR_PARAM,"缺少必要参数");
        }
        if (empty($info['ac_id'])) {
            Fn::outputToJson(self::ERR_PARAM,"缺少活动ID");
        }
        
        if (empty($info['userId'])) {
            Fn::outputToJson(self::ERR_PARAM,"缺少用户ID");
        }
        if (empty($info['content'])) {
            Fn::outputToJson(self::ERR_PARAM,"缺少评论内容");
        }
    
        $apiUrl = $this->_apiUrl;
        $appId  = $this->_commentAppId;
        $token  = $this->_commentToken;
        
        if (!$appId || !$apiUrl || !$token) {
            Fn::writeLog('Comment/add： 参数-appid:'.$appId.'-token:'.$token.'-apiurl:'.$apiUrl);
            Fn::outputToJson(self::ERR_PARAM,"缺少验证参数");
        }
        
        $ac_id = intval($info['ac_id']);
        $userId = intval($info['userId']);
        $content = Fn::filterString($info['content']);
        
        //接口URL
        $url = $apiUrl.'/v1/comment/';
        $dataRow = array(
            'appId' => intval($appId),
            'Token' => $token,
            'subjectId' => $ac_id,
            'userId' => $userId,
            'content' => $content,
            'toUserId' => isset($info['toUserId']) ? intval($info['toUserId']) : 0,
            'toId' => isset($info['toId']) ? intval($info['toId']) : 0
        );
        $result  = Curl::callWebServer($url, $dataRow, 'post', 5, true, false);
       
        Fn::writeLog('Comment/add：url-'.$url.', 参数-'.var_export($dataRow, true).', 返回值-'.var_export($result, true));
        
        $result = json_decode($result, true);
        
        if ($result && isset($result['Code']) && $result['Code'] == 0) {
            Fn::outputToJson(0,'ok',[]);
        }
    
        Fn::outputToJson(self::ERR_PARAM,"发布失败",[]);
    }
   
    /**
     * 评论列表
     * @param int ac_id
     * @param int userId
     * @return array
     */
    public function getCommentListAction () {
        $getParams = $this->request->getQuery();
        
        if (empty($getParams)) {
            Fn::outputToJson(self::ERR_PARAM,"缺少必要参数");
        }
        if (empty($getParams['ac_id'])) {
            Fn::outputToJson(self::ERR_PARAM,"缺少活动ID");
        }
        if (empty($getParams['userId'])) {
            Fn::outputToJson(self::ERR_PARAM,"缺少用户ID");
        }
        if (empty($getParams['user_id'])) {
            Fn::outputToJson(self::ERR_PARAM,"缺少用户信息");
        }
        
        $ac_id = intval($getParams['ac_id']);
        $loginUserID = intval($getParams['user_id']);
        
        $appId  = $this->_commentAppId;
        $token  = $this->_commentToken;
        $apiUrl = $this->_apiUrl;
        
        $userId = intval($getParams['userId']);
        $offset = intval($getParams['offset']) ? intval($getParams['offset']) : 1;
        
        $limit = 10;
        $offset = ($offset - 1) * $limit;
        
        if (!$appId || !$token || !$apiUrl) {
            Fn::writeLog('Comment/getCommentList： 参数-appid:'.$appId.'-token:'.$token);
            Fn::outputToJson(self::ERR_PARAM,"缺少验证参数");
        }
    
        $url = $apiUrl.'/v1/comment/';
        $url  = $url.'?appId='.$appId.'&subjectId='.$ac_id.'&userId='.$userId.'&token='.$token.'&offset='.$offset.'&limit='.$limit;
       
        $result  = Curl::callWebServer($url, [], 'get', 5, true);
        $result = json_decode($result, true);
        
        Fn::writeLog('Comment/getCommentList返回结果：'.var_export($result, true));
        if (!$result || !isset($result['Code']) || $result['Code'] != 0) {
            Fn::outputToJson(0,'ok',[]);
        }
       
        $list = $result['Res'];

        
        if (empty($list)) {
            Fn::outputToJson(0,'ok',[]);
        }
        
        foreach ($list as $key => $value) {
            
            $userInfo = User::getUserDetail($loginUserID,$value['UserId']);
            
            if ($value['ToUserId']) {
                $toUserInfo = User::getUserDetail($value['ToUserId']);
               
                $list[$key]['TouserName'] = $toUserInfo['name'];
            }

            $list[$key]['is_follow'] = $userInfo['is_follow'] ? 1 : 0;
            $list[$key]['username'] = $userInfo['name'];
            $list[$key]['school_name'] = $userInfo['school']['name'];
            $list[$key]['avatar'] = $userInfo['avatar'];
            $list[$key]['toon_uid'] = $userInfo['toon_uid'];
            $list[$key]['feed_id'] = $userInfo['feedId'];
            
        }
        
        Fn::outputToJson(0,'ok',$list);
    }
    
}