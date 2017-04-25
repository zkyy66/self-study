<?php

/**
 * @description 统计活动数据
 * @author liweiwei
 */

class AcstatController extends Controller
{
    public $acstatModel, $acModel, $applyModel;
    
    public function init()
    {
        parent::init();
        $this->acstatModel = new AcstatModel();
        $this->acModel     = new ActivityModel();
        $this->applyModel  = new ApplyModel();
    }
    
    /**
     * 统计活动的报名数和签到数，到ac_stat表
     */
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
                if (empty($v)) {
                    continue;
                }
                // 获取每个活动的报名人数和签到人数，并记录到ac_stat表
                $applyNum   = $this->applyModel->getApplyNum($v['activity_id'], 1);
                $checkinNum = $this->applyModel->getCheckinNum($v['activity_id'], 1);
                
                $ret = $this->acstatModel->addOne(['activity_id'=>$v['id'], 'apply_num'=>intval($applyNum), 'checkin_num'=>intval($checkinNum)]);
                if ($ret) {
                    $num++;
                }
            }
            $page++;
            $limit   = ($page-1)*$perpage.','.$perpage;
            $list    = $this->acModel->getAcListByStatus($limit);
        }
    
        exit(date("Y-m-d H:i:s")."-本次处理了{$num}条数据\n");
    }
    
}