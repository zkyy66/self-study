<?php
/**
 * @description 活动
 * @author by Yaoyuan.
 * @version: 2016-10-11
 * @Time: 2016-10-11 17:23
 *
 */
class ActivityController extends Controller {
    /**
     * 初始化
     */
    public function init() {
        header("Access-Control-Allow-Origin:*");
        parent::init();
        if (! $this->checkPortalTicket()) {
            Fn::outputToJson(self::ERR_SYS, '非法访问');
        }
    }
    
    /**

     * 分类列表
     * 按照活动类型获取活动列表信息
     * 数据条件：公开的，正常的，审核通过的
     * 数据排序：创建时间倒序
     * @return array
     */
    public function getListAction() {
        // 活动类型
        $type = intval($this->request->getQuery('type', 0));
        $page = intval($this->request->getQuery('page',1));
        
        // 当前时间，用于与开始时间等做比较，获取活动当前状态
        $time = time();
        // 每页活动多少条信息
        $pageSize = 10;
        
        $acModel       = new ActivityModel();
        
        //获取分类列表
        $result = $acModel->getList($page, $pageSize, $type);
       
        if (empty($result)) {
            Fn::outputToJson(self::OK, 'ok', []);
        }
        
        // 临时存储结束的活动，整体排序会用到
        $tmpEndAcArr = array();
        // 存放活动状态
        $acStatus = '';
        
        foreach ($result as $key=>$val) {
            if ($time >= $val['start_time'] && $time <= $val['end_time']) {
                $acStatus = '进行中';
            }
            if ($val['switch_status'] & 1) {
                // 开启报名情况下，未过报名截止时间时，显示报名中。
                if ($time < $val['apply_end_time']) {
                    $acStatus = '报名中';
                }
                // 开启报名情况下，已过报名截止时间，尚未开始时，显示报名截止
                if ($val['apply_end_time']< $time && $time < $val['start_time']) {
                    $acStatus = '报名截止';
                }
            } else if ($time < $val['start_time']) {
                // 未开启报名，尚未开始时，显示未开始
                $acStatus = '未开始';
            }

            if ($time >= $val['end_time']) {
                $acStatus = '已结束';
            }
            
            $userInfo = User::getUserDetail($val['uid']);
            //$feedArrayInfo = $toonUserModel->getFeedInfoByRedis($val['c_fid']);
            if (empty($userInfo)) {
                Fn::writeLog("activity/getList 获取用户信息失败：User::getUserDetail：".var_export($userInfo,true));
            }
            $result[$key]['id'] = $val['activity_id'];
            $result[$key]['applierCount'] = $val['applierCount'] ? $val['applierCount'] : 0;
            $result[$key]['price']        = floatval($val['price']);
            $result[$key]['img']          = json_decode($val['img'], true);
            $result[$key]['f_title']      = isset($userInfo['name']) ? $userInfo['name'] : '';
            $result[$key]['avatarId']     = isset($userInfo['avatar']) ? $userInfo['avatar'] : '';
            $result[$key]['ac_status']    = $acStatus;
            $result[$key]['user_id']      = isset($userInfo['userId']) ? $userInfo['userId'] : 0;
            $result[$key]['allow_apply']  = $val['switch_status'] & 1;
            
            // 已结束的活动单独存放，会放在最后
            if ($time >= $val['end_time']) {
                $tmpEndAcArr[] = $result[$key];
                unset($result[$key]);
            }
        }
        
        
        // 将所有的已结束活动按照结束时间排序
        $tmpEndAcArr = Fn::multiArraySort($tmpEndAcArr,'end_time',SORT_ASC);
        $result = array_merge($result, $tmpEndAcArr);
       
        $total     = count($result);
        $totalPage = ceil($total/$pageSize);
        $list['list'] = array_slice($result, ($page - 1) * $pageSize, $pageSize);
        $list['totalPage'] = $totalPage;
        $list['page'] = $page;
        
        Fn::outputToJson(0, 'ok', $list);
    }
    
