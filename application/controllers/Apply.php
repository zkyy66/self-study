<?php
/**
 * @description 活动报名
 * @author liweiwei
 * @version: 2016-10-21
 * @Time: 2016-10-21 15:50
 */
class ApplyController extends Controller {

    public $debug = false;
    public $acModel, $appModel, $toonModel, $noticeModel;
    // 提示信息
    private static $tipArr = array(
        'save'=>array(
            0=>'参数错误', // 缺少参数ac_id feed_id uid没传
            1=>'用户匹配失败', // 传来的uid与实际uid不符
            2=>'活动不存在', // 活动id取出来的活动不存在
            3=>'活动已结束',
            4=>'该活动不允许报名', 
            5=>'报名已结束',
            6=>'名额已满',
            7=>'姓名为必填项',
            8=>'姓名不能超过10个字',
            9=>'手机号为必填项',
            10=>'您输入的是一个无效手机号',
            11=>'报名信息不完整，请补充！',
            12=>'自定义字段长度最大20个字',
            13=>'该用户的报名正在审核中~',
            14=>'该用户已经报名成功，请勿重复报名哦~',
            15=>'您的手机号已报名~',
            16=>'报名失败，请重试',
            17=>'恭喜你报名成功',
            18=>'您输入的身份证号无效',
            19=>'您输入的邮箱格式错误',
            20=>'报名信息已提交，请耐心等待审核',
            21=>'不能报名自己的活动',
        ),
        'verify'=>array(
            0 =>'操作成功',
            1 =>'操作失败，请稍后重试', 
        )
    );
    
    /**
     * 初始化
     */
    public function init() {
        header("Access-Control-Allow-Origin:*");
        parent::init();
        
        $this->acModel     = new ActivityModel();
        $this->appModel    = new ApplyModel();
        $this->toonModel   = new ToonModel();
        $this->noticeModel = new NoticeModel();
    }

