<?php
/**
* @description 签到相关操作
* @author liweiwei
* @version 2016-10-31上午11:08:14
*/
class CheckinController extends Controller
{
    public $debug = false;
    public $acModel, $appModel, $toonModel;
    // 统一定义提示信息
    private static $tipArr = array(
        'list'=>array(
            0 =>'参数错误',   // 缺少参数ac_id feed_id uid没传 
            1 =>'用户匹配失败', // 传来的uid与实际uid不符
            2 =>'活动不存在',// 活动id取出来的活动不存在
            3 =>'签到成功',
            4 =>'没有报名成功的没有权限签到',
            5 =>'已经签过到了',
            6 =>'操作失败，请稍后重试',
            7 =>'没有开启签到',
            8 =>'还没开始签到',
            9 =>'签到时间已过',
            10 =>'未审核',
            11 => '审核未通过'
        )
    );
    
    /**
     * 初始化
     */
    public function init()
    {
        header("Access-Control-Allow-Origin:*");
        parent::init();
        
        $this->acModel   = new ActivityModel();
        $this->appModel  = new ApplyModel();
        $this->toonModel = new ToonModel();
    }

    /**
     * 签到接口
     * 接口思路：
     * 1、
     * 2、接口基本字段必填验证
     * 3、活动存在性验证
     * 4、活动是否开启了签到
     * 5、当前是否在签到时间范围内
     * 6、检查是否报名成功
     * 7、检查是否已经签到
     * 8、签到
     */
    public function saveAction()
    {
        if (! $this->checkPortalTicket()) {
            Fn::outputToJson(self::ERR_SYS, '非法访问');
        }
        
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {
            $acId      = isset($postData['ac_id'])? intval($postData['ac_id']) : 0; // 必传
            $sessionId = isset($postData['user_id'])? trim($postData['user_id']) : ''; // 必传
        } else {
            $acId      = intval($this->request->getPost('ac_id', 0)); // 必传
            $sessionId = trim($this->request->getPost('user_id', '')); // 必传
        }
        // --接口基本字段必填验证
        if (!$sessionId) {
            Fn::writeLog("checkin/save: [原因]缺少sessionId参数，[参考变量]postData信息如下：".json_encode($postData));
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][0]);
        }
        $uid = intval($sessionId);
        
        // --接口基本字段必填验证
        if (!$acId || !$uid) {
            Fn::writeLog("checkin/save:[原因]!acId || !uid，[参考变量] acId:".$acId.", uid:".$uid);
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][0]);
        }
        
        if ($this->debug) {
            Fn::outputToJson(self::OK, self::$tipArr['list'][3]);
        }
        
        $acInfo = $this->acModel->getActivityInfo($acId);
        // --判断活动信息：存在性
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][2]);
        }
        // 是否开启了签到
