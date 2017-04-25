<?php
/**
* @description 定时更新上下墙状态
* @author liweiwei
* @version 2016-11-2上午9:20:07
*/

class ChangeWallTypeController extends Controller
{
    public $wallTaskModel, $acAdminModel, $acModel, $noticeModel;

    public function init()
    {
        parent::init();
        $this->wallTaskModel= new WallTaskModel();
        $this->acAdminModel = new ActivityadminModel();
        $this->acModel      = new ActivityModel();
        $this->noticeModel  = new NoticeModel();
    }
    
    /**
     * 已结束活动保留一个月后，更新status为0
     * 超过一个月则进行伪删除
     */
    public function keepTimeAction() {
        $time = time();
        $overList = array();
        try {
            $overList = $this->wallTaskModel->getOverActivityList($time);
        } catch (Exception $e) {
            Fn::writeLog("查询结果记录:" .$e->getMessage(),'ac_cron_keeptime');
        }
        
        if (!$overList) {
            exit('['.date("Y-m-d H:i:s")."] 本次没有要处理的记录\n");
        }
        $num = 0;
        
        foreach ($overList as $k=>$v) {
            // 结束时间超过一个月才清除
            //30*24*3600
            if ($time - $v['end_time'] < 30*24*3600) {
                continue;
            }
            //更改活动状态
            $this->acModel->changeStatus($v['id']);
            Fn::writeLog("符合条件：".date('Y-m-d H:i:s',$v['end_time']).'--'.$v['id'],'ac_cron_keeptime');
            $num++;
        }
        exit('['.date("Y-m-d H:i:s")."]--本次处理条数：$num\n");
    }
    
    /**
     * 定时将已推荐的已结束的活动取消推荐状态
     */
    public function removeRecommendFlagAction() {
    
        $onwallList = $this->wallTaskModel->getRecommendList();
    
        if (!$onwallList) {
            exit('['.date("Y-m-d H:i:s")."] 本次没有要处理的记录\n");
        }

        $num = 0;
        foreach ($onwallList as $k=>$v) {
            if ($v['end_time'] > time()) {
                continue;
            }
            // 取消推荐
            $flag = $v['flag']^4;
            $this->acAdminModel->changeFlag($v['activity_id'], $flag);
            $num++;
        }
        exit('['.date("Y-m-d H:i:s")."]--本次处理条数：$num\n");
    }
    
    
//     /**
//      * 【已停用】
//      * 定时将上墙的已结束的活动改为下墙状态
//      */
//     public function checkEndTimeAction()
//     {
//         $onwallList = $this->wallTaskModel->getOnwallList();
//         if (!$onwallList) {
//             exit('['.date("Y-m-d H:i:s")."] 本次没有要处理的记录\n");
//         }
        
//         $num = 0;
//         foreach ($onwallList as $k=>$v) {
//             if ($v['end_time'] > time()) {
//                 continue;
//             }
//             // 下墙
//             $this->acAdminModel->changeFlag($v['id'], $this->getDownWallFlagAction($v['flag']));
//             $num++;
//         }
//         exit('['.date("Y-m-d H:i:s")."]--本次处理条数：$num\n");
//     }

//     /**
//      * 【已停用】
//      * 处理定时任务
//      * 定时将需要上墙的活动上墙
//      * 定时将需要下墙的活动下墙
//      */
//     public function taskAction()
//     {
//         $taskList   = $this->wallTaskModel->getTaskList();
//         if (!$taskList) {
//             exit('['.date("Y-m-d H:i:s")."] 本次没有要处理的记录\n");
//         }

//         $num = 0;
//         foreach ($taskList as $k=>$v) {
//             if ($v['time'] > time()) {
//                 continue;
//             }
//             $acInfo = $this->acModel->details($v['ac_id']);
//             if ($v['wall_type'] == 1) { // 上墙
//                 $ret = $this->acAdminModel->changeFlag($v['ac_id'], $this->getUpWallFlagAction($acInfo['flag']));
//                 if ($ret) {
//                     $codeArr = array(
//                             'visitor'=>array('uid'=>$acInfo['uid'], 'feed_id'=>$acInfo['c_fid']),
//                             'owner'=>array('feed_id'=>$acInfo['c_fid']),
//                     );
//                     if ($acInfo['isgroup']) {
//                         $codeArr['owner']['feed_id'] = $acInfo['fid'];
//                         $feedType = 'g'; // 代表群组活动
//                     } else {
//                         $feedType = 'c'; // 代表个人活动
//                     }
//                     $contentArr = [
//                     'url' =>Fn::generatePageUrl(null, $acInfo['id'], $codeArr, $feedType),
//                     'msg' => "你发布的活动【{$acInfo['title']}】已上墙，快去查看吧",
//                     'needHeadFlag'=>0,
//                     "buttonTitle" => '去看看',
//                     ];

//                     $noticeInfo = array('fromFeedId'=>$acInfo['c_fid'], 'toFeedId'=>$acInfo['c_fid'], 'toUid'=>$acInfo['uid'], 'contentArr'=>$contentArr);
//                     $this->noticeModel->addToList($noticeInfo);
//                 }
//             } else if ($v['wall_type'] == 2) { // 下墙
//                 $ret = $this->acAdminModel->changeFlag($v['ac_id'], $this->getDownWallFlagAction($acInfo['flag']));
//             }
//             if (!$ret) {
//                 continue;
//             }
//             // 更新定时任务的status为1
//             $this->wallTaskModel->changeStatus($v['id'], 1);
//             $num++;
//         }
//         exit('['.date("Y-m-d H:i:s")."]--本次处理条数：$num\n");
//     }
    
//     /**
//      * 【已停用】
//      * 将一个flag去掉上墙状态，新增下墙状态，并将结果返回
//      */
//     private function getDownWallFlagAction($flag)
//     {
//         // 去掉上墙的状态，添加下墙的状态
//         // flag:上墙状态 0-初始状态 1-已读 2-已编辑 4-已修改 8-备选 16-上墙 32-下墙
//         if ($flag&16) {
//             $flag = $flag^16;
//         }
//         if (!($flag&32)) {
//             $flag = $flag|32;
//         }
//         return $flag;
//     }
    
//     /**
//      * 【已停用】
//      * 将一个flag去掉下墙状态，新增上墙状态，并将结果返回
//      */
//     private function getUpWallFlagAction($flag)
//     {
//         // 去掉下墙的状态，添加上墙的状态
//         if ($flag&32) {
//             $flag = $flag^32;
//         }
//         if (!($flag&16)) {
//             $flag = $flag|16;
//         }
//         return $flag;
//     }
    
}