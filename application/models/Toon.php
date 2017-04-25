<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2016-10-20
 * @Time: 2016-10-20 14:21
 */
class ToonModel extends BaseModel {

    public $_table = 'ac_user';
//    public $_dbString = 'local';
     public $_dbString = 'portal';
    public $_mongoDbstring = 'portal';
    public $_collections = 'zanzan-activity';
    public $_mongoDbName = 'zanzan-activity';
    public $_redisString   = 'main';
    public $_mcPrefix = 'ZANZAN::Activity::AcUser::';
    
    public $_errmsg = '';


    /**
     * 缓存
     * @param array $info feed_id代表名片ID
     * @return bool|mixed|string
     */
    public function getByFeedInfo(array $info) {

        $feedID = $info['feed_id'];
        $newListArray = $dataArray = $mixDataRow = array();
        //根据feed_i从Redis获取feed相关信息
        $feed_info = $this->getMcRow($this->_mcPrefix.$feedID);
        //根据feed_id获取feed信息
        $list = Toon::getListFeedInfo([$feedID],'portal',$this->_errmsg);

        //获取用户信息
        $userFeedInfo = $this->getRow("feed_id='{$feedID}'");

        if (empty($feed_info) || $feed_info == '[]') {

            if (empty($userFeedInfo)) {
                if ($list) {
                    $mixDataRow = $this->_mixDataArray($list);
                    $dataArray = [
                        'feed_id' => $newListArray['feed_id'],
                        'toon_uid' => $newListArray['user_id'],
                        'create_time' => time(),
                    ];

                    $resultStatus = $this->add($dataArray,true);
                    unset($dataArray);
                    if ($resultStatus) {
                        $this->setMcRow($this->_mcPrefix.$feedID,$mixDataRow,3600*24);
                        $result = $this->getMcRow($this->_mcPrefix.$feedID);
                        if (is_array($result)) {
                            return $result;
                        } else {
                            return json_decode($result,true);
                        }

                    }
                }

            } else {
                $dataArray = [
                    'update_time' => time()
                ];

                $this->update($dataArray,"id='{$userFeedInfo['id']}'");
                $mixDataRow = $this->_mixDataArray($list);

                $this->setMcRow($this->_mcPrefix.$feedID,$mixDataRow,3600*24);
                $result = $this->getMcRow($this->_mcPrefix.$feedID);
                if (is_array($result)) {
                    return $result;
                } else {
                    return json_decode($result,true);
                }
            }
        } else {
            if (empty($userFeedInfo)) {
                if ($list) {
                    $mixDataRow = $this->_mixDataArray($list);
                    $dataArray = [
                        'feed_id' => $newListArray['feed_id'],
                        'toon_uid' => $newListArray['user_id'],
                        'create_time' => time(),
                    ];

                    $resultStatus = $this->add($dataArray,true);
                    if ($resultStatus) {
                        $this->setMcRow($this->_mcPrefix.$feedID,$mixDataRow,3600*24);
                        $result = $this->getMcRow($this->_mcPrefix.$feedID);
                        if (is_array($result)) {
                            return $result;
                        } else {
                            return json_decode($result,true);
                        }
                    }
                }
            }
            $dataArray = [
                'update_time' => time()
            ];
            $this->update($dataArray,"feed_id='{$feedID}'");
            return json_decode($this->getMcRow($this->_mcPrefix.$feedID),true);
        }
    }
    
    /**
     * 根据feed_id从缓存中查询相关数据
     * @param $feed_id
     * @param null $markType 当不为空时,则更新update_time字段值
     * @return array|bool|mixed
     */
    public function getFeedInfoByRedis($feed_id,$markType=NULL) {
        
        empty($feed_id) && Fn::outputToJson(self::ERR_PARAM,'缺少必要参数');
        $feed_info = $this->getMcRow($this->_mcPrefix.$feed_id);
        if ($feed_info) {
            if (1 == $markType) {
                $dataRow = [
                    'update_time' => time()
                ];
                $this->update($dataRow, ['feed_id'=>$feed_id]);
            }

            if (is_array($feed_info)) {
                return $feed_info;
            } else {
                return json_decode($feed_info,true);
            }

        } else {
            return $this->getFeedInfoByDataBase($feed_id,$markType);
        }
    }
    
    /**
     * 根据Feed_id从数据库查询相关信息
     * @param $feed_id
     * @param $markType 当不为空时,则更新update_time字段值
     * @return array|bool|mixed
     */
    public function getFeedInfoByDataBase($feed_id,$markType=NULL) {
        empty($feed_id) && Fn::outputToJson(self::ERR_PARAM,'缺少必要参数');
        $userFeedInfo = $this->getRow("feed_id='{$feed_id}'");
        //根据Feed_id调用Toon开放接口获取Feed信息
        $list = Toon::getListFeedInfo([$feed_id],'portal',$this->_errmsg);
        
        if (empty($list)) {
            return array();
        }
        if ($list) {
            $mixListArray = $this->_mixDataArray($list);
            if ($userFeedInfo) {
                if (1 == $markType) {
                    $dataRow = [
                        'update_time' => time()
                    ];
                    $this->update($dataRow,"id='{$userFeedInfo['id']}'");
                }

                $this->setMcRow($this->_mcPrefix.$feed_id,$mixListArray,3600*24);
                $result = $this->getMcRow($this->_mcPrefix.$feed_id);

                if (is_array($result)) {
                    return $result;
                } else {
                    return json_decode($result,true);
                }

            } else {
                $dataArray = [
                    'feed_id' => $mixListArray['feed_id'],
                    'toon_uid' => $mixListArray['user_id'],
                    'create_time' => time()
                ];
                $resultStatus = $this->add($dataArray,true);
                if (!$resultStatus) {
                    return [];
                }
                $this->setMcRow($this->_mcPrefix.$feed_id,$mixListArray,3600*24);
                $result = $this->getMcRow($this->_mcPrefix.$feed_id);

                if (is_array($result)) {
                    return $result;
                } else {
                    return json_decode($result,true);
                }
            }
        }

    }


