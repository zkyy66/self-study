<?php
class ApplyModel extends BaseModel
{
    public $_table = "ac_apply";
    public $_dbString = "portal";

    
    /**
     * 获取某个活动审核通过的人数或待审核的人数
     * @param int $acId
     * @param int $verifyStatus 0-待审核 1-审核通过 2-审核未通过
     * @return int 返回结果条数；参数不对时，返回-1；
     */
    public function getApplyNum($acId, $verifyStatus = 0)
    {
        $acId         = intval($acId);
        $verifyStatus = intval($verifyStatus);
        
        if (!$acId || !in_array($verifyStatus, array(0,1,2))) {
            return 0;
        }
        $where  = "`activity_id` = {$acId} and `verify_status` = {$verifyStatus} AND `status`=1";
        $sql    = "SELECT count(1) AS total FROM `{$this->_table}` WHERE {$where} LIMIT 1";
        $result = $this->getRowsBySql($sql);

        return $result[0]['total'];
    }
    
    /**
     * 获取某个活动签到的人数和未签到的人数
     * @param int $acId
     * @param int $checkinStatus
     * @return number
     */
    public function getCheckinNum($acId, $checkinStatus = 1)
    {
        $acId          = intval($acId);
        $checkinStatus = intval($checkinStatus);
        
        if (!$acId || !in_array($checkinStatus, array(0,1))) {
            return 0;
        }
        $where  = "`activity_id` = {$acId} and `verify_status` = 1 and `checkin_status`={$checkinStatus} AND `status`=1";
        $sql    = "SELECT count(1) AS total FROM `{$this->_table}` WHERE {$where} LIMIT 1";
        $result = $this->getRowsBySql($sql);
    
        return $result[0]['total'];
    }
    
    /**
     * 判断某个手机号是否已经报名指定活动
     * @param int $acId
     * @param string $phone
     * @param array|int $verifyStatus 审核状态
     * @param int $status 
     * @return array
     */
    public function getApplyByPhone($acId, $phone, $verifyStatus = null, $status = null)
    {
        $where = [
            'activity_id'  => $acId,
            'phone'  => $phone, 
        ];
        if ($verifyStatus) {
            $where['verify_status'] = $verifyStatus;
        }
        if ($status) {
            $where['status'] = $status;
        }
        return $this->getRow($where);
    }
    
//     /**
//      * 判断某个feedid是否已经报名指定活动
//      * @param int $ac_id
//      * @param string $feed_id
//      * @return array
//      */
//     public function getApplyByFeed($acId, $feedId)
//     {
//         $where = "`ac_id` = {$acId} and `feed_id` = '{$feedId}'";
//         return $this->getRow($where);
//     }
    
    /**
     * 判断某个uid最新的一个报名记录
     * 使用场景：
     * 1、活动详情页面，根据报名信息判断 报名情况，签到情况
     * 2、报名：根据最后一条报名信息的状态，判断提示的内容
     * 3、取消报名：获取自己的最后一条报名信息，并删除
     * 4、签到：判断最后一条报名信息是否是报名审核成功状态。
     * @param int $ac_id
     * @param int $uid
     * @return array
     */
    public function getApplyByUid($acId, $uid)
    {
        return $this->getRow(['activity_id'=>intval($acId), 'user_id'=>intval($uid)], [], ' `activity_id` DESC ');
    }
//     /**
//      * 判断某个uid是否已经报名成功
//      * @param int $ac_id
//      * @param int $uid
//      * @return array
//      */
//     public function applySucc($acId, $uid)
//     {
//         $applyInfo = $this->getApplyByUid($acId, $uid);
//         if (!$applyInfo || !$applyInfo['status']) {
//             return false;
//         }
//         return true;
//     }
    
    /**
     * 判断某个uid是否已经报名签到
     * 使用场景：
     * 1、活动详情页面有判断是否签到成功
     * @param int $ac_id
     * @param int $uid
     * @return array
     */
    public function checkinSucc($acId, $uid)
    {
        $applyInfo = $this->getApplyByUid($acId, $uid);
        if (!$applyInfo || !$applyInfo['status'] || !$applyInfo['checkin_status']) {
            return false;
        }
        return true;
    }
    
