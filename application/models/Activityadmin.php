<?php
/**
 * @description 后台用的活动model
 * @author liweiwei
 * @version 2016-11-1上午11:55:26
 */

class ActivityadminModel extends BaseModel
{
    public $_table = "ac_activity";
    public $_dbString = "portal";
//     public $_dbString = "local";
    
    /**
     * 修改活动的排序
     * @param int $acId
     * @param int $newOrder
     * @return int 影响的条数
     */
    public function changeOrder($acId, $newOrder)
    {
        return $this->update(array('ord'=>$newOrder), "id = {$acId}");
    }
    
    /**
     * 删除活动，伪删除，更新status字段
     * @param int $acId
     * @return int
     */
    public function deleteOne($acId)
    {
        return $this->update(array('status'=>0), "id = {$acId}");
    }
    
    /**
     * 上下墙、已读 状态设置时
     * @param int $acId
     * @param int $flag
     * @return Ambigous <boolean, unknown, unknown>
     */
    public function changeFlag($acId, $flag)
    {
        return $this->update(array('flag'=>$flag), "activity_id = {$acId}");
    }
    
    /**
     * 获取符合条件的活动数量
     * @param array $info 条件数组
     */
    public function getNum(array $filter)
    {
        $whereSql = $this->getWhereStr($filter);
        if (!isset($filter['operator']) || empty($filter['operator'])) {
            // 直接从ac_info表统计即可
            $sql = "SELECT COUNT(1) AS total FROM `".$this->_table."` aci ";
        }  else {
            // 需要从ac_info表和ac_op_record表连表查询
            $sql = "SELECT COUNT(1) AS total FROM `".$this->_table."` aci
            LEFT JOIN `ac_op_record` acop ON (aci.id = acop.ac_id)";
        }
        
        $result = $this->getRowsBySql($sql." WHERE 1 ". $whereSql);
        return $result[0]['total'];
    }
    
    /**
     * 获取列表
     * @param array $filter
     * @param string $order
     * @param string $limit
     * @return Ambigous <multitype:, unknown>
     */
    public function getList($filter, $order, $limit)
    {
        $whereSql = $this->getWhereStr($filter);
        $sql  = "SELECT aci.*, acop.`staff` FROM `".$this->_table."` aci ";
        $sql .= "LEFT JOIN `ac_op_record` acop ON (aci.id = acop.ac_id)";
        $sql .= " WHERE 1 ". $whereSql.' '.$order.' '.$limit;
        
        return $this->getRowsBySql($sql);
    }
    