    /**
     * 获取报名相关的统计数据
     */
    public function getStatAction()
    {
       // 待审核 已通过 未通过
        $acId = intval($this->request->getQuery('ac_id', 0)); // 必传
        if (!$acId) {
            Fn::writeLog('apply/getStatAction: 缺少acId参数, acId:'.$acId);
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        
        if ($this->debug) {
            Fn::outputToJson(self::OK, 'ok', array('unverify'=>5, 'verifyed'=>3, 'verifyFailed'=>2));
        }
        $unverify = $this->appModel->getApplyNum($acId, 0);
        $verifyed = $this->appModel->getApplyNum($acId, 1);
        $verifyFailed = $this->appModel->getApplyNum($acId, 2);
        
        Fn::outputToJson(self::OK, 'ok', array('unverify'=>$unverify, 'verifyed'=>$verifyed, 'verifyFailed'=>$verifyFailed));
    }
    
    /*
     * 报名接口
     * // 判断逻辑
        // --接口基本字段必填验证 ac_id feed_id sessionid必传
        // --判断活动信息：存在性
         * -- 自己不能报名自己的活动
        // --同一个uid只能报一次名
         * ----同一张名片多次报名该活动需判断：
        // ------开放模式时，同一名片报名活动开放模式下提示：该名片已经报名成功，请勿重复报名哦~。
        // ------审核模式下未审核时提示：该名片的报名正在审核中~
        // ------直接报名提供：恭喜你活动报名成功~
        // --判断活动信息：结束时间
        // --判断是否允许报名
        // --判断活动报名截止时间
        // --判断活动剩余名额，不限制名额的不用判断
        // --判断活动必填字段，姓名手机号必填，设置的自定义字段必填
         * -- 判断手机号，身份证，邮箱格式
        // --重报判断：（一个名片只能报一次，一个手机号只能报一次）
        // ----同一个手机号只能报一次 ：
        // ------您的手机号已报名~
        // ------你输入的是一个无效手机号
        // --判断审核状态，得到初始审核状态值
        // --符合要求，存入报名信息。
        // --报名完毕发通知
         * 
//         {"ac_id":5,"feed_id":"c_1146750710125416","uid":"405161","content":"\u5f88\u60f3\u53c2\u52a0\u54e6","cus_info":[{"key":'name',"value":"sally","id":'1'},{"key":'phone',"value":"185222","id":'2'}]}
    */
    public function saveAction()
    {
        if (!$this->checkPortalTicket()) {
            Fn::outputToJson(self::ERR_SYS, '非法访问');
        }
        
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {
            $acId    = isset($postData['ac_id'])? intval($postData['ac_id']) : 0; // 必传
            $feedId  = isset($postData['feed_id'])? Fn::filterString(trim($postData['feed_id'])) : ''; // 必传
            $cusInfo = isset($postData['cus_info'])? $postData['cus_info'] : array(); // 姓名手机号必填，其他选项在设置的情况下，必填
            $content = isset($postData['content'])? Fn::filterString(trim($postData['content'])) : ''; // 选填
            //$sessionId = isset($postData['session_id'])? Fn::filterString(trim($postData['session_id'])) : ''; // 必填
            $user_id = isset($postData['user_id']) ? intval($postData['user_id']) : 0;
        } else {
            $acId    = intval($this->request->getPost('ac_id', 0)); // 必传
            $feedId  = Fn::filterString(trim($this->request->getPost('feed_id', ''))); // 必传
            $uid     = intval($this->request->getPost('uid', 0)); // 必传
            $cusInfo = $this->request->getPost('cus_info', array()); // 姓名手机号必填，其他选项在设置的情况下，必填
            $content = Fn::filterString(trim($this->request->getPost('content', ''))); // 选填
            //$sessionId = Fn::filterString(trim($this->request->getPost('session_id', '')));  // 必填
            $user_id = intval($this->request->getPost('user_id',0));
        }
        
        if (!$user_id) {
            Fn::writeLog("apply/save: [原因]缺少user_id参数，[参考变量]postData信息如下：".json_encode($postData));
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        $uid = intval($user_id);
        
        // --接口基本字段必填验证
        if (!$acId || !$feedId || !$uid) {
            Fn::writeLog('apply/save: [原因]!acId||!feedId||!uid, [参考变量]其中 acId='.$acId.', feedId='.$feedId.', uid='.$uid.', postData信息如下：'.json_encode($postData));
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        
        if ($this->debug) {
            Fn::outputToJson(self::OK, self::$tipArr['save'][17]);
        }
        
        // --判断活动信息：存在性
        $acInfo = $this->acModel->details($acId);
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][2]);
        }
        
        if ($acInfo['uid'] == $uid) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][21]);
        }
    
        //判断用户是否重复报名
        $applyList = $this->appModel->getApplyList("activity_id = {$acInfo['id']} AND status = 1 AND verify_status != 2 ","create_time DESC",0);
        if ($applyList) {
            $tempUidArray = array();
            foreach ($applyList as $key => $val) {
                $tempUidArray[] = $val['user_id'];
                
            }
            $idStr = implode(',',$tempUidArray);
            
        } else {
            $idStr = '';
        }
        
        $applyStatus = User::isRepetitionByUserId($uid,$idStr);
        unset($idStr,$applyList,$tempUidArray);
        
        if (1 == $applyStatus) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][14]);
        }
        // --根据uid判断已报名，最新的一条报名信息
        $tmpApplyInfo = $this->appModel->getApplyByUid($acId, $uid);
        if (!empty($tmpApplyInfo)) {
            if ($tmpApplyInfo['status'] == 0 || $tmpApplyInfo['verify_status'] == 2) {
                // 被拒绝或取消报名，继续，还可以报名
            } elseif ($tmpApplyInfo['verify_status'] == 0) { // 待审核状态
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][13]);
            } elseif ($tmpApplyInfo['verify_status'] == 1) { // 已通过审核
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][14]);
            }
        }
        
        // --判断活动信息：时间
        if (time() >= $acInfo['end_time']) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][3]);
        }
        
        // --判断是否允许报名
        if (!($acInfo['switch_status'] & 1)) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][4]);
        }
        
        // --判断活动信息：活动报名截止时间
        if (time() >= $acInfo['apply_end_time']) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][5]);
        }
        
        // --判断活动剩余名额，不限制名额的不用判断
        if ($acInfo['max'] > 0) {
            $apply_num = $this->appModel->getApplyNum($acId, 1);
            // 参数不对，返回-1
            if ($apply_num == -1) {
                Fn::writeLog("apply/save：[原因]活动已参与人数获取失败[参考变量]acId=$acId, apply_num=$apply_num");
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
            }
            if ($apply_num >= $acInfo['max']) {
                Fn::outputToJson(self::OK, self::$tipArr['save'][6],[]);
            }
        }
        
        // 比较自定义字段必填及格式
        if (isset($acInfo['custom_field']) && !empty($acInfo['custom_field'])) {
            $customFieldArr = $acInfo['custom_field'];
            // 判断必填字段是否必填，判断格式是否正确，判断是否已经报名
            $this->checkCusInfo($customFieldArr, $cusInfo, $acId);
        }
        
        // 组织报名信息，准备入库
        $rowArr = [
            'activity_id'        => $acId,
            'user_id'          => $uid,
            'feed_id'      => $feedId, 
            'cus_info'     => json_encode($cusInfo), 
            'content'      => $content,
            'verify_status'=> 1,
            'create_time'  => time(),
        ];
        // 获取报名填写的手机号
        $cusInfoKeyValue  = $this->buildCusInfo($cusInfo);
        if (isset($cusInfoKeyValue[2])) {
            $rowArr['phone'] = $cusInfoKeyValue[2];
        }
        