    /**
     * 场景话组合数据
     * @param array $id
     * @param $time
     * @param $type
     * @return string
     */
    public function queryMixData(array $id,$time,$limit) {
        $data = [];
        $limit = " LIMIT ".$limit;
        if (!empty($id)) {
            $idStr = implode(',',$id);
            $sql = "SELECT * FROM ac_activity WHERE activity_id IN ({$idStr}) AND publicity = 1 AND status = 1 AND start_time > {$time} ORDER BY start_time ASC ". $limit;
            $rows = $this->getRowsBySql($sql);
            if ($rows) {
                foreach ($rows as &$val) {
                    //区分群组或者个人
                    if (1 == $val['isgroup']) {
                        $feed_id = $val['fid'];
                    } else {
                        $feed_id = $val['c_fid'];
                    }
                    //根据Feed_id
                    $userSql = "SELECT * FROM ac_user WHERE feed_id = '".$feed_id."'";
                    $userResult = $this->getRowsBySql($userSql);
                    if( !empty($userResult) ){
                        $feedrow = array();

                        foreach ($userResult as $v) {
                            $feedrow['feed_id'] = $v['feed_id'];
                            $feedrow['toon_uid'] = $v['toon_uid'];
                            $feedrow['id'] = $v['id'];
                        }

                        $feedrow['feed_id'] = $feedrow['feed_id'];
                        $feedrow['user_id'] = $feedrow['toon_uid'];
                    }
                    
                    $userinfo = $this->getFeedInfoByRedis($feed_id);
                    empty($userinfo) && Fn::outputToJson(self::ERR_PARAM,'用户信息获取失败');
                    $img = json_decode($val['img'],true);

//                    if( 1 == $type ) {
//                        $data = [
//                            "img" => $img['url'],
//                            "feed_id" => $feed_id,
//                            "avatar" => $userinfo['avatarId'],
//                            "title" => $val['title'],
//                            "id" => $val['id'],
//                            "place" => $val['locate'],
//                            "stm" => Fn::dateformat( $val['start_time'] ),
//                            "etm" => $val['end_time']  ? Fn::dateformat( $val['end_time'] ) : '',
//                            'url' => Fn::getServerUrl().'/html/src/index.html?appId=316&entry=101&ac_id='.$val['id'],
//                            'price' => floatval($val['price'])
//
//                        ];
//                        break;
//                    }
//                    else {
//                        $data[] = [
//                            "img" => $img['url'],
//                            "feed_id" => $feed_id,
//                            "avatar" => $userinfo['avatarId'],
//                            "title" => $val['title'],
//                            "id" => $val['id'],
//                            "place" => $val['locate'],
//                            "stm" => Fn::dateformat( $val['start_time'] ),
//                            "etm" => $val['end_time']  ? Fn::dateformat( $val['end_time'] ) : '',
//                            'url' => Fn::getServerUrl().'/html/src/index.html?appId=316&entry=101&ac_id='.$val['id'],
//                            'price' => floatval($val['price'])
//
//                        ];
//                    }
                    $data[] = [
                        "img" => $img['url'],
                        "feed_id" => $feed_id,
                        "avatar" => $userinfo['avatarId'],
                        "title" => $val['title'],
                        "id" => $val['id'],
                        "place" => $val['locate'],
                        "stm" => Fn::dateformat( $val['start_time'] ),
                        "etm" => $val['end_time']  ? Fn::dateformat( $val['end_time'] ) : '',
                        'url' => Fn::getServerUrl().'/html/src/index.html?appId=316&entry=101&ac_id='.$val['id'],
                        'price' => floatval($val['price'])

                    ];
                }
            }
        }

        return json_encode( $data );
    }

    /**
     * @param array $poi
     * @return bool
     */
    public function getNearInfo(array $poi) {

        $params = [
            'geoNear' =>$this->_collections,
            'near'    => [floatval($poi['long']),floatval($poi['lat'])],
            'distanceMultiplier' => 6378137,  //球半径
            'spherical' => true,
            'num'   => 1000,
            'maxDistance' => 1500 / 6378137,  //1500米之内的id
        ];
        
        try {
            $result = MongoData::getInstance($this->_mongoDbstring)->command($params);
            return $result;
        } catch (Exception $e) {
            Fn::writeLog("执行命令错误：" . $e->getMessage() . "\t\n" . var_export($params, true));
            return false;
        }
        
    }


    /**
     * @param array $list
     * @return mixed
     */
    private function _mixDataArray(array $list) {
        foreach ($list as $val) {
            $newListArray = array();
            $newListArray['feed_id'] = $val['feedId'];
            $newListArray['title'] = $val['title'];
            $newListArray['subtitle'] = $val['subtitle'];
            $newListArray['avatarId'] = $val['avatarId'];
            $newListArray['titlePinYin'] = $val['titlePinYin'];
            $newListArray['user_id'] = $val['userId'] ? $val['userId'] : 0;
            $newListArray['birthday'] = $val['birthday'] ? $val['birthday'] : 0;
            $newListArray['tag'] = $val['tag'];
            $newListArray['keyword'] = $val['keyword'] ? $val['keyword'] : '' ;
            $newListArray['u_no'] = $val['cardNo'] ? $val['cardNo'] : 0 ;
        }
        return $newListArray;
    }
    /**
     * 清空所有Redis缓存
     * @return mixed
     */
//    public function cleanCache() {
//        return RedisClient::instance($this->_redisString)->flushall();
//    }
}