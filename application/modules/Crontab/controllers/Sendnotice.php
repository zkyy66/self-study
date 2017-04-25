<?php

/**
 * @description 定时发活动通知
 * @author liweiwei
 */

class SendnoticeController extends Controller
{
    public $noticeModel, $toonModel;
    
    public function init()
    {
        parent::init();
        $this->noticeModel = new NoticeModel();
        $this->toonModel   = new ToonModel();
    }
    
    /**
     * 查看所有内容
     */
    public function showAction()
    {
        $type = $this->request->getQuery('type', 1);
        if ($type == 1) {
            $key = $this->noticeModel->mcKey;
        } else {
            $key = $this->noticeModel->failedMcKey;
        }
        $list = $this->noticeModel->showAll($key);
        var_dump($list);
    }
    

    public function delAction()
    {
//         $mcKey = 'Activity::notice::list';
//         $r = $this->noticeModel->delByKey($mcKey);
//         var_dump($r);
    }
    
    /**
     * pop一个，并发通知
     * 每次请求执行55s，防止多个进程开启
     */
    public function getAction()
    {
        $startTime = microtime(true); // 开始执行时间，用于计算本次执行时间
        $sendNum = 0; // 本次成功处理的条数
        
        while(true) {
            if (microtime(true) - $startTime >= 55) {
                exit('['.date("Y-m-d H:i:s")."] time over 55s \n");
            }
            
            $len = $this->noticeModel->getLen();
            if (!$len) {
                sleep(5);
            } else {
                for ($i=0; $i<$len; $i++) {
                    $info = $this->noticeModel->getOne();
                    
                    if (!$info) {
                        continue;
                    }
                    $ret = Fn::sendNoticeForActivity($info['fromFeedId'], $info['toFeedId'], $info['toUid'], $info['contentArr']);
                    if (!$ret) {
                        // 没发成功的，添加到失败的队列里面,并记录log
                        $this->noticeModel->addToList($info, $this->noticeModel->failedMcKey);
                    } else {
                        $sendNum++;
                    }
                    // 时间判断
                    if (microtime(true) - $startTime >= 55) {
                        exit('['.date("Y-m-d H:i:s")."] time over 55s, 本次处理了{$sendNum}条记录，总共{$len}条  \n");
                    }
                }
            }
        }
    }
    
    
    /**
     * 是否开启签到
     * @param string $switch_status 
     */
    public function openCheckin($switch_status)
    {
        return ($switch_status & 1) && ($switch_status & 4);
    }
    
    /**
     * 定时检查，是否有要发签到提醒通知的数据
     */
    public function sendCheckinNoticeAction()
    {
        // 获取未结束的活动
        $acModel = new ActivityModel();
        $time    = time();
        $acList  = $acModel->getListForActivity($time);
        if (!$acList) {
            exit('['.date("Y-m-d H:i:s")."] 本次没有要处理的记录\n");
        }
        
        // 本次发送个数
        $sendNum = 0;
        
        $logPrefix = "crontab/sendnotice/sendcheckinnotice: 签到通知-";
        foreach ($acList as $key => $val) {
            // 如果未开启签到直接跳出
            if (!$this->openCheckin($val['switch_status'])) {
                Fn::writeLog("{$logPrefix}未开启签到：id:{$val['id']}, record_notice:{$val['record_notice']}, switch_status:{$val['switch_status']}");
                continue;
            }
            // 如果已发过了，直接跳出
            if ($val['record_notice'] & 2) {
                Fn::writeLog("{$logPrefix}通知已发过了：id:{$val['id']}, record_notice:{$val['record_notice']}, switch_status:{$val['switch_status']}");
                continue;
            }
            // 如果签到时间距离现在，超过1个小时，直接跳出
            $calculateCheckinTime = $val['checkin_start_time'] - $time;
            if ($calculateCheckinTime > 60*60) {
                Fn::writeLog("{$logPrefix}还不到时间：id:{$val['id']}, difftime:{$calculateCheckinTime}");
                continue;
            }
            // 没有报名成功的，直接跳出
            $sendList = $acModel->getApplyInfo(2, $val['id']);
            if (empty($sendList)) {
                Fn::writeLog("{$logPrefix}没有人员信息：id:{$val['id']}, record_notice:{$val['record_notice']}, switch_status:{$val['switch_status']}, sendList:".json_encode($sendList));
                continue;
            }
            
            // 加到通知队列
            if (1 == $val['isgroup']) {
                $feedID   = $val['fid'];
                $feedType = "g";
            } else {
                $feedID   = $val['c_fid'];
                $feedType = "c";
            }
            
            Fn::writeLog("{$logPrefix}需要发通知：id:{$val['id']}, record_notice:{$val['record_notice']}, switch_status:{$val['switch_status']}");
            
            $message = "您参加的活动【".$val['title']."】，将于".date('m月d日 H:i', $val['checkin_start_time'])."开始签到，请安排好时间前往会场签到。";
            $checkInNotice = $this->_mixDataSendNotice($feedID, $sendList, $message, $feedType);
            if ($checkInNotice) {
                try {
                    $ret = $acModel->updateRecordNotice($val['id'], 2, $val['record_notice']);
                    Fn::writeLog("{$logPrefix}更新数据库结果：".$ret);
                } catch (Exception $e){
                    Fn::writeLog("{$logPrefix} 更新数据库record_notice=2失败：".$e->getMessage());
                }
            } else {
                Fn::writeLog("{$logPrefix}加入队列失败：".$checkInNotice." acId:{$val['id']}, sendList:".json_encode($sendList));
            }
            
            $sendNum++;
        }
        Fn::writeLog("crontab/sendnotice/sendcheckinnotice: 本次处理了：{$sendNum}条数据 ");
        exit("本次处理了：{$sendNum}条数据 ");
    }
    