//         // 需要审核 0-开放 1-审核
//         if ($acInfo['checktype'] == 1) {
//             $rowArr['verify_status'] = 0;
//         }
        // 有2-需要审核
        if (($acInfo['switch_status'] & 2)) {
            $rowArr['verify_status'] = 0;
        }
        // 之前是取消报名状态的处理
        if (isset($tmpApplyInfo['status']) && $tmpApplyInfo['status'] == 0) {
            $rowArr['status'] = 1;
        }
        
        if (!$this->appModel->addApply($rowArr)) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][16]);
        }
        
        // 开放式活动，不用审核，不用发通知，直接返回报名成功
        
        // 没2-开放
        if (!($acInfo['switch_status'] & 2)) {
//         if ($acInfo['checktype'] == 0) {
            // 更新报名数量
            $acstatModel = new AcstatModel();
            $acstatModel->incrApplyNum($acId);
            $this->acModel->removeAllMc();
            Fn::outputToJson(self::OK, self::$tipArr['save'][17], array());
        }

        //-----------------------------如下是发通知逻辑处理------------------------------
        // 当活动是需要审核的活动时，就发通知给发起人发个通知
//        $feedInfo = $this->toonModel->getFeedInfoByRedis($feedId);
        $feedInfo = User::getUserDetail($uid);
        // 下面会显示申请人的名字
        if (!empty($feedInfo['name'])) {
            $feedInfoTitle = $feedInfo['name'];
        } else {
            $feedInfoTitle = '';
        }
        //获取活动发布者的ToonUid
        $acUserInfo = User::getUserDetail($acInfo['uid']);
        // 通过codeArr传递身份
        $codeArr = [
                'visitor'=>array('uid'=>$acInfo['uid'], 'feed_id'=>$acInfo['c_fid']),
                'owner'=>array('feed_id'=>$acInfo['c_fid']),
        ];

        if ($acInfo['isgroup']) {
            $codeArr['owner']['feed_id'] = $acInfo['fid'];
            $feedType = 'g'; // 代表群组活动
        } else {
            $feedType = 'c'; // 代表个人活动
        }
        $contentArr = [
            'url' => Fn::generatePageUrl(4, $acInfo['id'], $codeArr, $feedType),
            'msg' => "{$feedInfoTitle}申请报名活动【{$acInfo['title']}】，请尽快审核",
            "buttonTitle" => '去审核',
        ];
        $noticeInfo = array('fromFeedId'=>$feedId, 'toFeedId'=>$acInfo['c_fid'], 'toUid'=>$acUserInfo['toon_uid'], 'contentArr'=>$contentArr);
        Fn::writeLog("报名通知发消息参数：".var_export($noticeInfo,true));
        $this->noticeModel->addToList($noticeInfo);
        //-----------------------------如上是发通知逻辑处理------------------------------
        
        Fn::outputToJson(self::OK, self::$tipArr['save'][20], array('ok'));
    }
    
    /**
     * // 比较活动自定义字段和自己报名填写的字段是否匹配
     * @param array $customFieldArr array(array('key'=>'姓名', 'value'=>'姓名', 'id'=>'1'))
     * @param array $cusInfoArr array(array('key'=>'姓名', 'value'=>'liww', 'id'=>'1'))
     * @param int $acId
     */
    private function checkCusInfo($customFieldArr, $cusInfoArr, $acId)
    {
        if (empty($customFieldArr)) {
            return;
        }
        
        // 报名字段必填判断
        if (!$cusInfoArr) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][11]);
        }
    
        $tmpCusField = $this->buildCusInfo($customFieldArr); //格式化成 array(1=>'姓名', 2=>'手机号')
        $tmpCusInfo  = $this->buildCusInfo($cusInfoArr); //格式化成 array(1=>'liww', 2=>'13490909090')
    
        // 验证是否有字段没有填写
        if (!empty(array_diff(array_keys($tmpCusField), array_keys($tmpCusInfo)))) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][11]);
        }
