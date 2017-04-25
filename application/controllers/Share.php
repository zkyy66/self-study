<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2016-11-21
 * @Time: 2016-11-21 16:03
 */
class ShareController extends Controller {
    
    
    //无身份登录
    public function nocheckLoginAction() {
        session_start();
        $this->setPortalTicket();
        $toonModel = new ToonModel();
        $params = json_decode(file_get_contents('php://input'),true);
        if (!is_array($params)) {
            Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        }
        if (empty($params)) {
            Fn::outputToJson(self::ERR_PARAM,'参数为空');
        }

        if (empty($params['feed_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'参数缺失');
        }
        
        $feedInfo = $toonModel->getFeedInfoByRedis($params['feed_id'],1);
        empty($feedInfo) && Fn::outputToJson('nocheckLogin获取用户信息失败');
        $_SESSION = array(
            'feed_id' => $feedInfo['feed_id'],
            'title' => $feedInfo['title'],
            'subtitle' => $feedInfo['subtitle'],
            'avatarId' => $feedInfo['avatarId'],
            'user_id' => $feedInfo['user_id'],
            'session_id' => session_id()
        );
        Fn::outputToJson('0','ok',$_SESSION);
    }
    
    //无身份列表
    public function noIdentityAction() {
        $activityModel = new ActivityModel();
        $toonModel     = new ToonModel();
        
        $time = time();
        $offset = intval($this->request->getQuery('offset', 0));
        $limit  = intval($this->request->getQuery('limit', 10));
        $type   = isset($type) ? intval($type) : 0;
        
        //分类列表--查询未结束的活动，传入标识--index
        $result = $activityModel->getList($offset, $limit, $type, 'index');

        if (!empty($result)) {
            $status = '';
            foreach ($result as $key => $val) {
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
                if (1 == $val['isgroup']) {
                    $feed_id = $val['fid'];
                } else {
                    $feed_id = $val['c_fid'];
                }
                $feedArrayInfo = $toonModel->getFeedInfoByRedis($feed_id);
                empty($feedArrayInfo) && Fn::writeLog('noIdentity获取用户信息失败');
                
                $result[$key]['price'] = floatval($val['price']);
                $img = json_decode($val['img'], true);
                if (!is_array($img)) {
                    $img = [];
                }
                $img = $img ? $img : [];
                $result[$key]['img'] = $img;
                $result[$key]['f_title'] = isset($feedArrayInfo['title']) ? $feedArrayInfo['title'] : '' ;
                $result[$key]['avatarId'] = isset($feedArrayInfo['avatarId']) ? $feedArrayInfo['avatarId'] : '';
                $result[$key]['ac_status'] = $status;
                $result[$key]['user_id'] = isset($feedArrayInfo['user_id']) ? $feedArrayInfo['user_id'] : 0;
                $result[$key]['allow_apply'] = $val['switch_status'] & 1;
            }
            Fn::outputToJson(0, 'ok', $result);
        } else {
            Fn::outputToJson(0,'error',[]);
        }
    }

    
    //无身份详情
    public function nonIdentityDetailAction() {
        $info = array();
        $time = time();
        $post = $this->request->getPost();
        
        if (empty($post)) {
            $post = file_get_contents('php://input');
        }
        $info = json_decode($post, true);
        if (!is_array($info)) {
            Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        }
        
        if (empty($info) || empty($info['ac_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }

        $detailModel = new ActivityModel();

        //获取活动基本信息
        $result = $detailModel->details($info['ac_id']);
        
        /**
         *
         * 新版状态标识
         * $mixstatus:0代表该活动不接受报名;-1代表已结束;1代表我要报名;2取消报名;3代表报名截止;4代表审核通过;5通过审核未通过;6代表待审核;7代表我要签到/取消报名;8代表已签到;9代表我要签到;10代表超时未审核
         *
         **/
        if ($result) {
            $switch_status = $result['switch_status'];
            /****************活动状态*******************/
            if ($time >= $result['start_time'] && $time <= $result['end_time']) {
                $ac_status = '进行中';
            }
            if ($switch_status & 1) {//开启报名模式
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
                $ac_status = '已结束';
            }
            /****************活动状态*******************/
            if ($time > $result['end_time']) {
                $mixstatus = -1;
            } else {
                if ($switch_status & 1) {
                    if ($time > $result['apply_end_time']) {
                        $mixstatus = 3;
                    } else {
                        $mixstatus = 1;
                    }
                } else {
                    $mixstatus = 0;
                }

            }
            $result['ac_status'] = $ac_status;
            $result['mixstatus'] = $mixstatus;
            Fn::outputToJson('0','ok',$result);
        } else {
            Fn::outputToJson('-1','该条活动已删除',[]);
        }
    }

    
    /**
     * 外部分享
     */
    public function shareActivityAction() {
        $activityModel = new ActivityModel();
        $toonModel     = new ToonModel();
        
        $getParams = $this->request->getQuery();

        if (empty($getParams['ac_id'])) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }

        $ac_id = Fn::filterString($getParams['ac_id']);
        if (is_numeric($ac_id)) {
            $acInfo = $activityModel->getActivityInfo($ac_id);

            $acInfo && $acExtInfo = $activityModel->getActivityExtInfo($acInfo['activity_id']);
        } else {
            $acInfo = $activityModel->getByAcInfoWithUuid($ac_id);
            $acInfo && $acExtInfo = $activityModel->getActivityExtInfo($acInfo['activity_id']);
        }
        if (isset($acExtInfo)) {
            $acInfo = array_merge($acInfo, $acExtInfo);
        }
        
        $timeMark = '';
        
        if (!empty($acInfo)) {
            $time = time();
            
            $feedArrayInfo = User::getUserInfoByFeed($acInfo['single_feed_id']);//获取用户信息
            $userDetails = User::getUserDetail($feedArrayInfo['userId']);
            
            $acInfo['username'] = $userDetails['name'];
            $acInfo['avatarId'] = $userDetails['avatar'];
            $acInfo['school_name'] = $userDetails['school']['name'];
           /********************优化开始***********************/
    
            $start_time = explode(" ",date('m月d日 H:i',$acInfo['start_time']));
            $acInfo['start_time_week'] = $this->_getTimeWeek($acInfo['start_time']);
            $end_time = explode(" ",date('m月d日 H:i',$acInfo['end_time']));
            $acInfo['end_time_week'] = $this->_getTimeWeek($acInfo['end_time']);
            
            
            if (date('Y',$time) == date('Y',$acInfo['start_time'])) {//判断是否同年
                $tempTime = date('Y-m-d',$time);//临时赋值用于一下判断
        
                if ($tempTime == date('Y-m-d',$acInfo['start_time'])) {//判断活动开始时间是否同天
                    $startTime = date('m月d日',$acInfo['start_time']).$acInfo['start_time_week'].date('H:i',$acInfo['start_time']);
                } else {
                    $startTime = date('m月d日',$acInfo['start_time']).$acInfo['start_time_week'].date('H:i',$acInfo['start_time']);
                }
                
                if (date('Y-m-d',$acInfo['start_time']) == date('Y-m-d',$acInfo['end_time'])) {//判断活动结束时间是否同天
                    $endTime = date('H:i',$acInfo['end_time']);
                } else {//不是同天
                    $endTime = date('m月d日',$acInfo['end_time']).$acInfo['end_time_week'].date('H:i',$acInfo['end_time']);
                }
                
                $acTime = $startTime.'-'.$endTime;//组合活动开始结束时间
                
                //报名截止时间
                $acInfo['end_time_apply'] = $acInfo['apply_end_time'];
                $apply_end_time = explode(" ",date('m月d日 H:i',$acInfo['apply_end_time']));
                $acInfo['apply_end_time_week'] = $this->_getTimeWeek($acInfo['apply_end_time']);
                $acInfo['apply_end_time'] = $apply_end_time;
                
//                if (($acInfo['switch_status'] & 4)) {//开启签到
//                    //显示周几
//                    $checkin_start_time = explode(" ",date('m月d日 H:i',$acInfo['checkin_start_time']));
//                    $acInfo['checkin_start_time_week'] = $this->_getTimeWeek($acInfo['checkin_start_time']);
//
//                    $checkin_end_time = explode(" ",date('m月d日 H:i',$acInfo['checkin_end_time']));
//                    $acInfo['checkin_end_time_week'] = $this->_getTimeWeek($acInfo['checkin_end_time']);
//
//                    if ($tempTime == date('Y-m-d',$acInfo['checkin_start_time'])) {//判断签到开始时间是否同天
//                        $checkinStartTime = date('m月d日',$acInfo['checkin_start_time']).$acInfo['checkin_start_time_week'].date('H:i',$acInfo['checkin_start_time']);
//                    } else {
//                        $checkinStartTime = date('m月d日',$acInfo['checkin_start_time']).$acInfo['checkin_start_time_week'].date('H:i',$acInfo['checkin_start_time']);
//                    }
//                    if (date('Y-m-d',$acInfo['checkin_start_time']) == date('Y-m-d',$acInfo['checkin_end_time'])) {//判断签到结束时间是否同天
//                        $checkEndTime = date('H:i',$acInfo['checkin_end_time']);
//                    } else {
//                        $checkEndTime = date('m月d日',$acInfo['checkin_end_time']).$acInfo['checkin_end_time_week'].date('H:i',$acInfo['checkin_end_time']);
//                    }
//
//                    $acCheckInTime = $checkinStartTime.'-'.$checkEndTime;//组合签到开始结束时间
//                }
            } else {
                //活动开始时间
                $startTime = date('Y年m月d日',$acInfo['start_time']).$acInfo['start_time_week'].date('H:i',$acInfo['start_time']);
                $endTime = date('Y年m月d日',$acInfo['end_time']).$acInfo['start_time_week'];
                $acTime = $startTime.'-'.$endTime;//组合活动开始结束时间
//                if (($acInfo['switch_status'] & 4)) {
//                    //显示周几
//                    $checkin_start_time = explode(" ",date('m月d日 H:i',$acInfo['checkin_start_time']));
//                    $acInfo['checkin_start_time_week'] = $this->_getTimeWeek($acInfo['checkin_start_time']);
//
//                    $checkin_end_time = explode(" ",date('m月d日 H:i',$acInfo['checkin_end_time']));
//                    $acInfo['checkin_end_time_week'] = $this->_getTimeWeek($acInfo['checkin_end_time']);
//
//                    $checkinStartTime = date('Y年m月d日',$acInfo['checkin_start_time']).$acInfo['checkin_start_time_week'];
//                    $checkEndTime = date('Y年m月d日',$acInfo['checkin_end_time']).$acInfo['checkin_end_time_week'].date('H:i',$acInfo['checkin_end_time']);
//                    $acCheckInTime = $checkinStartTime.'-'.$checkEndTime;//组合签到开始结束时间
//                }
            }
            
            /********************优化结束***********************/
            

            //活动海报
            $img = json_decode($acInfo['img'],true);
            if (is_array($img) && $img) {
                $acInfo['img'] = $img['url'] ? $img['url'] : [];
            } else {
                $acInfo['img'] = [];
            }
            
            //活动图片
            $images = json_decode($acInfo['images'],true);
            if (is_array($images) && $images) {
                $acInfo['images'] = $images ? $images : [];
                $imgCount = COUNT($images);
                $acInfo['imgCount'] = $imgCount;
            } else {
                $acInfo['images'] =  [];
            }

            $this->getView()->assign('acTime',$acTime);
            //$this->getView()->assign('acCheckInTime',$acCheckInTime);
            $this->getView()->assign("info", $acInfo);
        }
        $this->getView()->assign('toonType',$getParams['toon_type']);
        $this->getView()->display('Apply/share-active-info.html');
    }
    
    //公开活动
    public function openActivityListAction() {
        $queryResult = array();
        $getParam = $this->request->getQuery();
        
        if (!$getParam['fid'] && !$getParam['mark']) {
            exit($this->_mixDataForJosn(self::ERR_PARAM,'缺少必要参数','','',''));
        }
        Fn::writeLog("share/openactivitylist: 记录公开活动参数:".json_encode($getParam));
        
        if (1 == $getParam['mark']) {
            $getBaseToonParams = Toon::getBaseToonParams('portal');
        } else if (2 == $getParam['mark']) {
            $getBaseToonParams = Toon::getBaseToonParams('group');
        }
        
        if (!$getBaseToonParams) {
            Fn::writeLog('share/openactivitylist:获取toon平台通用参数失败， $getBaseToonParams:'.json_encode($getBaseToonParams));
            exit($this->_mixDataForJosn(self::ERR_PARAM,'获取toon平台通用参数失败','','',''));
        }

        $url = Yaf_Registry::get('config')->get('site.info.url');
        if (empty($url)) {
            Fn::writeLog('share/openactivitylist:获取conf.ini的site.info.url参数失败， $url:'.$url);
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
            exit($this->_mixDataForJosn(0,'error','','',''));
        }
    }
    
    /**
     * 获取个人公开接口数据
     */
    public function getOpenListAction() {
        $getParam = $this->request->getQuery();
        $acModel = new ActivityModel();
        $feedModel = new ToonModel();
        $time = time();
        Fn::writeLog("share/getOpenList: 记录公开活动参数:".json_encode($getParam));
        
        empty($getParam['session_id']) && Fn::outputToJson(self::ERR_PARAM,'缺少用户信息');
        empty($getParam['fid']) && Fn::outputToJson(self::ERR_PARAM,'缺少用户名片');
        empty($getParam['mark']) && Fn::outputToJson(self::ERR_PARAM,'缺少参数标识');
        empty($getParam['frame']) && Fn::outputToJson(self::ERR_PARAM,'缺少关系映射');
        
        $offset = intval($this->request->getQuery('offset', 0));
        $limit  = intval($this->request->getQuery('limit', 10));
        
        if (!in_array($getParam['frame'], array('sf','ff','af'))) {
            Fn::outputToJson(self::ERR_PARAM,'缺少关系映射');
        }
        
        if ('sf' == $getParam['frame']) {
           Fn::outputToJson(0,'ok',[]);
        }
        $acList = $acModel->getByOpenList($getParam['fid'],$time,$getParam['frame'],intval($getParam['mark']),$offset,$limit);
        empty($acList) && Fn::outputToJson(0,'ok',[]);
    
        foreach($acList as $key=>$val) {
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
            if (1 == $val['isgroup']) {
                $feed_id = $val['fid'];
            } else {
                $feed_id = $val['c_fid'];
            }
        
            $feedArray = $feedModel->getFeedInfoByRedis($feed_id);
            empty($feedArray) && Fn::writeLog("参与列表获取用户信息失败");
            unset($feed_id);
    
            $acList[$key]['price'] = floatval($val['price']);
            $acList[$key]['img'] = json_decode($val['img'],true);
            $acList[$key]['avatarId'] = isset($feedArray['avatarId']) ? $feedArray['avatarId'] : '';
            $acList[$key]['username'] = isset($feedArray['title']) ? $feedArray['title'] : '';
            $acList[$key]['ac_status'] = $ac_status;
            $acList[$key]['status'] = $status;
        }
        Fn::outputToJson(0, 'ok', $acList);
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
    
    /**
     * @param $time
     * @param int $i
     * @return string
     */
    private function _getTimeWeek($time) {
        $weekarray = array("日","一", "二", "三", "四", "五", "六");
        //$oneD = 24 * 60 * 60;
        return "周" . $weekarray[date("w", $time)];
    }
}