//         if (0 == $acInfo['need_checkin']) {
        if (!($acInfo['switch_status'] & 4)) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][7]);
        }

        
        // 判断是否已经报名了，只有报名成功才能签到
        $applyInfo = $this->appModel->getApplyByUid($acId, $uid);
        //审核模式下,只有审核通过才能签到
        if ($acInfo['switch_status'] & 2) {
            if (0 == $applyInfo['verify_status']) {
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][10]);
            }
            if (2 == $applyInfo['verify_status']) {
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][11]);
            }
        }

        // 没报过名，或报名没有审核，是不可以签到的
        if (!$applyInfo || $applyInfo['verify_status'] != 1 || $applyInfo['status'] != 1) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][4]);
        }
        // 已经签过到了
        if ($applyInfo['checkin_status'] == 1) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][5]);
        }
        // 更新状态
        if (!$this->appModel->checkin($applyInfo['id'])) {
            Fn::outputToJson(self::ERR_SYS, self::$tipArr['list'][6]);
        }
        
        // 更新签到数量
        $acstatModel = new AcstatModel();
        $acstatModel->incrCheckinNum($acId);
        
        Fn::outputToJson(self::OK, self::$tipArr['list'][3], array());
    }
    
    /**
     * 获取签到相关的统计已签到 未签到 已通过 签到率
     */
    public function getStatAction()
    {
        $acId = intval($this->request->getQuery('ac_id', 0)); // 必传
        if (!$acId) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][0]);
        }
        
        if ($this->debug) {
            $data = array('checkinNum'=>4, 'unCheckinNum'=>6, 'applyNum'=>10, 'percent'=>"40%");
            Fn::outputToJson(self::OK, 'ok', $data);
        }
        
        $checkinNum   = $this->appModel->getCheckinNum($acId, 1);
        $applyNum     = $this->appModel->getApplyNum($acId, 1);
        $unCheckinNum = $applyNum - $checkinNum;
        $perNum = 0; // 签到百分比
        if ($applyNum > 0) {
            $perNum = ceil(($checkinNum/$applyNum)*100);
        }
        
        $data = array('checkinNum'=>$checkinNum, 'unCheckinNum'=>$unCheckinNum, 'applyNum'=>$applyNum, 'percent'=>$perNum."%");
        Fn::outputToJson(self::OK, 'ok', $data);
    }
    
    /* 
     * 查看签到列表接口：已签到、未签到
     * 说明
     * 1、发布者可查看已签到、未签到的，信息中包含名片和报名字段信息
     * 2、参与者可查看已签到的，信息中只包含名片基本信息
     */
    public function showListAction()
    {
        $acId   = intval($this->request->getQuery('ac_id', 0)); // 必传
        $offset = intval($this->request->getQuery('offset', 0)); // 申请记录的id节点，默认0
        $limit  = intval($this->request->getQuery('limit', 10)); // 获取条数，默认10条
        $type   = intval($this->request->getQuery('type', 1)); // 类型：0-未签到 1-已签到，默认1
        $sessionId = trim($this->request->getQuery('user_id', '')); // 可不传，不传未访客
        
        if (!$sessionId) {
            $userInfo['userId'] = 0;
        } else {
            $userInfo = User::getUserDetail(intval($sessionId));
        }
       
        // --接口基本字段必填验证
        if (!$acId  || !$limit || !in_array($type, array(0, 1))) {
            Fn::writeLog('checkin/showList: [原因]!$acId ||!$limit||!in_array($type, array(0, 1))，[参考变量]acId:'.$acId.',limit:'.$limit.', type:'.$type);
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][0]);
        }
        
        // --活动存在性判断
        $acInfo = $this->acModel->getActivityInfo($acId);
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][2]);
        }
        // 判断是否是活动发布者
        if ($userInfo['userId'] == $acInfo['user_id']) {
            $isPublisher = 1; // 发布者自己访问，可以查看更多内容
        } else {
            $isPublisher = 0; // 其他人访问，只能看名片基本信息，不包含报名字段内容
        }
        
        // 未签到的列表，只有发布者可以查看        
        if (!$isPublisher && $type != 1) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['list'][1]);
        }
        //当前用户签到
        $currentUser = array();
        $userSql = " `activity_id`={$acId} AND  `verify_status`=1 AND `checkin_status` = {$type} AND `status`=1 AND user_id = {$userInfo['userId']}";
        // 组织获取条件
        $where = '';
        if ($offset > 0) {
            $where .= " `id` < {$offset} AND ";
        }
        $where .= " `activity_id`={$acId} AND  `verify_status`=1 AND `checkin_status` = {$type} AND `status`=1 AND user_id != {$userInfo['userId']}";
        if ($type == 1) {
            $order = ' `checkin_time` DESC '; // 查看已签到列表按照签到时间倒序排序
        } else {
            $order = ' `create_time` DESC '; // 查看未签到列表按照报名时间倒序排序
        }
        $data = $this->appModel->getApplyList($where, $order, $limit);
        if ($offset == 0) {
            $currentUser = $this->appModel->getApplyList($userSql,$order,1);
        }
        
        
        $dataRow = array_merge($currentUser,$data);
        
        $tmpData = array();
        foreach ($dataRow as $k=>$v) {
            $feedInfo = User::getUserDetail($userInfo['userId'],$v['user_id']);
            
            $tmpData[$k] = array(
                'id'       => $v['id'],
                'ac_id'    => $v['activity_id'],
                'uid'      => $v['user_id'],
                'feed_id'  => $v['feed_id'],
            );
            if ($userInfo['toon_uid'] == $feedInfo['toon_uid']) {
                $tmpData[$k]['showTop'] = 1;
            } else {
                $tmpData[$k]['showTop'] = 0;
            }
            
            // 从缓存中获取名片信息
//            $feedInfo = $this->toonModel->getFeedInfoByRedis($v['feed_id']);
            
            $tmpData[$k]['avatarId'] = isset($feedInfo['avatar']) ? $feedInfo['avatar'] : '';
            $tmpData[$k]['title']    = isset($feedInfo['name']) ? $feedInfo['name'] : '';
            $tmpData[$k]['subtitle'] = isset($feedInfo['subtitle']) ? $feedInfo['subtitle'] : '';
            $tmpData[$k]['is_follow'] = $feedInfo['is_follow'] ? 1 : 0 ;
            $tmpData[$k]['school_name'] = $feedInfo['school']['name'];
            $tmpData[$k]['user_toon_id'] = $feedInfo['toon_uid'];
            if ($isPublisher && !empty($v['cus_info'])) {
                $tmpData[$k]['cus_info'] = json_decode($v['cus_info'], true);
            } else {
                $tmpData[$k]['cus_info'] = [];
            }
        }
//        if (1 == $type) {
//            $tmpData = Fn::multiArraySort($tmpData, 'showTop');
//        }
        Fn::outputToJson(self::OK, 'OK', $tmpData);
    }
}