//         foreach ($customFieldArr as $customKey=>$customInfo) {
//             if (!isset($cusInfo[$customKey]) || empty($cusInfo[$customKey])) {
//                 Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][11].'['.$customInfo.']');
//             }
//         }
        
        // 手机号验证
        if (isset($tmpCusField[2])) {
            if (!Fn::checkPhone($tmpCusInfo[2])) {
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][10]);
            }
            // 手机号，名片只能报一次名。判断录入的手机号是否已经有未被删除的，已被审核或待审核的记录。
            $tmpPhoneInfo = $this->appModel->getApplyByPhone($acId, $tmpCusInfo[2], array(0, 1), 1);
            if (!empty($tmpPhoneInfo)) {
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][15]);
            }
        }
        // 身份证验证
        if (isset($tmpCusField[5]) && !Fn::checkCard($tmpCusInfo[5])) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][18]);
        }
        // 邮箱验证
        if (isset($tmpCusField[11]) && !Fn::checkEmail($tmpCusInfo[11])) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][19]);
        }
    }
    
    /**
     * $getCusInfo = array(array('key'=>'姓名', 'value'=>'liww', 'id'=>'1'))
     * @param unknown $getCusInfo
     * @return multitype:|multitype:unknown
     */
    private function buildCusInfo($getCusInfo)
    {
        if (!$getCusInfo) {
            return array();
        }
        
        $tmpData = array();
        foreach ($getCusInfo as $v) {
            if (!$v['value']) {
                continue;
            }
            $tmpData[$v['id']] = $v['value'];
        }
        return $tmpData;
    }
    
    /* 
     * 查看已报名人员列表，包含头像，名称（都可以查看）
     * 待审核的人员列表（只有发布者能看到）
     * 已拒绝的人员列表（只有发布者能看到）
     */
    public function showListAction()
    {
        $userInfo = array();
        $acId   = intval($this->request->getQuery('ac_id', 0)); // 必传
        $offset = intval($this->request->getQuery('offset', 0)); // 申请记录的id节点，默认0
        $limit  = intval($this->request->getQuery('limit', 10)); // 获取条数，默认10条
        $type   = intval($this->request->getQuery('type', 1)); // 类型：0-待审核 1-审核通过 2-审核失败 ，默认1
        $user_id = intval(trim($this->request->getQuery('user_id', ''))); //
        if (!$user_id) {
            $userInfo['userId'] = 0;
        } else {
            $userInfo = User::getUserDetail(intval($user_id));
            
        }
        
        // --接口基本字段必填验证
        if (!$acId  || !$limit) {
//            Fn::writeLog('apply/showListAction: [原因]缺少acId或limit参数, [参考变量]acId:'.$acId.', limit:'.$limit);
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        
        // --活动存在性判断
        $acInfo = $this->acModel->getActivityInfo($acId);
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][2]);
        }
        if ($userInfo['userId'] == $acInfo['user_id']) {
            $isPublisher = 1; // 发布者自己访问，可以查看更多内容
        } else {
            $isPublisher = 0; // 其他人访问，只能看名片基本信息，不包含报名字段内容
        }
        
        // 查看待审核、审核失败的列表时，只有发布者可以查看        
        if ($type != 1 && $userInfo['userId'] != $acInfo['user_id']) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][1]);
        }
        //获取当前用户报名信息
        $currentUser = array();
        $userSql = "`activity_id`={$acId} AND `verify_status` = {$type} AND `status`=1 AND user_id = {$userInfo['userId']}";
        
        // 组织获取条件
        $where = '';
        if ($offset > 0) {
            $where .= " `id` < {$offset} AND ";
        }
        $where .= "`activity_id`={$acId} AND `verify_status` = {$type} AND `status`=1 AND user_id != {$userInfo['userId']}";
        if ($type == 0) {
            $order = '`create_time` ASC ';
        } else {
            $order = ' `create_time` DESC ';
        }
        if ($offset == 0) {
            $currentUser = $this->appModel->getApplyList($userSql,$order,1);
        }
        
        $data = $this->appModel->getApplyList($where, $order, $limit);
        
        $dataRow = array_merge($currentUser,$data);
        
        $tmpData = array();
        foreach ($dataRow as $k=>$v) {
            $feedInfo = User::getUserDetail($userInfo['userId'],$v['user_id']);
            $tmpData[$k] = array(
                'id'            => $v['id'],
                'ac_id'         => $v['activity_id'],
                'uid'           => $v['user_id'],
                'feed_id'       => $v['feed_id'],
                'checkin_status'=> $v['checkin_status'],
                'verify_status' => $v['verify_status'],
                'create_time'   => $v['create_time'],
            );
            if ($userInfo['toon_uid'] == $feedInfo['toon_uid']) {
                $tmpData[$k]['showTop'] = 1;
            } else {
                $tmpData[$k]['showTop'] = 0;
            }
            // 显示feed信息
//            $feedInfo = $this->toonModel->getFeedInfoByRedis($v['feed_id']);
            
            
            Fn::writeLog("报名列表是否关注：".var_export($feedInfo,true));
            
            $tmpData[$k]['avatarId'] = isset($feedInfo['avatar']) ? $feedInfo['avatar'] : '';
            $tmpData[$k]['title']    = isset($feedInfo['name']) ? $feedInfo['name'] : '';
            $tmpData[$k]['subtitle'] = isset($feedInfo['subtitle']) ? $feedInfo['subtitle'] : '';
            $tmpData[$k]['is_follow'] = $feedInfo['is_follow'] ? 1 : 0 ;
            $tmpData[$k]['school_name'] = $feedInfo['school']['name'];
            $tmpData[$k]['user_toon_id'] = $feedInfo['toon_uid'];//toonguid
            if ($isPublisher && !empty($v['cus_info'])) {
                $tmpData[$k]['cus_info'] = json_decode($v['cus_info'], true);
            } else {
                $tmpData[$k]['cus_info'] = [];
            }
        }
        
