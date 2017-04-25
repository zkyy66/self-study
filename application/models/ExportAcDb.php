<?php
/**
 * @description 活动V3.0数据迁移
 * @author by Yaoyuan.
 * @version: 2017-01-06
 * @Time: 2017-01-06 10:18
 */
class ExportAcDbModel extends BaseModel {
    public $_table = 'ac_activity';
    public $_dbString = 'portal';
//    public $_dbString = 'local';
    
    /**
     * 获取全部活动数据
     * @return array
     */
    public function getAllList() {
        $where = "id > 0";
        $order = " id ASC ";
        $fields = array('id','title','allow_apply','checktype','need_checkin','flag');
        return $this->getRows($where,$fields,$order);
    }

    /**
     * 获取旧数据flag值
     * @return array
     */
    public function getFlagBak() {
        $where = "id > 0";
        $order = " id ASC ";
        $fields = array('id','flag','flagbak');
        return $this->getRows($where,$fields,$order);
    }
    
    /**
     * 根据活动ID更新活动报名审核签到开关值
     * @param $id
     * @param $swtich_status
     * @return bool|unknown
     */
    public function updateSwtich_status($id,$swtich_status) {
        
        $data = $where = [];
        $data = [
            'switch_status' => $swtich_status
        ];
        $where = [
            'id' => $id
        ];
        
        return $this->update($data,$where);
    }
    
    /**
     * 更新后台flag字段值
     * @param $id
     * @param $flag
     * @return bool|unknown
     */
    public function updateFlag($id,$flag) {
        $data = $where = [];
        $data = [
            'flag' => $flag
        ];
        $where = [
            'id' => $id
        ];
        return $this->update($data,$where);
    }
    
    /**
     * 此方法主要是备份旧数据的flag状态值
     * @param $id
     * @param $flag
     * @return bool|unknown
     */
    public function updateFlagBak($id,$flag) {
        $data = $where = [];
        $data = [
            'flagbak' => $flag
        ];
        $where = [
            'id' => $id
        ];
        return $this->update($data,$where);
    }
    
    /**
     * 获取活动所有数据
     */
    public function getAllData() {
        $where = " id > 0";
        $order = " id DESC ";
        return $this->getRows($where,'',$order);
    }
    
    /**
     * 获取活动统计数据
     * @param $id
     */
    public function getStatistics($id) {
        $this->_table = "ac_stat";
        $where = "ac_id = {$id}";
        return $this->getRows($where);
    }
}