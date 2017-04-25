<?php
/**
 * @description 活动动态近往期活动
 * @author by Yaoyuan.
 * @version: 2016-11-24
 * @Time: 2016-11-24 11:24
 */
class DynamicController extends Controller {
    
    /**
     * 近往期接口
     */
    public function getDataRowsAction() {
        $model = new DynamicModel();
        $toon = new ToonModel();

        $getParams = $this->request->getQuery();
        if (empty($getParams) || empty($getParams['session_id']) || empty($getParams['mark'])) {
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }
        $uid = Fn::getUidBySessionId($getParams['session_id']);

        if (empty($uid)) {
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }
        $limit = intval($getParams['limit']);
        $offset = intval($getParams['offset']);
        
        $mixData = $tempRows = array();
        $userState = $ac_status = '';
        $time = time();
        
        $tempTime = mktime(0, 0, 0,date('m'),date('d'),date('Y'));
        //根据当前用户UID获取活动数据
        $list = $model->getActivityRows($uid,$getParams['mark'],$limit,$offset,$tempTime);

        //根据当前用户UID获取报名信息
        $applyInfo = $model->getApplyRows($uid,$limit,$offset,$tempTime,$getParams['mark']);
        
        //合并数组
        $acInfo = array_merge($list,$applyInfo);

        foreach ($acInfo as $key => $val) {
            $tempRows = array();

            if (1 == $val['showMark']) {
                //区分个人/群组
                if (1 == $val['isgroup']) {
                    $fid = $feedID = $val['fid'];
                } else {
                    $feedID = $val['c_fid'];
                }

                $userState = '我是发起者';
                if ($time >= $val['start_time'] && $time <= $val['end_time']) {
                    $ac_status = '报名中';
                }
                if ($time >= $val['start_time']) {
                    $ac_status = '进行中';
                }
                if ($time >= $val['end_time']) {
                    $ac_status = '已结束';
                }
                if ($time < $val['start_time']) {
                    $ac_status = '未开始';
                }

                if ($val['price'] == 0.00 || $val['price'] == 0) {
                    $val['price'] = 0;
                }

            } else if (2 == $val['showMark']) {
                $feedID = $val['feed_id'];
                $userState = '我是参与者';
                if (1 == $val['verify_status'] ) {
                    $ac_status = '报名成功';
                }
                if (0 == $val['verify_status']) {
                    $ac_status = '待审核';
                }
                if (2 ==  $val['verify_status']) {
                    $ac_status = '审核不通过';
                }
            }

            //获取Feedinfo
            $feedInfo = $toon->getFeedInfoByRedis($feedID);
            empty($feedInfo) && Fn::writeLog("getDataRows获取用户信息失败");
            //组合数据
            $tempRows['id'] = $val['id'];
            $tempRows['c_fid'] = $feedID;
            $tempRows['fid'] = $fid;
            $tempRows['title'] = $val['title'];
            $tempRows['img'] = json_decode($val['img'],true);
            $tempRows['start_time'] = $val['start_time'];
            $tempRows['end_time'] = $val['end_time'];
            $tempRows['uuid'] = $val['uuid'];
            $tempRows['ac_status'] = $ac_status;
            $tempRows['userState'] = $userState;
            $tempRows['username'] = isset($feedInfo['title']) ? $feedInfo['title'] :'';
            $tempRows['subtitle'] = isset($feedInfo['subtitle']) ? $feedInfo['subtitle'] : '';
            $tempRows['avatarId'] = isset($feedInfo['avatarId']) ? $feedInfo['avatarId'] : '';
            $tempRows['showMark'] = $val['showMark'];
            $tempRows['price'] = floatval($val['price']);
            $tempRows['isgroup'] = $val['isgroup'];

            $timeRange = $this->_groupTime($time,$val['start_time']);
            if (empty($timeRange)) {
                if (date('Y') == date('Y',$val['start_time'])) {
                    $timeRange = date('m',$val['start_time']);
                } else {
                    $timeRange = date('Y-m',$val['start_time']);
                }
            }
            $tempRows['timeMark'] = $timeRange ;

            $mixData[] = $tempRows;
        }
        //销毁临时变量
        unset($tempRows,$list,$acInfo,$applyInfo);
        //数组排序
        ksort($mixData);
        if (1 == $getParams['mark'] ) {
            $mixData = $this->_multi_array_sort($mixData,'start_time',SORT_ASC);
        } else {
            $mixData = $this->_multi_array_sort($mixData,'start_time',SORT_DESC);
        }

        Fn::outputToJson(0,'ok',$mixData);

    }
    /**
     * 动态搜索接口
     * @param offset--偏移量;limit--条数限制;session_id--获取用户UID;title--活动标题
     */
    public function getSearchListAction() {
        $model = new DynamicModel();
        $toon = new ToonModel();
        $time = mktime(0, 0, 0,date('m'),date('d'),date('Y'));
        $getParams = $this->request->getQuery();

        if (empty($getParams) || empty($getParams['title']) || empty($getParams['session_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'参数错误');
        }

        $offset = intval($this->request->getQuery('offset', 0));
        $limit = intval($this->request->getQuery('limit', 10));
        $title = urldecode(trim($getParams['title']));

        $uid = Fn::getUidBySessionId($getParams['session_id']);

        $acList = $model->getActivityRows($uid,'',$limit,$offset,'',$title);
        $applyList = $model->getApplyRows($uid,$limit,$offset,'','',$title);

        $acInfo = array_merge($acList,$applyList);
        $timeRange = '';
        foreach ($acInfo as $key => $val) {
            $tempRows = array();

            if (1 == $val['showMark']) {
                //区分个人/群组
                if (1 == $val['isgroup']) {
                    $fid = $feedID = $val['fid'];
                } else {
                     $feedID = $val['c_fid'];
                }

                $userState = '我是发起者';
                if ($time >= $val['start_time'] && $time <= $val['end_time']) {
                    $ac_status = '报名中';
                }
                if ($time >= $val['start_time']) {
                    $ac_status = '进行中';
                }
                if ($time >= $val['end_time']) {
                    $ac_status = '已结束';
                }
                if ($time < $val['start_time']) {
                    $ac_status = '未开始';
                }

                if ($val['price'] == 0.00 || $val['price'] == 0) {
                    $val['price'] = 0;
                }

            } else if (2 == $val['showMark']) {
                $feedID = $val['feed_id'];
                $userState = '我是参与者';
                if (1 == $val['verify_status'] ) {
                    $ac_status = '报名成功';
                }
                if (0 == $val['verify_status']) {
                    $ac_status = '待审核';
                }
                if (2 ==  $val['verify_status']) {
                    $ac_status = '审核不通过';
                }
            }

            //获取Feedinfo
            $feedInfo = $toon->getFeedInfoByRedis($feedID);
            empty($feedInfo) && Fn::writeLog('getSearchList获取用户信息失败');
            //组合数据
            $tempRows['id'] = $val['id'];
            $tempRows['c_fid'] = $feedID;
            $tempRows['fid'] = $fid;
            $tempRows['title'] = $val['title'] ? $val['title'] : '';
            $tempRows['img'] = json_decode($val['img'],true);
            $tempRows['start_time'] = $val['start_time'];
            $tempRows['end_time'] = $val['end_time'];
            $tempRows['uuid'] = $val['uuid'];
            $tempRows['ac_status'] = $ac_status;
            $tempRows['userState'] = $userState;
            $tempRows['username'] = isset($feedInfo['title']) ? $feedInfo['title'] : '';
            $tempRows['subtitle'] = isset($feedInfo['subtitle']) ? $feedInfo['subtitle'] : '';
            $tempRows['avatarId'] = isset($feedInfo['avatarId']) ? $feedInfo['avatarId'] : '';
            $tempRows['showMark'] = $val['showMark'];
            $tempRows['price'] = floatval($val['price']);
            $tempRows['isgroup'] = $val['isgroup'];
            
            if (date('Y') == date('Y',$val['start_time'])) {
                $timeRange = date('m',$val['start_time']);
            } else {
                $timeRange = date('Y-m',$val['start_time']);
            }


            $tempRows['timeMark'] = $timeRange ;

            $mixData[] = $tempRows;

        }
        //销毁临时变量
        unset($tempRows,$acList,$acInfo,$applyList);
        //数组排序
        $mixData = $this->_multi_array_sort($mixData,'start_time',SORT_DESC);
        $mixData = empty($mixData) ? [] : $mixData;
        Fn::outputToJson(0,'ok',$mixData);
    }

    /**
     * 时间维度
     * @param $multi_array
     * @param $sort_key
     * @param int $sort
     * @return bool
     */
    private function _multi_array_sort($multi_array, $sort_key, $sort) {
        if (is_array($multi_array)) {
            foreach ($multi_array as $row_array) {
                if (is_array($row_array)) {
                    $key_array[] = $row_array[$sort_key];
                } else {
                    return FALSE;
                }
            }
        } else {
            return FALSE;
        }
        array_multisort($key_array, $sort, $multi_array);
        return $multi_array;
    }
    /**
     * 组合时间范围标识
     * @param $time
     * @param $stm
     * @return int|string
     */
    private function _groupTime($time,$stm) {
        $resultDate = $this->_mixRows($time);

        foreach ($resultDate as $key => $val) {
            if ($stm >= $val['start_time'] && $stm <= $val['end_time']) {

                if ($key) {
                    return $key;
                }
            }
        }

    }
    /**
     * 组织时间数据
     * @param $time
     * @return array
     */
    private function _mixRows($time) {
        $rangeArr = array();

        // 组织本周的时间范围
        $rangeArr['thisweek'] = $thisWeekRange = $this->_getweektimerange($time);

        // 组织下周的时间范围
        $nextWeekStartTime = mktime(0, 0, 0, date('m', $thisWeekRange['end_time']), date('d', $thisWeekRange['end_time']), date('Y', $thisWeekRange['end_time']))+3600*24;
        if (date('d', $nextWeekStartTime) != 1) {
            $rangeArr['nextweek'] = $nextWeekRange = $this->_getweektimerange($nextWeekStartTime);
            // 组织本月除本周和下周的数据，其他的数据时间范围
            $leftWeekStartTime = mktime(0, 0, 0, date('m', $nextWeekRange['end_time']), date('d', $nextWeekRange['end_time']), date('Y', $nextWeekRange['end_time']))+3600*24;
            if (date('d', $leftWeekStartTime) != 1) {
                $leftRange = array('start_time'=>$leftWeekStartTime, 'end_time'=>mktime(23, 59, 59, date('m', $leftWeekStartTime), date('t', $leftWeekStartTime), date('Y', $leftWeekStartTime)));

//                $rangeArr['leftmonth-'.date('m')] = $leftRange;
                $rangeArr[date('m')] = $leftRange;
            }
        }


        // 按周组织完毕，把本周的转化成天
        $weekLeft = 7-date('N', $time);
        $monLeft = date('t', $time) - date('d', $time);
        if ($monLeft < $weekLeft) {
            $weekLeft = $monLeft;
        }

        $todayRange = $rangeArr['today'] = array(
            'start_time'=>mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time)),
            'end_time'=>mktime(23, 59, 59, date('m', $time), date('d', $time), date('Y', $time)));

        if ($weekLeft != 0) {
            for ($i = 1; $i <= $weekLeft; $i++) {
                if ($i == 1) {
                    $key = 'tomorrow';
                } elseif ($i == 2) {
                    $key = 'houtian';
                } else {
                    $tmpWeek = (date('N', $time)+$i);
                    $key = 'week'.$tmpWeek;
                }

                $rangeArr[$key] = $this->_getthisweektime($time, $i);
            }
        }

        unset($rangeArr['thisweek']);

        return $rangeArr;
    }