//        if (1 == $type) {
//            $tmpData = Fn::multiArraySort($tmpData,'showTop');
//        }
//        Fn::writeLog("apply/showlist/报名列表数据：".var_export($tmpData,true));
        Fn::outputToJson(self::OK, 'OK', $tmpData);
    }

    
    /*
     * 审核报名人员 同意、拒绝
        // 判断必填字段
        // 判断活动信息：存在性
        // 判断活动的所有者
        // 如果是同意，需要判断是否还有名额，拒绝不用判断。是否可以报名，活动时间、报名时间和名额 
        // 更新审核状态
        // 发送通知
     */
    public function verifyAction()
    {
        if (!$this->checkPortalTicket()) {
            Fn::outputToJson(self::ERR_SYS, '非法访问');
        }
        
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {
            $applyId = isset($postData['apply_id'])? intval($postData['apply_id']) : 0; // 必传
            $acId    = isset($postData['ac_id'])? intval($postData['ac_id']) : 0; // 必传
            $verifyStatus = isset($postData['verify_status'])? intval($postData['verify_status']) : 0; // 必传
            $user_id = isset($postData['user_id'])? trim($postData['user_id']) : ''; // 必传
        } else {
            $applyId = intval($this->request->getPost('apply_id', 0)); // 必传
            $acId    = intval($this->request->getPost('ac_id', 0)); // 必传
            $verifyStatus = intval($this->request->getPost('verify_status', 0)); // 必传
            $user_id = trim($this->request->getPost('user_id', '')); // 必传
        }
        
        // 从session中获取
        if (!$user_id) {
            Fn::writeLog('apply/verify: [原因]缺少$user_id参数, [参考变量]postData:'.json_encode($postData));
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        // 从session中获取uid
        $uid = intval($user_id);
        
        // --接口基本字段必填验证
        if (!$applyId || !$acId || !$uid) {
            Fn::writeLog("apply/verify: [原因]acId||feedId||uid无值, [参考变量]acId:$acId, applyId:$applyId, uid:$uid, postData信息如下：".json_encode($postData));
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        
        if ($this->debug) {
            Fn::outputToJson(self::OK, self::$tipArr['verify'][0], array('verify_status'=>$verifyStatus, 'apply_id'=>$applyId));
        }
        
        // --判断活动信息：存在性
        $acInfo = $this->acModel->getActivityInfo($acId);
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][2]);
        }
        // 只能自己审核自己的活动申请
        if ($acInfo['user_id'] != $uid) {
            Fn::writeLog('apply/verify: [原因]$acInfo[user_id] != $uid, [参考变量]'.$acInfo['user_id'].'~'.$uid);
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        // 验证报名信息存在性
        $applyInfo = $this->appModel->getApplyById($applyId);
        if (!$applyInfo) {
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][2]);
        }
        
        // 如果是同意，需要判断是否还有名额，拒绝不用判断
        if ($verifyStatus == 1) {
            // --判断活动信息：时间
            if (time() >= $acInfo['end_time']) {
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][3]);
            }
            
            // --判断是否允许报名
            if (!($acInfo['switch_status'] & 1)) {
                Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][4]);
            }
            
            // --判断活动剩余名额，不限制名额的不用判断
            if ($acInfo['max_people'] > 0) {
                $apply_num = $this->appModel->getApplyNum($acId, 1);
                // 参数不对，返回-1
                if ($apply_num == -1) {
                    Fn::writeLog('apply/verify：[原因]获取活动报名人数失败， [参考变量]acId='.$acId.',apply_num='.$apply_num);
                    Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
                }
                if ($apply_num >= $acInfo['max_people']) {
                    Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][6]);
                }
            }
        }
        
        // 更新状态
        if (!$this->appModel->verifyApply($applyId, $verifyStatus)) {
            Fn::outputToJson(self::ERR_SYS, self::$tipArr['verify'][1]);
        }
        if ($verifyStatus == 1) {
            // 更新报名数量
            $acstatModel = new AcstatModel();
            $acstatModel->incrApplyNum($acId);
        }
