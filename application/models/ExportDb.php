<?php
/**
* @description 数据迁移用到的model
* @author liweiwei
* @version 2016-11-8上午10:33:05
*/
class ExportDbModel extends BaseModel
{
    
    public $newDb = 'portal';
    
    /**
     * 从旧库中抓取活动基本信息列表
     * @return array
     */
    public function getAcInfoList($limit = null)
    {
        $this->_table    = 'activity';
        $this->_dbString = 'olddb';
        
        // 从旧库的主库取出数据
        return $this->getRows("id > 0", array(), '`id` ASC', $limit, $this->_table, $this->_dbString, true);
    }
    
    /**
     * 从新库中抓取活动基本信息列表
     * @return array
     */
    public function getAcFromNew($limit = null)
    {
        $this->_table    = 'ac_activity';
        $this->_dbString = $this->newDb;
    
        // 从旧库的主库取出数据
        return $this->getRows("uid = 0", array(), '`id` ASC', $limit, $this->_table, $this->_dbString, true);
    }
    
    /**
     * @return array
     */
    public function getStat($db, $table, $where)
    {
        if ($db == 'new') {
            
        }
        $this->_dbString = 'olddb';
        $this->_table    = 'activity';
        $oldStat = array();
        // 旧表中的活动记录数量
        $sql    = "SELECT count(1) AS total FROM `activity` WHERE `id`>0 LIMIT 1";
        $result = $this->getRowsBySql($sql, $this->_dbString, true);
        $oldStat['ac_num'] = $result[0]['total'];
        
        // 旧表中的活动申请记录数量
        $sql    = "SELECT count(1) AS total FROM `apply` WHERE `id`>0 LIMIT 1";
        $result = $this->getRowsBySql($sql, $this->_dbString, true);
        $oldStat['apply_num'] = $result[0]['total'];
        
        // 旧表中的操作记录数量
        $sql    = "SELECT count(1) AS total FROM `op_record` WHERE `aid`>0 LIMIT 1";
        $result = $this->getRowsBySql($sql, $this->_dbString, true);
        $oldStat['op_num'] = $result[0]['total'];
        
        // 从旧库的主库取出数据
        $this->_dbString = $this->newDb;
        
        // 新表中的活动记录数量
        $sql    = "SELECT count(1) AS total FROM `ac_activity` WHERE `id`<3200 LIMIT 1";
        $result = $this->getRowsBySql($sql, $this->_dbString, true);
        $oldStat['new_ac_num'] = $result[0]['total'];
        
        // 新表中的活动申请记录数量
        $sql    = "SELECT count(1) AS total FROM `ac_apply` WHERE `id`<6000 LIMIT 1";
        $result = $this->getRowsBySql($sql, $this->_dbString, true);
        $oldStat['new_apply_num'] = $result[0]['total'];
        
        // 新表中的操作记录数量
        $sql    = "SELECT count(1) AS total FROM `ac_op_record` WHERE `ac_id`>0 LIMIT 1";
        $result = $this->getRowsBySql($sql, $this->_dbString, true);
        $oldStat['new_op_num'] = $result[0]['total'];
        
        
        $commands = [
        'count' => 'activity',
        'query' =>  [
            '_id'=>['$gt'=>0]
            ],
        ];
//         $result = MongoDB::getInstance('portal')->runCommand($commands,'activity');
        $result = MongoData::getInstance('portal')->command($commands);
        $oldStat['poi_num'] = $result->n;
        
        return $oldStat;
    }
    
    /**
     * 从旧库中抓取活动报名信息列表
     * $limit string 0, 10
     * @return array
     */
    public function getAcApplyList($limit=null)
    {
        $this->_table    = 'apply';
        $this->_dbString = 'olddb';
    
        // 从旧库的主库取出数据
        return $this->getRows("id > 0", array(), '`id` ASC', $limit, $this->_table, $this->_dbString, true);
    }
    
    /**
     * 从新库中抓取活动报名信息列表
     * $limit string 0, 10
     * @return array
     */
    public function getApplyFromNew($limit=null)
    {
        $this->_table    = 'ac_apply';
        $this->_dbString = $this->newDb;
    
        // 从旧库的主库取出数据
        return $this->getRows("uid = 0", array(), '`id` ASC', $limit, $this->_table, $this->_dbString, true);
    }
    
    /**
     * 从旧库中抓取运营后台的操作记录
     * @return array
     */
    public function getOpList($limit = null)
    {
        $this->_table    = 'op_record';
        $this->_dbString = 'olddb';
    
        // 从旧库的主库取出数据
        return $this->getRows("aid > 0", array(), '`tm` ASC', $limit, $this->_table, $this->_dbString, true);
    }
    
    /**
     * 向新库的活动基本信息表中填充数据
     */
    public function addAcInfo($row)
    {
        $this->_table    = 'ac_activity';
        $this->_dbString = $this->newDb;
        return $this->addGetInsertId($row, true);
    }
    
    /**
     * 向新库的活动扩展表中填充数据
     */
    public function addAcExt($row)
    {
        $this->_table    = 'ac_ext';
        $this->_dbString = $this->newDb;
        return $this->addGetInsertId($row, true);
    }
    
    /**
     * 向新库的活动报名表中填充数据
     */
    public function addAcApply($row)
    {
        $this->_table    = 'ac_apply';
        $this->_dbString = $this->newDb;
        return $this->addGetInsertId($row, true);
    }
    
    /**
     * 向新库的运营操作记录中，导入数据
     */
    public function addOp($row)
    {
        $this->_table    = 'ac_op_record';
        $this->_dbString = $this->newDb;
        return $this->addGetInsertId($row, true);
    }
    
    /**
     * 将位置信息植入mongo
     * @param $acId 活动id
     * @param $poiInfo 经纬度数组 array('longtitude', 'latitude')
     */
    public function addPoiToMongo($acId, $poiInfo)
    {
        $acModel = new ActivityModel();
        return $acModel->addMongodb($acId, $poiInfo);
    }
    
    /**
     * 更新活动表的uid
     * @param int $acId
     * @param int $uid
     * @return 影响的条数
     */
    public function updateAcUid($acId, $uid)
    {
        $this->_table    = 'ac_activity';
        $this->_dbString = $this->newDb;
        return $this->update(array('uid'=>$uid), "id = {$acId}" );
    }
    
    /**
     * 更新报名表的uid
     * @param int $applyId
     * @param int $uid
     * @return 影响的条数
     */
    public function updateApplyUid($applyId, $uid)
    {
        $this->_table    = 'ac_apply';
        $this->_dbString = $this->newDb;
        return $this->update(array('uid'=>$uid), "id = {$applyId}" );
    }
    
}