<?php

/**
 * @description 每天检查一次活动发布者的feed信息是否存在
 * @author liweiwei
 */

class CheckfeedController extends Controller
{
    public $acModel, $appModel;
    
    public function init()
    {
        parent::init();
        $this->acModel  = new ActivityModel();
        $this->appModel = new ApplyModel(); 
    }
    
    public function IndexAction()
    {
        $page    = 1;
        $perpage = 100;
        $limit   = ($page-1)*$perpage.','.$perpage;
        $list    = $this->acModel->getAcListByStatus($limit);
        if (!$list) {
            exit('没有要处理的信息');
        }
        
        $num = 0;
        while(!empty($list)) {
            foreach ($list as $k=>$v) {
                // 检查每个活动发布者对应的feed信息是否存在。
                $feedInfo = Toon::getListFeedInfo([$v['c_fid']], 'portal', $errMsg);
                if (!empty($errMsg)) {
                    Fn::writeLog( date('Y-m-d H:i:s').'-crontab/checkfeed/index:- feedId:'.$v['c_fid'].'--'.$errMsg , 'checkfeed_ac_error.log');
                    continue;
                }
                if (!$feedInfo || !isset($feedInfo[0]['userId']) || !$feedInfo[0]['userId']) {
                    $this->acModel->hideOne($v['id']);
                    Fn::writeLog( date('Y-m-d H:i:s').'-crontab/checkfeed/index:- feedId:'.$v['c_fid'].'--'.json_encode($feedInfo) , 'checkfeed_ac_error.log');
                    $num++;
                }
            }
            
            $page++;
            $limit   = ($page-1)*$perpage.','.$perpage;
            $list    = $this->acModel->getAcListByStatus($limit);
        }
        
        exit(date("Y-m-d H:i:s")."-本次处理了{$num}条数据\n");
    }
    
    /**
     * 定时检查报名表中的feed信息
     */
    public function ApplyAction()
    {
        $page    = 1;
        $perpage = 100;
        $limit   = ($page-1)*$perpage.','.$perpage;
        $list    = $this->appModel->getApplyList("status=1", ' `id` DESC', $limit);
        
        if (!$list) {
            exit('没有要处理的信息');
        }
    
        $num = 0;
        while(!empty($list)) {
            foreach ($list as $k=>$v) {
                // 检查每个活动发布者对应的feed信息是否存在。
                $feedInfo = Toon::getListFeedInfo([$v['feed_id']], 'portal', $errMsg);
                if (!empty($errMsg)) {
                    Fn::writeLog( date('Y-m-d H:i:s').'-crontab/checkfeed/apply- feedId:'.$v['feed_id'].'--'.$errMsg , 'checkfeed_apply_error.log');
                    continue;
                }
                if (!$feedInfo || !isset($feedInfo[0]['userId']) || !$feedInfo[0]['userId']) {
                    $this->appModel->changeStatus($v['id'], 2);
                    Fn::writeLog( date('Y-m-d H:i:s').'-crontab/checkfeed/index- feedId:'.$v['feed_id'].'--'.json_encode($feedInfo) , 'checkfeed_apply_error.log');
                    $num++;
                }
            }
    
            $page++;
            $limit   = ($page-1)*$perpage.','.$perpage;
            $list    = $this->appModel->getApplyList("status=1", ' `id` DESC', $limit);
        }
    
        exit(date("Y-m-d H:i:s")."-本次处理了{$num}条数据\n");
    }
}