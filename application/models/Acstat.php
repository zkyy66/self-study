<?php

/**
 * 活动统计相关操作model
 * @author liweiwei
 */
class AcstatModel extends BaseModel {
    
    public $_table = 'ac_stat';
    public $_dbString = 'portal';
    
    /**
     * 增加报名人数
     */
    public function incrApplyNum($acId, $num=1)
    {
        if (!$acId || !$num) {
            return 0;
        }
        return $this->_updateStat($acId, 'apply_num', $num);
    }
    /**
     * 减报名人数
     */
    public function decrApplyNum($acId, $num=1)
    {
        if (!$acId || !$num) {
            return 0;
        }
        return $this->_updateStat($acId, 'apply_num', -$num);
    }
    
    /**
     * 增加心动
     * @param $acId
     * @param int $num
     */
    public function incrLoveNum($acId,$num=1) {
        if ($acId || $num) {
            return 0;
        }
        return $this->_updateStat($acId, 'love_num', $num);
    }
    
    /**
     * 减少心动
     * @param $acId
     * @param int $num
     */
    public function decrLoveNum($acId,$num=1) {
        if (!$acId || !$num) {
            return 0;
        }
        return $this->_updateStat($acId, 'love_num', -$num);
    }
    /**
     * 增加签到人数
     */
    public function incrCheckinNum($acId, $num=1)
    {
        if (!$acId || !$num) {
            return 0;
        }
        return $this->_updateStat($acId, 'checkin_num', $num);
    }
    /**
     * 减签到人数
     */
    public function decrCheckinNum($acId, $num=1)
    {
        if (!$acId || !$num) {
            return 0;
        }
        return $this->_updateStat($acId, 'checkin_num', -$num);
    }
    
    /**
     * 记录一条
     * @param array $row ['ac_id', 'apply_num', 'checkin_num']
     * @return number
     */
    public function addOne($row)
    {
        if (empty($row) || empty($row['ac_id'])) {
            Fn::writeLog('AcstatModel::addOne， row信息为空：'.json_encode($row));
            return 0;
        }
        if (!isset($row['create_time'])) {
            $row['create_time'] = time();
        }
        if (!isset($row['update_time'])) {
            $row['update_time'] = time();
        }
        return $this->addGetInsertId($row, true);
    }
    
    /**
     * 更新统计表报名/签到个数
     * @param $acId--活动ID
     * @param $field --更新字段
     * @param $num--个数
     * @return bool|unknown--返回类型
     */
    private function _updateStat($acId, $field, $num)
    {
        $acId = intval($acId);
        $num  = intval($num);
        
        // 获取是否已有记录，没有创建，有的话更新
        // 正常情况下，在创建活动的时候，就应该创建相应的统计信息，这里创建，可能存在统计数据不准的情况。
        $statInfo = $this->getRow(['activity_id'=>$acId]);
        if (empty($statInfo)) {
            $num = ($num < 0) ? 0 : $num;
            $data = ['activity_id'=>$acId, $field=>$num];
            $ret  = $this->addOne($data);
            Fn::writeLog('models/Acstat/_updateStat : 活动没有统计记录，特此添加一条。本次更新内容：'.json_encode($data)." 之前已有的statInfo：".json_encode($statInfo));
            return $ret;
        } else {
            $sql  = "UPDATE $this->_table SET `{$field}` = `{$field}`+{$num} WHERE `activity_id`={$acId}";
            return $this->update($sql);
        }
    }
}