    /**
     * 判断某个报名id是否已经报名指定活动
     * 使用场景：
     * 1.审核一个报名信息的时候，获取报名记录的信息
     * @param int $id
     * @return array
     */
    public function getApplyById($id)
    {
        return $this->getRow(['id'=>intval($id)]);
    }
    
    /**
     * 报名
     * 使用场景：
     * 1.报名：保存报名信息
     * @param array $rowArr
     * @return int 插入条数
     */
    public function addApply($rowArr)
    {
        if (empty($rowArr)) {
            return false;
        }
        // @modify by liww 初步方案：因为每个uid只能报一次同一个活动，uid与ac_id设为唯一主键，特此使用replace，之前被拒绝过的话，再次报名就会变为新的内容。
        // @modify by liww 因为旧数据中有好多重复uid，不能设为唯一主键，只能先取出来再判断了
        $oldApply = $this->getApplyByUid($rowArr['activity_id'], $rowArr['user_id']);
        if (!empty($oldApply)) {
            return $this->update($rowArr, ['id'=>$oldApply['id']]);
        } else {
            return $this->addGetInsertId($rowArr, true); 
        }
    }
    
    /**
     * 同意拒绝审核记录
     * 使用场景：
     * 1.审核报名信息
     * @param int $apply_id
     * @param int $verify_status
     * @return int
     */
    public function verifyApply($applyId, $verifyStatus)
    {
        $applyId      = intval($applyId);
        $verifyStatus = intval($verifyStatus);
        if (!$applyId || !$verifyStatus) {
            return false;
        }
        return $this->update(array('verify_status'=>$verifyStatus), ['id'=>$applyId]);
    }
    
    /**
     * 取消报名
     * 使用场景：
     * 1.取消报名
     * @param int $apply_id
     * @param int $verify_status
     * @return int
     */
    public function revokeApply($applyId)
    {
        $applyId = intval($applyId);
        if (!$applyId) {
            return false;
        }
//         return $this->delete(['id'=>$applyId]);
        return $this->update(['status'=>0], ['id'=>$applyId]);
    }
    
    /**
     * 更新status状态
     * @param int $applyId
     * @param int $status
     * @return int
     */
    public function changeStatus($applyId, $status)
    {
        $applyId = intval($applyId);
        if (!$applyId) {
            return false;
        }
        return $this->update(['status'=>$status], ['id'=>$applyId]);
    }
    
    /**
     * 获取报名人员列表
     * 使用场景：
     * 1.活动详情页面：展示几个报名人员
     * 2.活动详情页面：展示几个签到人员
     * 3.报名人员列表页面：待审核，已通过，未通过
     * 4.签到人员列表：已签到，未签到，已通过
     * @param string $where 条件，例如：id > 1 AND verify_status=1
     * @param string $order 排序：例如： `create_time` DESC 
     * @param int $limit 获取的数量
     * @return array 
     */
    public function getApplyList($where, $order, $limit)
    {
        return  $this->getRows($where, [], $order, $limit);
    }
    
    
    /**
     * 将某条申请记录，设置为签到状态
     * 使用场景：
     * 1.签到
     * @param int $apply_id
     * @return int
     */
    public function checkin($applyId)
    {
        $applyId = intval($applyId);
        if (!$applyId) {
            return false;
        }
        return $this->update(array('checkin_status'=>1, 'checkin_time'=>time()), ['id'=>$applyId]);
    }
    
    /**
     * 审核未通过，添加到ac_reject_apply表（暂时废弃，因为拒绝后仍可以报名，不用加入这个表了）
     * @param array $rowArr
     * @return int 插入条数
     */
    public function addRejectApply($rowArr)
    {
        $this->_table = "ac_reject_apply";
        return $this->addGetInsertId($rowArr);
    }
    
    public function execSql($sql)
    {
        return $this->getRowsBySql($sql);
    }
}