    /**
     * 用户报名时，排重
     */
    public function getApplyUserInfoAction() {
        $post = file_get_contents('php://input');
        if (empty($post)) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        
        $info = json_decode($post,true);
        if (empty($info['ac_id'])) {
            Fn::outputToJson(self::ERR_PARAM, '缺少活动ID');
        }
        
        if (empty($info['user_id'])) {
            Fn::outputToJson(self::ERR_PARAM, '缺少用户ID');
        }
        $applyModel = new ApplyModel();
        $applyList = $applyModel->getApplyList("activity_id = {$info['ac_id']} AND status = 1 AND verify_status != 2 ","create_time DESC",0);
        if ($applyList) {
            $tempUidArray = array();
            foreach ($applyList as $key => $val) {
                $tempUidArray[] = $val['user_id'];
            }
            $idStr = implode(',',$tempUidArray);
        
        } else {
            $idStr = '';
        }
        $applyStatus = User::isRepetitionByUserId($info['user_id'],$idStr);
        unset($idStr,$applyList,$tempUidArray);
        if (1 == $applyStatus) {
            Fn::outputToJson(self::ERR_PARAM, '你已有其他名片报名此活动
');
        }
        Fn::outputToJson(0,'ok',[]);
    }
    /**
     * 存储活动信息
     * 参数：mark 1表示增加 2表示编辑
     */
    public function addActivityAction() {
        $post = file_get_contents('php://input');
        
        if ($post) {
            $postArray = json_decode($post, true);
            
        } else {
            Fn::writeLog('activity/addactivity: post值为空，参考：post:'.var_export($post,true));
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
       
        if (!is_array($postArray)) {
            Fn::writeLog('activity/addactivity: !is_array，参考：post:'.var_export($postArray,true));
            Fn::outputToJson(self::ERR_PARAM, '数据格式错误');
        }
        if (empty($postArray['mark'])) {
            Fn::writeLog('activity/addactivity:  没有mark参考：post:'.var_export($postArray,true));
            Fn::outputToJson(self::ERR_PARAM,'参数丢失');
        }
        
        
        if (!$postArray['user_id']) {
            Fn::writeLog('activity/addactivity:  缺少用户信息');
            Fn::outputToJson(self::ERR_PARAM,'参数丢失');
        }
        $dataModel = new ActivityModel();
        $postArray['time'] = time();
       
        //校验数据
        $dealData = $this->checkPostData($postArray);
        
        if (1 == $postArray['mark']) { // 添加
            $result = $dataModel->addDataBase($dealData);
        } else { // 编辑
            $acDetail = $dataModel->getByAcInfoWithUuid($dealData['uuid']);

            empty($acDetail) && Fn::outputToJson(self::ERR_PARAM, '该活动存在异常');
            if ($dealData['user_id'] != $acDetail['user_id']) {
                Fn::outputToJson(self::ERR_PARAM, '您无编辑该活动权限');
            }
           
            $result = $dataModel->updateDataBase($dealData,$postArray['user_id']);
        }

        if ($result) {
            Fn::outputToJson(0,'ok',$result);
        } else {
            Fn::outputToJson(-1,'发布活动失败',$result);
        }
    }
    
    
    
    /**
     * 根据用户信息和活动ID获取获取活动,报名信息及签到信息
     */
    public function getAcInfoByUserAction () {
        
        $postData = file_get_contents('php://input');
        if (empty($postData)) {
            Fn::outputToJson(self::ERR_PARAM,'缺少参数');
        }
        $info = json_decode($postData, true);//解析参数
        if (empty($info['ac_id']) || empty($info['user_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少参数');
        }
        $ac_status = $mixstatus = '';
    
        
        $time = time();//用于判断状态
        
        $acModel = new ActivityModel();
        $applyModel = new ApplyModel();
        //用于判断身份验证
        $user_id = intval($info['user_id']);
        if (!$user_id) {
            Fn::writeLog('activity/getAcInfoByUser !user_id，参考：post:'.json_encode($postData));
            Fn::outputToJson(self::ERR_PARAM,'用户信息缺失');
        }
        $acInfo = $acExtInfo = $appInfo = $checkIn = array();
        //获取活动基本信息
        if (is_numeric($info['ac_id'])) {
            $acInfo = $acModel->getActivityInfo($info['ac_id']);
            $acInfo && $acExtInfo = $acModel->getActivityExtInfo($acInfo['activity_id']);
        } else {
            $acInfo = $acModel->getByAcInfoWithUuid(Fn::filterString($info['ac_id']));
            $acInfo && $acExtInfo = $acModel->getActivityExtInfo($acInfo['activity_id']);
            
        }
        
        empty($acInfo) && Fn::outputToJson('-1','该条活动已删除');
        $acInfo = array_merge($acInfo,$acExtInfo);
        if ($acInfo['custom_field']) {
            $acInfo['custom_field'] = json_decode($acInfo['custom_field'],true);
        }
        
        if ($acInfo['switch_status'] & 2) {
            $acInfo['is_need_check'] = 1;
        } else {
            $acInfo['is_need_check'] = 0;
        }
        
        // 参与者访问活动
        // 获取该用户的报名信息
        $appInfo = $applyModel->getApplyByUid($acInfo['activity_id'], $user_id);
        
        // 获取该用户的签到信息
        $checkIn = $applyModel->checkinSucc($acInfo['activity_id'], $user_id);
        
        /**
         *
         * 新版状态标识
         * $mixstatus:
         * -1 代表已结束;
         * 1 代表我要报名;
         * 2 取消报名;
         * 3 代表报名截止;
         * 4 代表审核通过;
         * 5 通过审核未通过;
         * 6 代表待审核;
         * 8 代表已签到;
         * 9 代表我要签到;
         **/
        
        
        $switch_status = $acInfo['switch_status'];//活动组合值
        //活动状态
        if ($time >= $acInfo['start_time'] && $time <= $acInfo['end_time']) {
            $ac_status = '进行中';
        }
        // 开启报名模式
        if ($switch_status & 1) {
            if ($time < $acInfo['apply_end_time']) {
                $ac_status = '报名中';
            }
            if ($acInfo['apply_end_time']< $time && $time < $acInfo['start_time']) {
                $ac_status = '报名截止';
            }
        } else if ($time < $acInfo['start_time']) {
            $ac_status = '未开始';
        }
        if ($time >= $acInfo['end_time']) {
            $ac_status = '已结束';
        }
        
        // 活动已结束的情况，直接显示已结束
        if ($time > $acInfo['end_time']) {
            $mixstatus = -1;
    
            $acInfo['mixstatus']  = $mixstatus;
            Fn::outputToJson('0', 'ok', $acInfo);
        }
        
        // 活动未结束，开启报名的情况
        if ( $time < $acInfo['apply_end_time']) {
            
            switch ($switch_status) {
                case ($switch_status & 5) ://101,开启报名签到未开启审核模式
                    if ($appInfo && $appInfo['status'] != 0) {
                        if ($checkIn) {
                            $mixstatus = 8;// 代表已签到
                        } else {
                            $mixstatus = 9;//我要签到
                        }
                    } else {
                        $mixstatus = 1; // 我要报名
                    }
                    break;
                case ($switch_status & 7) ://111,开启报名签到及审核模式
                    if ($appInfo) {
                        if ((1 == $appInfo['verify_status']) && (0 == $appInfo['status'])) {
                            $mixstatus = 1;//我要报名
                        } else if (1 == $appInfo['verify_status']) {
                            if ($checkIn) {
                                $mixstatus = 8;//代表已签到
                            } else {
                                $mixstatus = 9;//我要签到
                            }
                        }  else if (2 == $appInfo['verify_status']) {
                            $mixstatus = 1;//我要报名
                        } else if (0 == $appInfo['verify_status'] ){
                            $mixstatus = 9;//我要签到
                        }
                    } else {
                        $mixstatus = 1; //我要报名
                    }
                    break;
                default:
                    $mixstatus = 0;
                    break;
            }
        } else {
            //报名截止超时
            if ($appInfo) {
                if ((0 == $appInfo['status']) && (1 == $appInfo['verify_status'])) {
                    $mixstatus = 9;//我要签到
                } else if (1 == $appInfo['verify_status']) {
                    if ($switch_status & 4) {
                        if ($checkIn) {
                            $mixstatus = 8;//代表已签到
                        } else {
                            $mixstatus = 9;//我要签到
                        }
                    } else {
                        $mixstatus = 9;//我要签到
                    }
                }  else if (2 == $appInfo['verify_status']) {
                    $mixstatus = 1;//我要报名
                } else if (0 == $appInfo['verify_status']) {
                    $mixstatus = 9;//我要签到
                }
            } else {
                $mixstatus = 1;//我要报名
            }
        }
        $acInfo['ac_status'] = $ac_status;//活动状态
        $acInfo['mixstatus'] = $mixstatus;
        
        if ($appInfo) {
            $acInfo['cus_info'] = json_decode($appInfo['cus_info'],true);
            $acInfo['verify_status'] = $appInfo['verify_status'];
            $acInfo['checkin_status'] = $appInfo['checkin_status'];
        }
//        if ($acInfo['custom_field']) {
//            $appInfo['custom_field'] = json_decode($acInfo['custom_field'],true);
//        }
        
        unset($ac_status,$mixstatus);
        
        Fn::outputToJson(0,'ok',$acInfo);
    }
    
    
    /**
     * 查看活动详情
     */
    public function detailActivityAction() {
        $time = time();
        
        $post = $this->request->getPost();
        if (empty($post)) { 
            $post = file_get_contents('php://input');
        }
        if (empty($post)) {
            Fn::writeLog('activity/detail empty(post)，参考：post:'.json_encode($post));
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        
        $info = json_decode($post, true);//解析参数
        if (!is_array($info)) {
            Fn::writeLog('activity/detail !is_array($info)，参考：post:'.json_encode($post));
            Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        }
        // 必填参数验证
        if (empty($info['ac_id']) || empty($info['user_id'])) {
            Fn::writeLog('activity/detail !ac_id || !user_id, 参考：post:'.json_encode($post));
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }

        //用于判断身份验证
        $uid = intval($info['user_id']);
        $userInfo = User::getUserDetail($uid);
        if (!$userInfo['userId']) {
            Fn::writeLog('activity/detail !user_id，参考：post:'.json_encode($post));
            Fn::outputToJson(self::ERR_PARAM,'用户信息缺失');
        }

        $acModel    = new ActivityModel();
        $applyModel = new ApplyModel();
        
        //获取活动基本信息
        $result = $acModel->details($info['ac_id']);
        
        empty($result) && Fn::outputToJson('-1','该条活动已删除');
        //关注
        $isFollws = User::getUserDetail($userInfo['userId'],$result['uid']);
        $result['isFollw'] = $isFollws['is_follow'] ? 1 : 0 ;//1是取消关注;0是关注
       
        //心动
        $hearList = User::heartBeatList($result['id'],$userInfo['userId']);

        $result['heartCount'] = $hearList['total'];//心动人数
        
        $tmpEndAcArr = array();
        
        if ($hearList['dataList']) {
            foreach ($hearList['dataList'] as $key => $val) {
                if ($val['userId'] == $userInfo['userId']) {
                    $showTop = 1;
                } else {
                    $showTop  = 0;
                }
                $hearList['dataList'][$key]['showTop'] = $showTop;
            }
    
            $tmpEndAcArr = Fn::multiArraySort($hearList['dataList'],'showTop',SORT_DESC);//排序
        }
        
        
        $result['heartList'] = $tmpEndAcArr ? $tmpEndAcArr : array();//心动列表
        
        //判断当前用户是否对该活动心动过
        $isLove = User::getUserIsFollow($result['id'],$userInfo['userId']);
        $result['islove']  = $isLove['is_love'] ? 1 : 0 ;//心动
        //发布人学校信息
        $acUserShool = User::getUserDetail($result['uid']);

        $result['school_name'] = $acUserShool['school']['name'];
        
        // 参与者访问活动
        // 获取该用户的报名信息
        $appInfo = $applyModel->getApplyByUid($result['id'], $userInfo['userId']);
        // 获取该用户的签到信息
        $checkIn = $applyModel->checkinSucc($result['id'], $userInfo['userId']);
        /**
         *
         * 新版状态标识
         * $mixstatus:
         * 0 代表该活动不接受报名;
         * -1 代表已结束;
         * 1 代表我要报名;
         * 2 取消报名;
         * 3 代表报名截止;
         * 4 代表审核通过;
         * 5 通过审核未通过;
         * 6 代表待审核;
         * 7 代表我要签到/取消报名;
         * 8 代表已签到;
         * 9 代表我要签到;
         * 10 代表超时未审核;
         * 11 代表编辑状态
         **/
        //---------------------------- 以下根据时间，用户，整理底部按钮状态-------------------
        $result['exportMark'] = -1; // 默认不显示导出
    
        $loginUserIsApply = $mixstatus = 0;
        $switch_status = $result['switch_status'];
        
        /*******************活动状态--Start************************/
        if ($time >= $result['start_time'] && $time <= $result['end_time']) {
            $ac_status = '进行中';
        }
        // 开启报名模式
        if ($switch_status & 1) {
            if ($time < $result['apply_end_time']) {
                $ac_status = '报名中';
            }
            if ($result['apply_end_time']< $time && $time < $result['start_time']) {
                $ac_status = '报名截止';
            }
        } else if ($time < $result['start_time']) {
            $ac_status = '未开始';
        }
        if ($time >= $result['end_time']) {
            if ($appInfo && 1 == $appInfo['verify_status']) {
                $loginUserIsApply = 1;
            }
            $ac_status = '已结束';
        }
        $result['loginIsApply'] = $loginUserIsApply ? $loginUserIsApply : 0;
        $result['ac_status']  = $ac_status;
        /*******************活动状态--End************************/
        //发布者toonUId
        $ac_release_user = User::getUserDetail($result['uid']);
        $result['ac_release_user'] = $ac_release_user['toon_uid'] ? $ac_release_user['toon_uid'] : 0;
        
        // 发布者自己查看活动详情
        if ($userInfo['userId'] == $result['uid']) {
            // 当前用户是活动发起者
            $result['exportMark'] = 1;
            $result['loginIsApply'] = 2;
            if ($time >= $result['end_time']) {
                $mixstatus = -1; // 已结束
            } else {
                $mixstatus = 11; // 编辑
            }
            $result['mixstatus']  = $mixstatus;
            Fn::outputToJson('0', 'ok', $result);
        }
        

            
        // 活动已结束的情况，直接显示已结束
        if ($time > $result['end_time']) {
            $mixstatus = -1;
            // 活动已结束，但是未被审核，视为 超时未审核
            if ($appInfo && 0 == $appInfo['verify_status']) {
                $mixstatus = -1;
            }
            $result['mixstatus']  = $mixstatus;
            Fn::outputToJson('0', 'ok', $result);
        } 
        
        // 未结束，但是未开启报名的情况
        if (!($switch_status & 1)) {
            $mixstatus = 0;
            $result['mixstatus']  = $mixstatus;
            Fn::outputToJson('0', 'ok', $result);
        }
        
        // 活动未结束，开启报名的情况
        if ( $time < $result['apply_end_time']) {
            switch($switch_status) {
                //开启报名未开启审核
                case ($switch_status & 1) ://001
                    if ($appInfo && 0 != $appInfo['status']) {
                        $mixstatus = 2; // 取消报名
                    } else {
                        $mixstatus = 1; // 我要报名
                    }
                    break;
                //开启报名并开启审核
                case ($switch_status & 3) ://011
                    if ($appInfo) {
                        if ((1 == $appInfo['verify_status']) && (0 == $appInfo['status'])) {
                            $mixstatus = 1;// 我要报名
                        } else if (1 == $appInfo['verify_status']) {
                            $mixstatus = 2;// 取消报名
                        } else if (2 == $appInfo['verify_status']) {
                            $mixstatus = 1;// 我要报名
                        } else if (0 == $appInfo['verify_status']){
                            $mixstatus = 6;// 待审核
                        }
                    } else {
                        $mixstatus = 1; // 我要报名
                    }
                    break;
                //开启报名并开启签到，但未开启审核
                case ($switch_status & 5) ://101
                    if ($appInfo && $appInfo['status'] != 0) {
                        if ($checkIn) {
                            $mixstatus = 8;// 代表已签到
                        } else {
                            $mixstatus = 2;//报名取消
                        }
                    } else {
                        $mixstatus = 1; // 我要报名
                    }
                    break;
                //开启报名，审核及签到
                case ($switch_status & 7) ://111
                    if ($appInfo) {
                        if ((1 == $appInfo['verify_status']) && (0 == $appInfo['status'])) {
                            $mixstatus = 1;//我要报名
                        } else if (1 == $appInfo['verify_status']) {
                            if ($checkIn) {
                                $mixstatus = 8;//代表已签到
                            } else {
                                $mixstatus = 2;//报名取消
                            }
                        }  else if (2 == $appInfo['verify_status']) {
                            $mixstatus = 1;//我要报名
                        } else if (0 == $appInfo['verify_status'] ){
                            $mixstatus = 6;//待审核
                        }
                    } else {
                        $mixstatus = 1; //我要报名
                    }
                    break;
                //未开启报名
                default:
                    $mixstatus = 0;
                    break;
            }
        } else  {
            // 报名截止时间已过
            if ($appInfo) {
                if ((0 == $appInfo['status']) && (1 == $appInfo['verify_status'])) {
                    $mixstatus = 3;//代表报名截止(已截止报名)
                } else if (1 == $appInfo['verify_status']) {
                    if ($switch_status & 4) {
                        if ($checkIn) {
                            $mixstatus = 8;//代表已签到
                        } else {
                            $mixstatus = 2;//取消报名
                        }
                    } else {
                        $mixstatus = 2;//取消报名
                    }
                }  else if (2 == $appInfo['verify_status']) {
                    $mixstatus = 3;//报名截止
                } else if (0 == $appInfo['verify_status']) {
                    $mixstatus = 6;//待审核
                }
            } else {
                $mixstatus = 3;//代表报名截止(已截止报名)
            }
        }
        
       
       
        $result['mixstatus']  = $mixstatus;
        //Fn::writeLog("活动详情页状态：".var_export($result,true));
        Fn::outputToJson('0', 'ok', $result);
    }
    
    /**
     * 参与方列表
     */
    public function getMyApplyListAction() {
        $time = time();
        $getParams = $this->request->getQuery();
        if (empty($getParams) || empty($getParams['user_id'])) {
            Fn::writeLog('activity/getMyApplyList ，参考：getParams:'.json_encode($getParams));
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }
        
        //身份验证
    
        $userInfo = User::getUserDetail(intval($getParams['user_id']));
        if (empty($userInfo) || empty($userInfo['userId'])) {
            Fn::writeLog('activity/getMyApplyList !user_id，参考：userInfo:'.json_encode($userInfo));
            Fn::outputToJson(self::ERR_PARAM,'用户信息缺失');
            
        }
        $uid = intval($userInfo['userId']);
        
        $offset = intval($this->request->getQuery('offset', 0));
        $limit  = intval($this->request->getQuery('limit', 10));
        $listModel = new ActivityModel();
        
        // 我参与的活动列表
        $mark = 2;
        $listResult = $listModel->getMyListData($uid, $mark, $offset, $limit);
        if (empty($listResult)) {
            Fn::outputToJson(0,'ok',[]);
        }
        
        $status = '';
        foreach($listResult as $key=>$val) {
            if ($time > $val['start_time'] && $time < $val['end_time']) {
                $ac_status = '进行中';
            }
            if ( $val['switch_status'] & 1 ) {
                if ($time <= $val['apply_end_time']) {
                    $ac_status = '报名中';
                }
                if ($val['apply_end_time'] < $time && $time < $val['start_time']) {
                    $ac_status = '报名截止';
                }
            } else if ($time < $val['start_time']) {
                $ac_status = '未开始';
            }
            if ($time > $val['end_time']) {
                $ac_status = '已结束';
                if (0 == $val['verify_status']) {
                    $status = '超时未审核';
                }
            }

            if (1 == $val['verify_status'] ) {
                $status = '报名成功';
                if (1 == $val['checkin_status']) {
                    $status = '已签到';
                }
            }
            if ($time < $val['end_time'] ) {
                if (0 == $val['verify_status']) {
                    $status = '待审核';
                }
            }
            if (0 == $val['apply_status']) {
                $status = '报名取消';
            }
            if (2 ==  $val['verify_status']) {
                $status = '审核不通过';
            }


            if ($val['price'] == 0.00 || $val['price'] == 0) {
                $val['price'] = 0;
            }
//            if (1 == $val['isgroup']) {
//                $feed_id = $val['fid'];
//            } else {
//                $feed_id = $val['c_fid'];
//            }
            //置换成自己用户体系中的用户ID
            $feedArray = User::getUserDetail($val['user_id']);
            empty($feedArray) && Fn::writeLog("参与列表获取用户信息失败");
            
            $listResult[$key]['id'] = $val['activity_id'];
            $listResult[$key]['isgroup'] = $val['is_group'];
            $listResult[$key]['c_fid'] = $val['single_feed_id'];
            $listResult[$key]['fid'] = $val['group_feed_id'];
            $listResult[$key]['price'] = floatval($val['price']);
            $listResult[$key]['img'] = json_decode($val['img'],true);
            $listResult[$key]['avatarId'] = isset($feedArray['avatar']) ? $feedArray['avatar'] : '';
            $listResult[$key]['username'] = isset($feedArray['name']) ? $feedArray['name'] : '';
            $listResult[$key]['ac_status'] = $status;
            $listResult[$key]['status'] = $ac_status;
        }
        
        Fn::outputToJson(0, 'ok', $listResult);

    }
    
    /**
     * 发起方列表
     */
    public function getMyPubListAction() {
        $time = time();
        $getParams = $this->request->getQuery();
        if (empty($getParams)|| empty($getParams['user_id'])) {
            Fn::writeLog('activity/getMyPubList ，参考：getParams:'.json_encode($getParams));
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }
        // 用户验证
        $userInfo = User::getUserDetail(intval($getParams['user_id']));
        if (empty($userInfo) || empty($userInfo['userId'])) {
            Fn::writeLog('activity/getMyPubList ，参考：userInfo:'.json_encode($userInfo));
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        
        $uid = intval($userInfo['userId']);

        $offset = intval($this->request->getQuery('offset', 0));
        $limit  = intval($this->request->getQuery('limit', 10));

        $listModel = new ActivityModel();
        $mark = 1;
        $listResult = $listModel->getMyListData($uid, $mark, $offset, $limit);
        if (empty($listResult)) {
            Fn::outputToJson(0, 'ok', []);
        }
        
        $status = '';
        foreach($listResult as $key=>$val) {
            if ($time > $val['start_time'] && $time < $val['end_time']) {
                $status = '进行中';
            }
            if ($val['switch_status'] & 1) {
                if ($time <= $val['apply_end_time']) {
                    $status = '报名中';
                }
                if ($val['apply_end_time'] < $time && $time < $val['start_time']) {
                    $status = '报名截止';
                }
            } else if ($time < $val['start_time']) {
                $status = '未开始';
            }

            if ($time > $val['end_time']) {
                $status = '已结束';
            }

            if ($val['price'] == 0.00 || $val['price'] == 0) {
                $val['price'] = 0;
            }

            $feedArray = User::getUserDetail($val['user_id']);
            empty($feedArray) && Fn::writeLog("发起方列表获取用户信息失败");
            $listResult[$key]['id'] = $val['activity_id'];
            $listResult[$key]['isgroup'] = $val['is_group'];
            $listResult[$key]['c_fid'] = $val['single_feed_id'];
            $listResult[$key]['fid'] = $val['group_feed_id'];
            $listResult[$key]['price'] = floatval($val['price']);
            $listResult[$key]['img'] = json_decode($val['img'],true);
            $listResult[$key]['avatarId'] = isset($feedArray['avatar']) ? $feedArray['avatar'] : '';
            $listResult[$key]['username'] = isset($feedArray['name']) ? $feedArray['name'] : '';
            $listResult[$key]['ac_status'] = $status;
        }
        Fn::outputToJson(0, 'ok', $listResult);
    }
    //导出报名并发送邮箱
    public function exportApplyInfoAction() {
        $post = file_get_contents('php://input');
        $postArray = json_decode($post,true);
        $statisticsString = '';
        if (!is_array($postArray)) {
            Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        }
        //获取报名签到信息
        $applyModel = new ApplyModel();
        //获取活动信息
        $acModel = new ActivityModel();
        //根据Feed_id获取feed信息
        $feedModel = new ToonModel();
        //报名签到标识1--代表报名;2--代表签到
        //检验email格式
        !Fn::checkEmail($postArray['email']) && Fn::outputToJson('-1','请提供正确的邮箱格式');
        //获取活动详情信息
        $acList = $acModel->details($postArray['ac_id']);
        
        $postArray['title'] = $acList['title'];
        $acCusField = $acList['custom_field'];
        $tempArray = array();
        foreach ($acCusField as $key => $value) {
            $tempArray[] = $value['key'];
        }
        $tempArray[] = '昵称';
        $tempArray[] = '报名时间';
        if (1 == $postArray['mark']) {//获取报名相关信息
            $list = $applyModel->getApplyList("status = 1 AND `verify_status` = 1 AND activity_id='{$acList['id']}'", '`create_time` DESC ', 0);
            
        } else if (2 == $postArray['mark']) {//获取签到相关信息
            $list = $applyModel->getApplyList("status = 1 AND `verify_status` = 1 AND activity_id='{$acList['id']}'", '`checkin_time` DESC ', 0);
            //报名通过总数
            $applyNum = $applyModel->getApplyNum($acList['id'],1);
            //已签到数
            $checkinNum = $applyModel->getCheckinNum($acList['id'], 1);
            //未签到
            $unCheckinNum = $applyNum - $checkinNum;
            $perNum = 0;
            
            if ($applyNum > 0) {
                $perNum = ceil(($checkinNum/$applyNum)*100).'%';
            }
            $statisticsString = "报名通过:".$applyNum."人\n已签到:".$checkinNum."人\n未签到:".$unCheckinNum."人\n签到率:".$perNum;
            /************************************/
            $tempArray[] = '是否签到';
            $tempArray[] = '签到时间';
        }
        //组合自定义信息数据结构
        $tempArrayData = $mixArray = array();
        empty($list) && Fn::outputToJson('-1','error');
        
        foreach ($list as $key=>$val) {
            //$feedArrayInfo = $feedModel->getFeedInfoByRedis($val['feed_id']);
            $feedArrayInfo = User::getUserDetail($val['user_id']);
            empty($feedArrayInfo) && Fn::writeLog('导出报名/签到信息获取用户信息失败');
            $dataArray = json_decode($val['cus_info'],true);
            if ($dataArray) {
                foreach($dataArray as $k=>$v) {
                    $mixArray[$k] = $v['value'];
                }
            } else {
                $dataArray = $acList['custom_field'];
                foreach($dataArray as $k=>$v) {
                    $mixArray[$k] = $v['value'];
                }
            }
            if ($feedArrayInfo) {
                $mixArray['title'] = $feedArrayInfo['name'];
            }
            
            $mixArray['create_time'] = date('Y-m-d H:i',$val['create_time']);
            
            if( 2 == $postArray['mark']) {
                $mixArray['checkin_status'] = empty($val['checkin_status']) ? '否' : '是';
                if (1 == $val['checkin_status']) {
                    $mixArray['checkin_time'] = date('Y-m-d H:i',$val['checkin_time']);
                } else {
                    $mixArray['checkin_time'] = date('Y-m-d H:i',$val['create_time']);
                }
                
            }
            
            $tempArrayData[$key] = $mixArray;
            unset($dataArray,$mixArray);
        }
        
        ExportExcelModel::excelExportCsv($postArray,$tempArrayData,$tempArray,$statisticsString);
        unset($tempArrayData,$tempArray);
        Fn::outputToJson('0','ok',[]);
        
    }
    
    //搜索活动接口
    public function searchListAction() {
        //当前时间和活动开始时间，结束时间、报名截止时间比较返回活动状态
        $time = time();
        //用于接收Get方式传参
        $getParams = $this->request->getQuery();
        //偏移量--用于分页
        $offset = intval($this->request->getQuery('offset', 0));
        //每页显示条数--用于分页
        $limit = intval($this->request->getQuery('limit', 10));
        //搜索必传条件
        if (empty(trim($getParams['title'])) || empty($getParams['user_id'])) {
            Fn::writeLog('activity/searchList:  没有title或没有user_id，参考：post:'.json_encode($getParams));
            Fn::outputToJson(self::ERR_PARAM,'参数缺失');
        }
        $title = urldecode(trim($getParams['title']));
        $sqlModel = new ActivityModel();

        $result = $sqlModel->getAcList($offset,$limit,$title);
        //结果为空，则返回
        empty($result) && Fn::outputToJson(0,'ok',[]);

        foreach ($result as $key => $val) {
            if ($time > $val['start_time'] && $time < $val['end_time']) {            //当前时间在开始是假和结束时间的区间内
    
                $status = '进行中';
            }

            if ($val['switch_status'] & 1) {//开启报名
                if ($time <= $val['apply_end_time']) {//当前时间小于报名截止时间
                    $status = '报名中';
                }
                if ($val['apply_end_time'] < $time && $time < $val['start_time']) {//当前时间大于报名截止时间，而小于活动开始时间
                    $status = '报名截止';
                }
            } else if ($time < $val['start_time']) {//未开启报名且当前时间小于开始时间
                $status = '未开始';
            }

            if ($time > $val['end_time']) {
                $status = '已结束';
            }
            if ($val['price'] == 0.00 || $val['price'] == 0) {
                $val['price'] = 0;
            }
            $result[$key]['price'] = floatval($val['price']);
            $result[$key]['img'] = json_decode($val['img'],true);
            $result[$key]['applierCount'] = $val['applierCount'] ? $val['applierCount'] : 0;
            $result[$key]['ac_status'] = $status;
            $result[$key]['allow_apply'] = $val['switch_status'] & 1;
        }
        
        Fn::outputToJson('0','ok',$result);
    }

    /**
     * 添加编辑活动数据验证
     * @param array $post
     * @return array
     */
    public function checkPostData(array $post) {
        
        if (!is_array($post)) {
            Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        }
        
        if (2 == $post['mark']) {
            $post['id'] = $post['ac_id'];
        }
        /**********************过滤必要数据***************************/

        //获取当前用户
        $userInfo = User::getUserDetail($post['user_id']);
        
        if (!$userInfo) {
            Fn::writeLog("activity:checkpost:".var_export($userInfo,true));
            
            Fn::outputToJson(self::ERR_PARAM,'校验用户信息失败');
        }
        //创建时间
        $post['create_time'] = time();

        // mark验证--用于区分目前操作是新增还是编辑
        empty($post['mark']) && Fn::outputToJson(self::ERR_PARAM,'缺少必要参数');
        if (empty($post['school_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少学校信息');
        }
        // title验证
        $post['title'] = Fn::filterString(trim($post['title']));
        empty($post['title']) && Fn::outputToJson(self::ERR_PARAM,'请填写标题');
        strlen($post['title']) > 90 && Fn::outputToJson(self::ERR_PARAM,'标题长度过长');
        
        // 海报处理
        if($post['img']['url'] && is_string($post['img']['url'])) {
            $post['img'] = json_encode($post['img']);
        } else {
            Fn::writeLog("acitivity/checkpostdata 海报数据错误：".json_encode($post));
            Fn::outputToJson(self::ERR_PARAM,'海报数据异常');
        }

        //活动类型 0娱乐 1兴趣  2户外   3展览  4演出  5会议 6运动 7-沙龙
        $post['type']  = empty($post['type']) ? 0 : intval( $post['type'] );
        if (!in_array($post['type'], array(0,1,2,3,4,5,6,7))) {
            Fn::outputToJson(self::ERR_PARAM,'请选择活动类型');
        }
        //开始时间和结束时间的判断
        if (empty($post['start_time']) || empty($post['end_time'])) {
            Fn::outputToJson(self::ERR_PARAM,'请正确填写开始时间或者结束时间');
        }
        if ($post['start_time'] <= $post['time']) {
            Fn::outputToJson(self::ERR_PARAM,'开始时间不早于当前时间');
        }
        if ($post['end_time'] <= $post['start_time']) {
            Fn::outputToJson(self::ERR_PARAM,'结束时间不早于开始时间');
        }
        //是否是群组
        $post['isgroup'] = empty($post['isgroup']) ? 0 : $post['isgroup'];
        
        //活动地点
        $post['locate'] = Fn::filterString(trim($post['locate']));
        empty($post['locate']) && Fn::outputToJson(self::ERR_PARAM,'请填写活动地点');
        $post['address'] = $post['address'] ? Fn::filterString(trim($post['address'])) : '';
        //验证发起方联系方式
        $post['tel'] = isset($post['tel']) ? $post['tel'] : 0;

        //活动描述
        if (Fn::getStrLen($post['description']) < 10) {
            Fn::outputToJson(self::ERR_PARAM,"文字描述至少10个字。");
        }
        $post['description'] =  Fn::filterString(Fn::nl2br($post['description']));
        
        //上传图片
        $post['images'] = empty($post['images']) ? '' : json_encode($post['images']);

        //uid
        $post['uid'] = empty($post['uid']) ? 0 : $post['uid'];
        //区分群组ID还是个人ID
        $post['fid']  = !empty( $post['fid'] ) ? $post['fid'] : '';
        $post['c_fid'] = !empty( $post['c_fid'] ) ? $post['c_fid'] : '';
        
        //根据isgroup来获取feed_id来获取相关feed信息
//        if (1 == $post['isgroup']) {
//            $feedInfo = $toonModel->getFeedInfoByRedis($post['fid']);
//        } else {
//            $feedInfo = $toonModel->getFeedInfoByRedis($post['c_fid']);
//        }
//        empty($feedInfo) && Fn::writeLog('发布活动时获取用户信息失败');
    
        //获取名片号
        if (!isset($post['u_no'])) {
            $post['u_no'] = $userInfo['cardNo'] ? intval($userInfo['cardNo']) : 0;
        }
        
        //获取用户昵称
        $post['nickname'] = Fn::filterString($userInfo['name']);
        $post['nickname'] = $post['nickname'] ? $post['nickname'] : '';
        
        //纬度
        $post['latitude'] = empty($post['latitude']) ? '0' : $post['latitude'];
        //经度
        $post['longtitude'] = empty($post['longtitude']) ? '0' : $post['longtitude'];

        //是否报名
        $post['allow_apply'] = empty($post['allow_apply']) ? '0': $post['allow_apply'];
        
        $post['price'] = empty($post['price']) ? 0.00 : $post['price'];
        if ($post['price'] < 0) {
            Fn::outputToJson(self::ERR_PARAM,"请正确填写金额数");
        }
        if ($post['price'] > 9999) {
            Fn::outputToJson(self::ERR_PARAM,"所填写金额不应超过9999");
        }
        
        //开启报名模式下而进行的判断
        if (1 == $post['allow_apply']) {
            $post['max'] = !empty($post['max']) ? intval($post['max']) : 0;
            $post['max'] < 0 && Fn::outputToJson(self::ERR_PARAM,'请正确输入人数');
            $post['max'] > 100000 && Fn::outputToJson(self::ERR_PARAM,'请保持人数在100000内');
            //报名截止时间
            empty($post['apply_end_time']) && Fn::outputToJson(self::ERR_PARAM,'报名截止时间不能为空');
            
            if ($post['apply_end_time'] > $post['start_time']) {
                Fn::outputToJson(self::ERR_PARAM,'报名截止时间不晚于活动开始时间');
            }
    
            empty($post['custom_field']) && Fn::outputToJson(self::ERR_PARAM,'自定义字段不能为空');
            
//            $post['price'] = empty($post['price']) ? 0.00 : $post['price'];
//            if ($post['price'] < 0) {
//                Fn::outputToJson(self::ERR_PARAM,"请正确填写金额数");
//            }
//            if ($post['price']) {
//                Fn::outputToJson(self::ERR_PARAM,"所填写金额超出规定金额数");
//            }

            //审核开关
            $post['checktype'] =  $post['checktype'];

            $post['need_checkin'] = 1;
            
        }

        return $post;
    }
    
    /**
     * 群活动列表--暂时废弃
     */
    public function getGroupListAction() {
        $time = time();//当前时间和活动开始结束，报名截止时间判断，返回相应状态
        $activityModel = new ActivityModel();
        $feedModel = new ToonModel();

        $getParams = $this->request->getQuery();

        if (empty($getParams['session_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }
        $offset = intval($this->request->getQuery('offset', 0));
        $limit = intval($this->request->getQuery('limit', 10));

        $feedInfo = Fn::getSessionByUser($getParams['session_id']);
        empty($feedInfo) && Fn::writeLog('群活动列表获取用户信息失败');
        
        $result = $activityModel->getGroupList($feedInfo['g_feed_id'],$offset,$limit,$feedInfo['frame'],$time);
        empty($result) && Fn::outputToJson('0','ok',[]);
            
        foreach($result as $key=>$val) {
            if ($time > $val['start_time'] && $time < $val['end_time']) {
                $status = '进行中';
            }

            if ($val['switch_status'] & 1) {
                if ($time <= $val['apply_end_time']) {
                    $status = '报名中';
                }
                if ($val['apply_end_time'] < $time && $time < $val['start_time']) {
                    $status = '报名截止';
                }
            } else if ($time < $val['start_time']) {
                $status = '未开始';
            }
            
            
            if ($val['price'] == 0.00 || $val['price'] == 0) {
                $val['price'] = 0;
            }
            
            //$feedArray = $feedModel->getFeedInfoByRedis($val['fid']);
            $feedArray = $feedModel->getFeedInfoByRedis($val['uid']);

            empty($feedArray) && Fn::outputToJson(self::ERR_PARAM,'获取用户信息失败');
            
            $result[$key]['img'] = json_decode($val['img'],true);
            $result[$key]['price'] = floatval($val['price']);
            $result[$key]['avatarId'] = $feedArray['avatar'];
            $result[$key]['username'] = $feedArray['name'];
            $result[$key]['ac_status'] = $status;
            $result[$key]['applierCount'] = $val['applierCount'] ? $val['applierCount'] : 0;
            $result[$key]['allow_apply'] = $val['switch_status'] & 1;
        }
            
        Fn::outputToJson(0,'ok',$result);
    }
    

    /**
     * 推荐/热门/近期活动--暂时废弃
     */
    public function getPancelListAction() {
        $groupData = array();
        $time = time();
        
        $model = new ActivityModel();
      
        $getParams = $this->request->getQuery();
        if (empty($getParams)|| empty($getParams['session_id'])) {
            Fn::writeLog('activity/getPancelList:  没有session_id，参考：getParams:'.json_encode($getParams));
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }
        
        //推荐活动
        $recommendList = $model->getPanelList(0, 2, $time,'index');
        $recommendList = empty($recommendList) ? [] : $this->_minRuleDataRow($recommendList,$time);
        
        //热门活动
        $hotList = $model->getHotList(0, 2, $time,'index');
        $hotList = empty($hotList) ? [] : $this->_minRuleDataRow($hotList,$time);
    
        //近期活动
        $recentList = $model->getRecentList(0, 7, $time,'index');
        $recentList = empty($recentList) ? [] : $this->_minRuleDataRow($recentList,$time);
        /**
         * 活动数据去重
         */
        if ($hotList && $recentList) {
            foreach ($hotList as $key => $val) {
                foreach ($recentList as $k => $v) {
                    if ($v['id'] == $val['id']) {
                        unset($recentList[$k]);
                    } else {
                        $recentList = array_slice($recentList, 0, 5);
                    }
                }
            }
        }
        
        //组合数据
        $groupData = array(
            'recommendList' => $recommendList,
            'hotList' => $hotList,
            'recentList' => $recentList
        );

        Fn::outputToJson(0,'ok',$groupData);
    }
    
    //热门活动
    public function getHotListAction() {
        $model = new ActivityModel();
        $getParams = $this->request->getQuery();
        if (empty($getParams) ||!isset($getParams['page'])) {
            Fn::writeLog('activity/getHotList:  没有page或者没有session_id，参考：getParams:'.json_encode($getParams));
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }
        $time = time();
        $page = intval($this->request->getQuery('page',1));
        $pageSize = 10;
        $hotList = $model->getHotList($page,$pageSize,$time);
        empty($hotList) && Fn::outputToJson(0,'ok',[]);

        $hotList = $this->_minRuleDataRow($hotList,$time);
      
        if ($pageSize > 0) {
            $total = count($hotList);
            $totalPage = ceil($total/$pageSize);
            $result['list']  = array_slice($hotList, ($page - 1) * $pageSize, $pageSize);
        } else {
            $totalPage = 1;
        }
        $result['totalPage'] = $totalPage;
        $result['page'] = $page;
        
        Fn::outputToJson(0,'ok',$result);
    }
    
    //近期活动--f废弃
    public function getRecentListAction() {
        $model = new ActivityModel();
        $time = time();
        $getParams = $this->request->getQuery();
        if (empty($getParams)|| empty($getParams['session_id']) || !isset($getParams['page'])) {
            Fn::writeLog('activity/getRecentList:  没有page或者没有session_id，参考：getParams:'.json_encode($getParams));
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }

        $page = intval($this->request->getQuery('page',1));
        $pageSize = 10;
        $recentList = $model->getRecentList($page,$pageSize,$time);
        empty($recentList) && Fn::outputToJson(0,'ok',[]);

        $recentList = $this->_minRuleDataRow($recentList,$time);

        if ($pageSize > 0) {
            $total = count($recentList);
            $totalPage = ceil($total/$pageSize);
            $result['list']  = array_slice($recentList, ($page - 1) * $pageSize, $pageSize);
        } else {
            $totalPage = 1;
        }
        $result['totalPage'] = $totalPage;
        $result['page'] = $page;

        Fn::outputToJson(0,'ok',$result);
    }

    /**
     * 新增活动公告
     */
    public function addNoticeAction() {
        $postData = file_get_contents('php://input');
        $model = new ActivityModel();
        if ($postData) {
            $postData = json_decode($postData,true);
        }
        if (!is_array($postData)) {
            Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        }
        if (empty($postData['ac_id']) || empty($postData['user_id'])) {
            Fn::writeLog('activity/addNotice:  没有ac_id或者没有session_id，参考：postData:'.json_encode($postData));
            Fn::outputToJson(self::ERR_PARAM,'数据参数缺失');
        }
        
        $acInfo = $model->getByAcInfoWithUuid($postData['ac_id']);
        empty($acInfo) && Fn::outputToJson(self::ERR_PARAM,'参数错误');
        
        if ($postData['user_id'] != $acInfo['user_id']) {
            Fn::outputToJson(self::ERR_PARAM,'权限不正确');
        }
        $postData['loginUser'] = $postData['user_id'];
        $result = $model->addNotice($postData);
        $result = $result ? $result : [];
        Fn::outputToJson(0,'ok',$result);

    }

    /**
     * 群聊信息
     * 获取群聊ID和活动ID
     * 
     */
    public function groupChatAction() {

        $model = new ActivityModel();
        
        $post = file_get_contents('php://input');
       
        Fn::writeLog("群聊参数：".var_export($post,true));
        empty($post) && Fn::outputToJson(self::ERR_PARAM,'参数错误');

        $post = json_decode($post,true);
        !is_array($post) && Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        
        $id = intval($post['id']);
        $acInfo = $model->getActivityInfo($id);
        
        empty($acInfo) && Fn::outputToJson(self::ERR_PARAM,'该活动数据异常');
        
        $groupID = intval($post['groupID']);
        empty($groupID) && Fn::outputToJson(self::ERR_PARAM,'参数缺失');
        !is_numeric($groupID) && Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        
        $loginUid = intval($post['user_id']);
        
        if (!$loginUid) {
            Fn::outputToJson(self::ERR_PARAM,'缺少当前用户信息');
        }
        
        /**
         * 查询群聊表
         */
        $groupChatInfo = $model->detailChatInfo($id,$groupID);
        if (empty($groupChatInfo)) {
            $status = $model->addChatInfo($id,$groupID);
            Fn::writeLog("群聊通知状态：".$status);
            !$status && Fn::outputToJson(self::ERR_PARAM,'群聊信息入库失败');

            $list = $model->applyList(1,$id);
            $result = $model->remainingPeopleWithApply($list,$acInfo,$loginUid);

            if($status && $result) {
                Fn::outputToJson(0,'ok',[]);
            }
        }

    }
    /**
     * 公告列表
     */
    public function noticeListAction() {
        $getParam = $this->request->getQuery();
        $offset = intval($getParam['offset']);
        $limit = intval($getParam['limit']);
        if (!isset($offset) || empty($limit)) {
            Fn::outputToJson(self::ERR_PARAM,'缺少必要参数');
        }
        $acModel = new ActivityModel();
        $acInfo = $acModel->getByAcInfoWithUuid(Fn::filterString($getParam['ac_id']));
        empty($acInfo) && Fn::outputToJson(self::ERR_PARAM,'该活动不存在');
        
        $list = $acModel->getNoticeList($offset,$limit,$acInfo['uuid']);
        empty($list) && Fn::outputToJson(0,'ok',[]);
        foreach ($list as $key => $val) {
            $list[$key]['content'] = strip_tags(Fn::br2nl($val['content']));
        }
        Fn::outputToJson(0,'ok',$list);
    }

    /**
     * 删除公告
     */
    public function deleteNoticeAction() {
        $postData = file_get_contents('php://input');
        $model = new ActivityModel();
        $result = $model->deleteNotice($postData['id']);
        $result = $result ? $result : [];
        Fn::outputToJson(0,'ok',$result);
    }
    
    /**
     * 关注接口
     */
    public function attentionUserAction () {
        $post = file_get_contents('php://input');
        Fn::writeLog("Activity:attentionUser:".var_export($post,true));
        if (empty($post)) {
            Fn::outputToJson(self::ERR_PARAM,'缺少必要参数');
        }
        $info = json_decode($post,true);
        if (empty($info['user_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少用户ID');
        }
        if (empty($info['toUser_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少被关注用户ID');
        }
        if (!isset($info['type'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少关注类型');
        }
        
        $result = User::attentionUser ($info['user_id'],$info['toUser_id'],$info['type']);
        if (0 == $result) {
            Fn::outputToJson(0,'ok',[]);
        } else if (2000 == $result ) {
            Fn::outputToJson(1,'已关注，不允许重复关注',[]);
        } else {
            Fn::outputToJson(1,'关注失败',[]);
        }

        
    }
    
    /**
     * 活动心动接口
     */
    public function heartBeatAction () {
        $post = file_get_contents('php://input');
        if (empty($post)) {
            Fn::outputToJson(self::ERR_PARAM,'缺少必要参数');
        }
        $info = json_decode($post,true);
        if (empty($info['user_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少用户ID');
        }
        if (empty($info['ac_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少活动ID');
        }
        if (!isset($info['type'])) {
            Fn::outputToJson(self::ERR_PARAM,'缺少关注类型');
        }
        $result = User::heartBeat($info['user_id'],$info['type'],$info['ac_id']);
        
        if ($result) {
            Fn::outputToJson(0,'ok',[]);
        }
        Fn::outputToJson(1,'关注失败',[]);
    }
    
    /**
     * 心动列表
     */
    public function heartBeatListAction () {
        
        $page = intval($this->request->getQuery('page',1));
        $ac_id = intval($this->request->getQuery('ac_id'));
        $user_id = intval($this->request->getQuery('user_id'));
        //$loginUserId = $this->request->getQuery('loginUserId','');
        if (empty($ac_id)) {
            Fn::outputToJson(self::ERR_PARAM,'缺少活动ID');
        }
//        if (empty($loginUserId)) {
//            Fn::outputToJson(self::ERR_PARAM,'缺少用户标识');
//        }
        
        $getUserInfo = User::getUserDetail($user_id);
        if (!$getUserInfo) {
            Fn::outputToJson(self::ERR_PARAM,'当前用户信息缺失');
        }

        if (empty($user_id)) {
            Fn::outputToJson(self::ERR_PARAM,'缺少用户ID');
        }
        $result = User::heartBeatList($ac_id,$user_id,$page);
        
        if (!$result) {
            Fn::outputToJson(0,'ok',[]);
        }
        
        $tmpEndAcArr = array();
        
        foreach ($result['dataList'] as $key => $val) {
            if ($getUserInfo['toon_uid'] == $val['toon_uid']) {
                $showTop = 1;
            } else {
                $showTop = 0;
            }
            $result['dataList'][$key]['showTop'] = $showTop;
        }
        $tmpEndAcArr = Fn::multiArraySort($result['dataList'],'showTop',SORT_DESC);//排序
        Fn::outputToJson(0,'ok',$tmpEndAcArr);
    }
    
    /**
     * 检测联系方式是否符合要求
     * @param $phone
     * @return bool
     */
    private function _checkContact($phone) {
        $isMob="/^1[3-5,8]{1}[0-9]{9}$/";
        $isTel="/^([0-9]{3,4}-)?[0-9]{7,8}$/";
        if (!preg_match($isMob,$phone) && !preg_match($isTel,$phone)){
            return false;
        }
        return true;
    }
    
    /**
     * @param $time
     * @param int $i
     * @return string
     */
    private function _getTimeWeek($time, $i = 0) {
        $weekarray = array("一", "二", "三", "四", "五", "六", "日");
        $oneD = 24 * 60 * 60;
        return "周" . $weekarray[date("w", $time + $oneD * $i)];
    }
    
    /**
     * 整理数据
     * @param array $list
     * @param $time
     * @return array
     */
    private function _minRuleDataRow(array $list,$time) {
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
                
//                if (1 == $val['isgroup']) {
//                    $feed_id = $val['fid'];
//                } else {
//                    $feed_id = $val['c_fid'];
//                }
                
                
                $list[$key]['price'] = floatval($val['price']);
                $list[$key]['img'] = json_decode($val['img'],true);

                $list[$key]['ac_status'] = $status;
                $list[$key]['user_id'] = $val['uid'];
                $list[$key]['applierCount'] = $val['applierCount'] ? $val['applierCount'] : 0;
                $list[$key]['allow_apply'] = $val['switch_status'] & 1;
                $list[$key]['skipUrl'] = Fn::getServerUrl().'/html/src/index.html?entry=3&ac_id='.$val['id'];
                
            }
        } else {
            $list = [];
        }
        
        return $list;
    }
    
}