//         // 暂时去掉，后来更新了需求，被拒绝后，还可以报名
//         if ($verifyStatus == 2) {
//             // 插入reject_apply表
//             $this->appModel->addRejectApply(array('ac_id'=>$acId, 'uid'=>$uid, 'feed_id'=>$applyInfo['feed_id'], 'create_time'=>time()));
//         }
        
        //-------------------------如下是通知逻辑---------------------------
        $fromFeedId = $acInfo['single_feed_id'];
        // 通过codeArr传递身份
        $codeArr = array(
            'visitor'=>array('uid'=>$applyInfo['user_id'], 'feed_id'=>$applyInfo['feed_id']),
            'owner'=>array('feed_id'=>$acInfo['single_feed_id'] ),
        );
        if ($acInfo['is_group']) {
            $codeArr['owner']['feed_id'] = $acInfo['group_feed_id'];
            $feedType = 'g';
            $fromFeedId = $acInfo['group_feed_id'];
        } else {
            $feedType = 'c';
        }

        if ($verifyStatus == 1) { // 操作成功的情况下，需要发通知给申请者
            $contentArr = [
                'url' => Fn::generatePageUrl(3, $acId, $codeArr, $feedType),
                'msg' => "您报名的活动【{$acInfo['title']}】已通过审核",
                "buttonTitle" => '去看看',
            ];

        } elseif ($verifyStatus == 2) {
            $contentArr = [
                'url' => Fn::generatePageUrl(3, $acId, $codeArr, $feedType),
                'msg' => "很遗憾，您报名的活动【{$acInfo['title']}】，因报名信息有误或不符要求，未通过审核，可更正报名信息再次报名。",
                "buttonTitle" => '去看看',
            ];

        }
        $applyUserInfo = User::getUserDetail($applyInfo['user_id']);
        $noticeInfo = array('fromFeedId'=>$fromFeedId, 'toFeedId'=>$applyInfo['feed_id'], 'toUid'=>$applyUserInfo['toon_uid'], 'contentArr'=>$contentArr);
        $this->noticeModel->addToList($noticeInfo);
        //-------------------------如上是通知逻辑---------------------------
        
        // 更新热门列表的缓存
        $this->acModel->removeAllMc();
        
        // 结果返回
        Fn::outputToJson(self::OK, self::$tipArr['verify'][0], array('verify_status'=>$verifyStatus, 'apply_id'=>$applyId, 'contentArr'=>$contentArr));
    }
     
    /*
     * 报名人员取消报名，并发通知
        // --接口基本字段必填验证
        // 获取报名信息,判断存在性
        // 新增status字段，更新status=0
        // 发通知
        // 返回结果
     */
    public function revokeAction()
    {
        if (!$this->checkPortalTicket()) {
            Fn::outputToJson(self::ERR_SYS, '非法访问');
        }
        
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData) {
            $acId = isset($postData['ac_id'])? intval($postData['ac_id']) : 0; // 必传
            $sessionId = isset($postData['user_id'])? trim($postData['user_id']) : ''; // 必传
            
        } else {
            $acId = intval($this->request->getPost('ac_id', 0)); // 必传
            $sessionId = trim($this->request->getPost('user_id', '')); // 必传
        }
        
        if (!$sessionId) {
            Fn::writeLog('apply/revoke: [原因]user_id， [参考变量] acId='.$acId.', postData='.json_encode($postData));
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        // 从session中获取
        $uid = intval($sessionId);
        
        // --接口基本字段必填验证
        if (!$acId || !$uid) {
            Fn::writeLog('apply/revoke: [原因]缺少acId或uid参数， [参考变量] acId='.$acId.', uid='.$uid);
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        
        if ($this->debug) {
            Fn::outputToJson(self::OK, self::$tipArr['verify'][0]);
        }
        
        $applyInfo = $this->appModel->getApplyByUid($acId, $uid);
        if (!$applyInfo || $applyInfo['user_id'] != $uid) {
            Fn::writeLog('apply/revoke: [原因]!$applyInfo || $applyInfo[user_id] != $uid ,  [参考变量]$applyInfo='.json_encode($applyInfo).' $uid='.$uid);
            Fn::outputToJson(self::ERR_PARAM, self::$tipArr['save'][0]);
        }
        
        if (!$this->appModel->revokeApply($applyInfo['id'])) {
            Fn::outputToJson(self::ERR_SYS, self::$tipArr['verify'][1]);
        }
        
        // 更新报名数量
        $acstatModel = new AcstatModel();
        $acstatModel->decrApplyNum($acId);
        
        
        // --------------------如下发通知逻辑---------------------------------------
        $acInfo     = $this->acModel->getActivityInfo($applyInfo['activity_id']);
        
        $acUserInfo = User::getUserDetail($acInfo['user_id']);
        
        $feedInfo = $this->toonModel->getFeedInfoByRedis($applyInfo['feed_id']);
        $contentArr = ['msg' => "{$feedInfo['title']}已取消报名活动【{$acInfo['title']}】"];
        $noticeInfo = array('fromFeedId'=>$applyInfo['feed_id'], 'toFeedId'=>$acInfo['single_feed_id'], 'toUid'=>$acUserInfo['toon_uid'], 'contentArr'=>$contentArr);
        $this->noticeModel->addToList($noticeInfo);
        // --------------------如上发通知逻辑---------------------------------------
        
        // 删除首页s所有列表的缓存
        $this->acModel->removeAllMc();
        
        // 返回结果
        Fn::outputToJson(self::OK, self::$tipArr['verify'][0], array('apply_id'=>$applyInfo['id']));
    }
}