    /**
     * 根据条件组织条件sql
     * @param array $filter
     * @return string
     */
    private function getWhereStr($filter)
    {
        $whereSql = '';
        if (isset($filter['tab'])) {
            switch ($filter['tab']) {
                case 1://审核通过
                    $whereSql .= " AND (aci.flag & 1) AND aci.`flag` != -1  ";
                    break;
                case 2://不通过
                    $whereSql .= " AND !(aci.flag & 1) AND !(aci.flag & 2) AND !(aci.flag & 4)";
                    break;
//                case 3://不处理
//                    $whereSql .= " AND (aci.flag & 2)  AND aci.`flag` != -1  ";
//                    break;
                default://待审核
                    //$whereSql .= " AND (aci.flag = -1) || (aci.flag & 4)";
                    $whereSql .= " AND (aci.flag = -1) AND  ((aci.flag & 4) OR !(aci.flag & 1)) ";
                    break;
            }
        } else if(isset($filter['flag'])) {
            $whereSql .= " AND aci.`flag` & '{$filter['flag']}'";
        }
        if (isset($filter['get_recommend'])) {
            if ($filter['get_recommend'] == 1) {
                $whereSql .= " AND aci.`flag` & 1 AND aci.`flag` & 4 AND aci.`flag` != -1";
            } else {
                $whereSql .= " AND aci.`flag` & 1 AND aci.`flag` != -1";
            }
        }
        if (isset($filter['title']) && !empty($filter['title'])) {
            $whereSql .= " AND aci.`title` LIKE '%{$filter['title']}%' ";
        }
        if (isset($filter['u_no']) && !empty($filter['u_no'])) {
            $whereSql .= " AND aci.`u_no` = '{$filter['u_no']}'";
        }
        if (isset($filter['operator']) && !empty($filter['operator'])) {
            $whereSql .= " AND acop.`staff` = '{$filter['operator']}'";
        }
        if (isset($filter['nickname']) && !empty($filter['nickname'])) {
            $whereSql .= " AND aci.nickname LIKE '%{$filter['nickname']}%'";
        }
        // 报名截止时间段搜索
        if (isset($filter['apply_end_time_s']) && !empty($filter['apply_end_time_s'])) {
            $whereSql .= " AND aci.`apply_end_time` >= '{$filter['apply_end_time_s']}'";
        }
        if (isset($filter['apply_end_time_e']) && !empty($filter['apply_end_time_e'])) {
            $whereSql .= " AND aci.`apply_end_time` <= '{$filter['apply_end_time_e']}'";
        }
        // 开始时间段搜索
        if (isset($filter['ac_start_time_s']) && !empty($filter['ac_start_time_s'])) {
            $whereSql .= " AND aci.`start_time` >= '{$filter['ac_start_time_s']}'";
        }
        if (isset($filter['ac_start_time_e']) && !empty($filter['ac_start_time_e'])) {
            $whereSql .= " AND aci.`start_time` <= '{$filter['ac_start_time_e']}'";
        }
        // 发布时间段搜索
        if (isset($filter['ac_create_time_s']) && !empty($filter['ac_create_time_s'])) {
            $whereSql .= " AND aci.`create_time` >= '{$filter['ac_create_time_s']}'";
        }
        if (isset($filter['ac_create_time_e']) && !empty($filter['ac_create_time_e'])) {
            $whereSql .= " AND aci.`create_time` <= '{$filter['ac_create_time_e']}'";
        }
        if (isset($filter['ac_end_time_s']) && !empty($filter['ac_end_time_s'])) {
            $whereSql .= " AND aci.`end_time` >= '{$filter['ac_end_time_s']}'";
        }
        if (isset($filter['publicity']) && !empty($filter['publicity'])) {
            $whereSql .= " AND aci.`publicity` = '{$filter['publicity']}'";
        }
        $whereSql .= " AND aci.`status`=1 ";
        
        return $whereSql;
    }
    
    /**
     * 添加操作记录
     * @param int $acId 活动id
     * @param string $staff 操作人姓名
     * @param int $state flag值
     * @param string note 操作备注： 删除 、上墙 、 下墙、 更新顺序、已读、 定时上墙、定时下墙
     */
    public function addOpRecord($acId, $staff, $note)
    {
        $note = '  '.$staff."-".$note;
        $updateTime = $createTime = time();
        
        $sql = "INSERT INTO `ac_op_record` (`ac_id`, `staff`, `update_time`, `create_time`, `note`) ";
        $sql .= "VALUES ($acId, '$staff', $updateTime, $createTime, '$note')";
        $sql .=" ON DUPLICATE KEY UPDATE `staff`='$staff', `update_time`=$updateTime, `note`= concat(note, '$note') ";
        return $this->add($sql);
    }
    
    /**
     * 后台审核术语
     * @param $id
     * @return array
     */
    public function getContentList($limit,$id) {
        $where = '';
        if ($id) {
            $where = " WHERE id = {$id}";
        }
        $order = " ORDER BY id DESC,create_time DESC ";
        $sql = "SELECT id,content,create_time,update_time FROM ac_check_content ".$where. $order . $limit;
        
        return $this->getRowsBySql($sql);
    }
    
    /**
     * 获取回复术语总条数
     * @return mixed
     */
    public function getContentNum () {
        $sql = "SELECT COUNT(id) AS total FROM ac_check_content WHERE 1=1";
        $result = $this->getRowsBySql($sql);
        return $result[0]['total'];
    }
    
    /**
     * 修改审核回复术语
     * @param $id
     * @param $mark
     * @param $content
     * @return bool|unknown
     */
    public function editContent($id,$mark,$content) {
        if (2 == $mark) {
            $where = " id = {$id}";
            return $this->delete($where,'ac_check_content');
        } else if (1 == $mark) {
            $row = [
                'content' => $content,
                'update_time' => time()
            ];
            $where = "id={$id}";
            return $this->update($row,$where,'ac_check_content');
        }
    }
    /**
     * 新增审核术语
     * @param $post
     * @return int
     */
    public function addCheckContent($post) {
        $this->_table = 'ac_check_content';
        $dataRow = [
            'content' => $post['content'],
            'create_time' => time()
        ];
        return $this->add($dataRow);
    }
    
