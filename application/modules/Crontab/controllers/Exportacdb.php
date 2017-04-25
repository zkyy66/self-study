<?php
/**
 * @description活动V3.0数据迁移脚本
 * @author by Yaoyuan.
 * @version: 2017-01-06
 * @Time: 2017-01-06 10:10
 */
class ExportAcDbController extends Controller {
    

    /**
     * 活动主表ac_info报名审核签到开关数据迁移
     * 活动主表老版字段说明：allow_apply:1代表开启,0代表未开启;checktype:0代表开启,1代表未开启;need_checkin:1代表开启,0代表未开启
     * 活动主表新版字段说明 switch_status开关操作说明: 1-报名开关 2-审核开关 4-签到开关
     */
    public function upateSwitchStatusAction() {
       
        $exportModel = new ExportAcDbModel();
        $totalNum = 0;//记录获取活动数据总条数
        $list = $exportModel->getAllList();
       
        $totalNum = COUNT($list);
        
        empty($list) && exit("暂无数据");
        $i = 0;//统计当前处理数据条数

        foreach ($list as $key => $val) {
            $statusFlag = $newCheckType = 0;//记录新状态的值
            //报名开关
            if (1 == $val['allow_apply']) {
                $statusFlag = 1|1;//开启报名
                //审核开关
               if (0 == $val['checktype']) {
                	$statusFlag = $statusFlag | 2;
                   
            	}
	            //签到开关
	            if (1 == $val['need_checkin']) {
	                $statusFlag = $statusFlag | 4;
	            } 
            }

//            echo $val['id'].'*'.$val['allow_apply'].'--'.$val['checktype'].'--'.$val['need_checkin'].'-*-'.$statusFlag."\n";
//            echo '<br>';
            
            $result = $exportModel->updateSwtich_status($val['id'],$statusFlag);
            if ($result) {
                $i++;
            } else if ($result === false){
                Fn::writeLog("活动ID为：".$val['id'].",活动名称为：".$val['title'].":处理失败\n",'ac_export');
            }
        }
        exit("共".$totalNum."条数据;当前处理".$i."条");
    }
    
    
    /**
     *此方法主要作用是备份旧数据的flag值，更新到新字段flagbak中
     */
    public function updateFlagBakAction() {
        $exportModel = new ExportAcDbModel();
        $list = $exportModel->getAllList();

        $totalNum = 0;//记录活动总条数
        empty($list) && exit("暂无数据");
        
        $i = 0;//记录每次处理的条数
        foreach ($list as $key => $val) {
            $result = $exportModel->updateFlagBak($val['id'],$val['flag']);
            
            if ($result) {
                $i++;
            } else if ($result === false) {
                Fn::writeLog("活动ID为：".$val['id']."，活动名称为：".$val['title']."处理失败\n",'ac_flagbak_status');
            }
        }
        exit("共".$totalNum."条数据;当前处理".$i."条");
    }
    
    
    /**
     * 活动后台flag更新
     * 获取flag的备份值flagbak，根据flagbak更新flag字段值
     * 老版活动数据上下墙flag更新值
     * 老版flag字段值为 0-初始状态 1-已读 2-已编辑 4-已修改 8-备选 16-上墙 32-下墙
     * 新版flag字段值为 可操作状态 -1-待审核 1-审核是否通过 2-是否处理 4-是否推荐
     */
    public function backGroundUpateFlagAction() {
        $exportModel = new ExportAcDbModel();
        $list = $exportModel->getFlagBak();
        
        $totalNum = 0;//记录活动数据总条数
        
        empty($list) && exit("暂无数据");
        $totalNum = COUNT($list);
        
        $i = 0;//统计条数
        foreach ($list as $key => $val) {
            $newFlag = '';//记录新状态值
            if ($val['flagbak'] & 16) {
                $newFlag = 1;//审核通过
            } else if ($val['flagbak'] & 32) {
                $newFlag = 0;//未通过
            } else {
                $newFlag = 2;//不处理
            }
//            echo $val['id'].'--'.$val['flag'].'--'.$newFlag."\n";
//            echo '<br>';
            $result = $exportModel->updateFlag($val['id'],$newFlag);
            if ($result) {
                $i++;
            } else if ($result === false){
                Fn::writeLog("活动ID为：".$val['id']."处理失败\n",'ac_flag_status');
            }
        }
        exit("共".$totalNum."条数据;当前处理".$i."条");
    }
    
    
    /**
     * 查看线上数据
     */
    public function showListAction() {
        $exportModel = new ExportAcDbModel();
        $list = $exportModel->getAllData();
        
        foreach ($list as $key => $val) {
            echo "活动ID：".$val['id']."--活动标题:".$val['title']."--是否报名:".$val['allow_apply']."--是否审核:".$val['checktype']."--是否签到:".$val['need_checkin']."--报名截止时间:".date('Y-m-d H:i:s',$val['apply_end_time'])."--签到开始时间:".date('Y-m-d H:i:s',$val['checkin_start_time'])."--签到结束时间:".date('Y-m-d H:i:s',$val['checkin_end_time'])."--组合值:".$val['switch_status']."--flag备份值:".$val['flagbak']."--flag值：".$val['flag']."<br>\n";
        }
    }
    
    /**
     * 获取活动统计数据
     */
    public function getTotalAction() {
        $exportModel = new ExportAcDbModel();
        $getParams = $this->request->getQuery();
        $id = intval($getParams['id']);
        $list = $exportModel->getStatistics($id);
        if (empty($list)) {
            echo "暂无数据";
        }
        foreach ($list as $key => $val) {
            echo "报名人数：".$val['apply_num']."--签到人数：".$val['checkin_num'];
        }
    }
}