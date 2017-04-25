<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2017-01-12
 * @Time: 2017-01-12 16:16
 */
class ShareModel extends BaseModel {
    public $_table = 'ac_activity';
    public $_dbString = 'portal';
    /**
     * 公开活动--个人
     * @param $id--名片id
     * @param $time
     * @return array
     */
    public function getByOpenFeedList($id,$time,$frame) {
        
        $whereSql = " WHERE isgroup = 0 AND c_fid = '{$id}' AND end_time > '{$time}' AND status = 1 ";
        if ('af' == $frame) {
            $whereSql .= ' AND publicity = 1 ';
        }
        
        $sql = "SELECT id,c_fid AS feedId,title,create_time AS createdTimestamp, img AS image,start_time,end_time FROM ".$this->_table. " $whereSql ORDER BY create_time DESC ,id DESC ";
        return $this->getRowsBySql($sql);
    }
    
    
    /**
     * 公开活动-- 群组
     * @param $id
     * @param $time
     */
    public function getByOpenGroupList($id,$time,$frame) {
        $whereSql = " WHERE isgroup = 1 AND fid = '{$id}' AND end_time > '{$time}' AND status = 1";
        if ('af' == $frame) {
            $whereSql .= ' AND publicity = 1 ';
        }
        
        $sql = "SELECT id,fid AS groupId,title, create_time AS createdTimestamp,img AS image,start_time,end_time FROM ".$this->_table."  $whereSql ORDER BY create_time DESC,id DESC ";
        return $this->getRowsBySql($sql);
    }
    
    /**
     * 获取该活动报名个数
     * @param $ac_id
     * @return mixed
     */
    public function getApplierCount($ac_id) {
        $sql = "SELECT `ac_id` AS `id`, COUNT(id) AS applierCount  FROM `ac_apply` WHERE `ac_id` = '{$ac_id}' AND `verify_status`= 1 GROUP BY `ac_id` ";
        $queryResult = $this->getRowsBySql($sql);
        if ($queryResult) {
            return $queryResult[0]['applierCount'];
        }
        
    }
    
}