    /**
     * @param $id
     * @param $cid
     * @return array
     */
    public function getCheckContentInfo($id,$cid) {
        $where = "";
        if ($cid) {
            $where .= " AND cid = {$cid}";
        }
        $where = " id = {$id} ". $where;
        return $this->getRow($where,false,false,'ac_content_ext');
        
    }
    /**
     * 审核结果
     * @param $id
     * @param $mark
     * @param $cid
     */
    public function checkStatus($id,$mark,$cid,$flag) {
        //可操作状态 1-审核是否通过 2-是否处理 4-是否推荐
        $whereSql = $str = "";
        if ($flag == -1) {
            $tmpFlag = 0;
        } else {
            $tmpFlag = $flag;
        }
        
        switch($mark) {
            case 1:
                $str = "通过操作";
                if (!($tmpFlag & 1)) {
                    $flag = $tmpFlag | 1;
                }
                $this->checkNotice($id,1);
                break;
            case 2:
                if ($tmpFlag & 1) {
                    $flag = $tmpFlag ^ 1;
                } else {
                    $flag = $tmpFlag;
                }
                $str = "不通过操作";
                $this->checkNotice($id,2,$cid);
                break;
            case 4:
                $str = "删除操作";
                $this->deleteOne($id);
                $this->checkNotice($id,4);
                break;
//            case 3:
//                if (!($tmpFlag & 2)) {
//
//                    $flag = $tmpFlag | 2;
//                }
//                $str = "不处理操作";
//                $this->checkNotice($id,3);
//                break;
            default:
                $flag = -1;
                break;
        }
        
        if (2 == $mark) {
            $cid = intval($cid);
        } else {
            $cid = 0;
        }
        
        $dataRow = [
            'ac_id' => $id,
            'cid' => $cid
        ];
        $insetResult = $this->add($dataRow,false,false,'ac_content_ext');
        $whereSql = " flag = {$flag}";
        if (4 != $mark) {
            $sql = "UPDATE ".$this->_table." SET ". $whereSql . " WHERE id= {$id}";
            
            $result = $this->update($sql);
            if ($result && $insetResult) {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
        
    }
    //审核通知
    public function checkNotice($acid,$mark,$cid=NULL) {
        $content = $contentArr = "";
        $model = new ActivityModel();
        $notice = new NoticeModel();
        $acInfo = $model->getActivityInfo($acid);
        if (is_numeric($cid)) {
            $noticeInfo = $this->getContentList('',$cid);
            foreach ($noticeInfo as $val) {
                $contentArr = $val['content'];
            }
        }
        
        switch($mark) {
            case 1:
                $content = "你发布的公开活动【".$acInfo['title']."】已通过审核，将在活动分类列表中被更多人看到啦";
                break;
            case 2:
                $content = "你发布的公开活动【".$acInfo['title']."】因为【".$contentArr."】，未通过审核，未能在分类列表展示，请编辑修改后再发布";
                break;
            case 4:
                $content = "你发布的活动【".$acInfo['title']."】因内容违规，已被删除。";
                break;
            default:
                break;
        }
        if (1 == $acInfo['isgroup']) {
            $feed_id = $acInfo['fid'];
            $feedType = "g";
        } else {
            $feed_id = $acInfo['c_fid'];
            $feedType = "c";
        }
        $codeData = [
            'visitor' => array(
                'feed_id' => $feed_id,
                'uid' => $acInfo['uid']
            ),
            'owner' => array(
                'feed_id' => $feed_id
            )
        ];
        $contentArr = [
            'url' => Fn::generatePageUrl(3,$acInfo['id'],$codeData,$feedType),
            'msg' => $content
        ];
        $noticeInfo = [
            'fromFeedId' => $feed_id,
            'toFeedId' => $feed_id,
            'toUid' => $acInfo['uid'],
            'contentArr' => $contentArr
        ];
        
        $notice->addToList($noticeInfo);
    }
}