    /**
     * 定时检查，是否有要发开始提醒通知的数据
     */
    public function sendStartNoticeAction()
    {
        // 获取未结束的活动
        $acModel = new ActivityModel();
        $time    = time();
        $acList  = $acModel->getListForActivity($time);
        if (!$acList) {
            exit('['.date("Y-m-d H:i:s")."] 本次没有要处理的记录\n");
        }
        
        // 本次发送个数
        $sendNum = 0;
        
        $logPrefix = "crontab/sendnotice/sendStartNotice: 开始通知-";
        foreach ($acList as $key => $val) {
            // 如果已发过了，直接跳出
            if ($val['record_notice'] & 1) {
                Fn::writeLog("{$logPrefix}通知已发过了：activity_id:{$val['activity_id']}, record_notice:{$val['record_notice']}, switch_status:{$val['switch_status']}");
                continue;
            }
            // 如果开始时间距离现在超过1个小时，直接跳出
            $calculateStartTime = $val['start_time'] - $time;
            if ($calculateStartTime > 60*60) {
                Fn::writeLog("{$logPrefix}还不到时间：activity_id:{$val['activity_id']}, difftime:{$calculateStartTime}");
                continue;
            }
            // 没有报名的，直接跳出
            $sendList = $acModel->getApplyInfo(1, $val['activity_id']);
            if (empty($sendList)) {
                Fn::writeLog("{$logPrefix}没有人员数据：activity_id:{$val['activity_id']}, record_notice:{$val['record_notice']}, switch_status:{$val['switch_status']}");
                continue;
            }
            
            // 加到通知队列
            if (1 == $val['is_group']) {
                $feedID   = $val['group_feed_id'];
                $feedType = "g";
            } else {
                $feedID   = $val['single_feed_id'];
                $feedType = "c";
            }
            
            Fn::writeLog("{$logPrefix}需要发通知：id:{$val['activity_id']}, record_notice:{$val['record_notice']}, switch_status:{$val['switch_status']}");
            
            // 加入通知队列
            $message = "您参加的活动【".$val['title']."】，将于".date('m月d日 H:i', $val['start_time'])."开始，请安排好时间准时参加。";
            $noticeStatus = $this->_mixDataSendNotice($feedID, $sendList, $message, $feedType);
            if ($noticeStatus) {
                try {
                    $ret = $acModel->updateRecordNotice($val['activity_id'], 1, $val['record_notice']);
                    Fn::writeLog("{$logPrefix}更新数据库结果：".$ret);
                } catch (Exception $e) {
                    Fn::writeLog("{$logPrefix}更新数据库失败：".$e->getMessage());
                }
            } else {
                Fn::writeLog("{$logPrefix}插入队列失败：".$noticeStatus." acid:{$val['activity_id']} sendLIst:".json_encode($sendList));
            }
            
            $sendNum++;
        }
        
//         Fn::writeLog("crontab/sendnotice/sendcheckinnotice: 本次处理了：{$sendNum}条数据，总共{$totalNum}条 ");
        exit("本次处理了：{$sendNum}条数据 ");
    }
    
    
    /**
     * 废弃
     * 活动开始时间/签到时间前一小时发通知
     * 需要记录活动数据
     */
    public function checksendNoticeAction() {
        $acModel = new ActivityModel();
        $time = time();
        $acInfo = $acModel->getListForActivity($time);
        
        if (!$acInfo) {
            exit('['.date("Y-m-d H:i:s")."] 本次没有要处理的记录\n");
        }
        
        $recordApllyNum = $recordCheckNum = 0;//用于统计发送通知次数
        foreach ($acInfo as $key => $val) {
            if (($val['record_notice'] & 1) && ($val['record_notice'] & 2)) {
                continue;
            }
            
            //计算活动开始时间前一小时
            $calculateStartTime = $val['start_time'] - $time;
            $calculateCheckinTime = $val['checkin_start_time'] - $time;
            //优化
            if(!($val['record_notice'] & 1)) {
	            if (($val['switch_status'] & 1) && $calculateStartTime <= 60*60) {
                    
	                $message = "你参加的活动【".$val['title']."】，将于".date('m月d日 H:i',$val['start_time'])."开始，请安排好时间准时参加。";
	                $applyList = $acModel->getApplyInfo(1,$val['id']);
	                if (1 == $val['isgroup']) {
	                    $feedID = $val['fid'];
	                    $feedType = "g";
	                } else {
	                    $feedID = $val['c_fid'];
	                    $feedType = "c";
	                }
	                //发送通知
	                $noticeStatus = $this->_mixDataSendNotice($feedID,$applyList,$message,$feedType);
	                //记录通知标识
                    if ($noticeStatus) {
                        try{
                            $acModel->updateRecordNotice($val['id'], 1, $val['record_notice']);
                        }catch (Exception $e) {
                            Fn::writeLog("更新数据库记录状态：".$e->getMessage(),'ac_cron_checksendNotice');
                        }
                    }
                    Fn::writeLog("报名发送通知状态：".$noticeStatus,'ac_cron_checksendNotice');
                    $recordApllyNum++;
                    
	                //签到开关
	                if (!($val['record_notice'] & 2)) {
	                    //开关操作switch_status为组合值,所以采取以下判断操作
		                if ((($val['switch_status'] & 5) || ($val['switch_status'] & 7)) && $calculateCheckinTime <= 60*60) {
		                    $message = "您参加的活动【".$val['title']."】，将于".date('m月d日 H:i',$val['checkin_start_time'])."开始签到，请安排好时间前往会场签到。";

		                    $checkInList = $acModel->getApplyInfo(2,$val['id']);
                            //发送通知
		                    $checkInNotice = $this->_mixDataSendNotice($feedID,$checkInList,$message,$feedType);
		                    if ($checkInNotice) {
		                        try{
                                    $acModel->updateRecordNotice($val['id'], 2, $val['record_notice']);
                                }catch (Exception $e){
                                    Fn::writeLog("更新数据库记录状态：".$e->getMessage(),'ac_cron_checksendNotice');
                                }
                            }
                            Fn::writeLog("签到发送通知状态：".$checkInNotice,'ac_cron_checksendNotice');
                            $recordCheckNum++;
		                }
	                }

	            }

            }
            
        }
        exit('['.date("Y-m-d H:i:s")."]--本次统计报名次数：$recordApllyNum.--本次统计发送签到次数：$recordCheckNum\n");
    }
    
    /**
     * 组合数据并发送通知
     * @param $list
     */
    private function _mixDataSendNotice($feedID,$list,$message,$feedType) {
        $status = '';
        if (empty($list)) {
            Fn::writeLog("报名/签到相关人员列表为空");
            return false;
        }
        foreach ($list as $key => $val) {
            $getToonID = User::getUserDetail($val['user_id']);
            $codeData = array(
                'visitor' => array(
                    'feed_id' =>$val['feed_id'],
                    'uid' => $val['user_id']
                ),
                'owner' => array(
                    'feed_id' => $feedID
                )
            );
            
            $contentArr = [
                'url' => Fn::generatePageUrl(3,$val['activity_id'],$codeData,$feedType),
                'msg' => $message
            ];
            
            $noticeInfo = array(
                'fromFeedId' => $feedID,
                'toFeedId' => $val['feed_id'],
                'toUid' => $getToonID['toon_uid'],
                'contentArr' => $contentArr
            );
            
            $status = $this->noticeModel->addToList($noticeInfo);
        }
        return $status;
    }
}