    /**
     * 计算天数
     * @param $time
     * @param int $num
     * @return array
     */
    private function _getthisweektime($time, $num=0) {
        $finaltime = $time + 3600*24*$num;
        return array(
            'start_time'=>mktime(0, 0, 0, date('m', $finaltime), date('d', $finaltime), date('Y', $finaltime)),
            'end_time'=>mktime(23, 59, 59, date('m', $finaltime), date('d', $finaltime), date('Y', $finaltime)));
    }

    /**
     * 获取周范围
     * @param $time
     * @return mixed
     */
    private function _getweektimerange ($time) {
        $weekLeft = 7-date('N', $time);
        // 获取今天几号，距离月底还剩几天
        $monLeft = date('t', $time) - date('d', $time);

        if ($monLeft < $weekLeft) {
            // 本周就是月底,本周的时间段是今天0点到月底的24点
            $thisWeekRange['start_time'] = mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time));
            $thisWeekRange['end_time'] = mktime(23, 59, 59, date('m', $time), date('d', $time)+$monLeft, date('Y', $time));
            // 下周不用组织，直接走下个月
        } else {
            // 本周不是月底,本周的时间段是今天0点到周日的24点
            $thisWeekRange['start_time'] = mktime(0, 0, 0, date('m', $time), date('d', $time), date('Y', $time));
            $thisWeekRange['end_time'] = mktime(23, 59, 59, date('m', $time), date('d', $time)+$weekLeft, date('Y', $time));
        }

        return $thisWeekRange;
    }

}