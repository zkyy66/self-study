<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2016-11-24
 * @Time: 2016-11-24 11:26
 */
class DynamicModel extends BaseModel {

    public $_table = 'ac_activity';
    public $_dbString = 'portal';

    /**
     * 根据UID获取活动信息
     * 获取所有活动包含近期/往期活动
     * @param $uid
     * @param $mark
     * @param $limit
     * @param $offset
     * @param $time
     * @return array
     */
    public function getActivityRows($uid,$mark,$limit,$offset,$time,$title) {
        $uid = intval($uid);
        $whereSql = " WHERE uid = '{$uid}' AND status = 1 ";
        $order = '';
        $limit = " LIMIT 10";

        if ($offset > 0) {
            $whereSql .= " AND id < {$offset} ";
        }
        if ($mark && $time) {
            if (1 == $mark) {
                $whereSql .= " AND start_time >= {$time}";
                $order = " ORDER BY start_time ASC";
            } else {
                $whereSql .= " AND start_time < {$time}" ;
                $order = " ORDER BY start_time DESC";
            }
        }
        if ($title) {
            $title = Fn::filterString($title);
            $whereSql .= " AND title LIKE '%{$title}%' ";
            $order = " ORDER BY start_time ASC";
        }

        $sql = "SELECT id,title,img,start_time,end_time,isgroup,c_fid,fid,uid,price,uuid,locate ,1 AS showMark FROM ".$this->_table . $whereSql. $order .$limit;

        return $this->getRowsBySql($sql);

    }

    /**
     * 根据UID获取活动报名信息
     * @param $uid
     * @param $limit
     * @param $offset
     * @param $time
     * @param $mark
     * @return array
     */
    public function getApplyRows($uid,$limit,$offset,$time,$mark,$title) {
        $where = " WHERE aci.status = 1 AND aly.verify_status != 2 AND aly.uid = '{$uid}'";
        $limit = " LIMIT 10";

        if ($offset > 0) {
            $where .= " AND aci.id < {$offset} ";
        }
        if ($mark && $time) {
            if (1 == $mark) {
                $where .= " AND aci.start_time >= {$time}";
                $order = " ORDER BY aci.start_time ASC";
            } else {
                $where .= " AND aci.start_time < {$time}" ;
                $order = " ORDER BY aci.start_time DESC";
            }
        }

        // @FIXME 过滤使用Fn里面的，不是必填参数，给个默认值，防止调用时，参数不够报错
        if ($title) {
            $title = mysql_escape_string($title);
            $where .= " AND aci.title LIKE '%{$title}%' ";
            $order = " ORDER BY aci.start_time ASC  ";
        }

        $sql = "SELECT aly.ac_id AS id, aci.img AS img,aci.title,aci.start_time,aci.end_time,aci.uuid,aly.uid, aly.feed_id,2 AS showMark,aci.price,aci.isgroup,aci.fid,aci.locate,aly.verify_status,aly.checkin_status FROM ac_apply aly
                  LEFT JOIN ac_activity aci ON (aci.id = aly.ac_id)
                 ". $where . $order . $limit ;

        return $this->getRowsBySql($sql);
    }

}