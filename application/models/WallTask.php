<?php
/**
* @description 活动定时任务的model
* @author liweiwei
* @version 2016-11-1上午11:55:26
*/

class WallTaskModel extends BaseModel 
{
    public $_table = "ac_wall_task";
    public $_dbString = "portal";
//     public $_dbString = "local";
    
    /**
     * 将任务改为已处理
     * @param int $id
     * @param int $status
     * @return int
     */
    public function changeStatus($id, $status)
    {
        $id     = intval($id);
        $status = intval($status);
        if (!$id) {
            return false;
        }
        return $this->update(array('status'=>$status, 'update_time'=>time()), "id = {$id}");
    }
    
    /**
     * 获取已结束活动列表
     * @return array
     */
    public function getOverActivityList($time)
    {
        $sql = "SELECT activity_id AS id,end_time FROM `ac_activity` WHERE {$time} > end_time  AND status = 1";
        return $this->getRowsBySql($sql);
    }
    /**
     * 获取在墙上的活动列表
     * @return array
     */
    public function getOnwallList()
    {
        $sql = "SELECT * FROM `ac_activity` WHERE `flag`&16";
        return $this->getRowsBySql($sql);
    }
    /**
     * 获取待处理的定时任务
     * @return array
     */
    public function getTaskList()
    {
        $sql = "SELECT * FROM `ac_wall_task` WHERE `status` = 0";
        return $this->getRowsBySql($sql);
    }
    
    /**
     * 新增到任务表
     * @param int $row
     * @return int
     */
    public function addWallTask($row)
    {
        if (!$row) {
            return 0;
        }
        return $this->addGetInsertId($row);
    }
    
    /**
     * 获取获取已推荐的的活动
     * @return array
     */
    public function getRecommendList()
    {
        $sql = "SELECT * FROM `ac_activity` WHERE flag & 4";
        return $this->getRowsBySql($sql);
    }
}