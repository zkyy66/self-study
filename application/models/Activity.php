<?php

/**
 * @description
 * @author by Yaoyuan.
 * @version: 2016-10-20
 * @Time: 2016-10-20 15:50
 */
class ActivityModel extends BaseModel
{
    
    public $_table = 'ac_activity';
    public $_dbString = 'portal';
    //MobngoDB
    public $_mongoDbstring = 'portal';
    public $_collections = 'zanzan-activity';
    public $_mongoDbName = 'zanzan-activity';
    public $_redisString = 'main';
    public $_mcPrefix = 'ZANZAN::activity::IndexPage::';
    public $_mcCategoryList = 'ZANZAN::activity::CategoryList::';
    
    // 推荐，热门，最新的缓存，3分钟
    public $_indexMcTime = 180;
    
    
    /**
     * 删除首页推荐，热门，最新，及其列表的缓存
     */
    public function removeAllMc()
    {
        // 删除首页推荐列表的缓存
        $listKeys = RedisClient::instance('main')->keys('*activity::IndexPage*');
        if (!empty($listKeys)) {
            $ret = RedisClient::instance('main')->del($listKeys);
        }
    }
    
    
    /**
     * 删除首页推荐及其列表的缓存
     */
    public function removeRecommendMc()
    {
        // 删除首页推荐列表的缓存
        $listKeys = RedisClient::instance('main')->keys('*activity::IndexPage::panellist*');
        if (!empty($listKeys)) {
            $ret = RedisClient::instance('main')->del($listKeys);
        }
    }
    
    /**
     * 删除首页热门及其列表的缓存
     */
    public function removeHotMc()
    {
        // 删除首页推荐列表的缓存
        $listKeys = RedisClient::instance('main')->keys('*activity::IndexPage::hotlist*');
        if (!empty($listKeys)) {
            $ret = RedisClient::instance('main')->del($listKeys);
        }
    }
    
    /**
     * 删除分类列表缓存
     */
    public function removeCategoryMc()
    {
        $listKey = RedisClient::instance('main')->keys('*activity::CategoryList*');
        if (!empty($listKey)) {
            try {
                RedisClient::instance('main')->del($listKey);
            } catch (Exception $e) {
                Fn::writeLog("列表缓存状态结果：" . $e->getMessage());
            }
        }
    }
    
    /**
     * 获取活动分类列表
     * @return array
     */
    public function getList($offset, $limits, $type = NULL, $poi = NULL, $school_id = NULL, $is_admin = NULL, $is_mark = NULL)
    {
        $time = time();
        
        $order = $whereSql = $limit = '';
        //分类列表缓存
//        $mcKey = $this->_mcCategoryList."Category::$type::$offset::$limits";
//        $listFromMc = RedisClient::instance($this->_redisString)->get($mcKey);
//        if (!empty($listFromMc)) {
//            return unserialize($listFromMc);
//        }
        
        $whereSql = " WHERE aci.status = 1 AND aci.flag & 1 AND aci.flag != -1 ";
        
        if ('index' == $poi) {
            $whereSql .= " AND {$time} < aci.end_time ";
            $order = " ORDER BY aci.order_no DESC,aci.create_time DESC ";
//            if ($offset > 0) {
//                $whereSql .= " AND aci.id < '{$offset}' ";
//            }
//            $limit = " LIMIT ". $limits;
        } else {
            if (1 == $is_mark) {
                $order = " ORDER BY aci.start_time ASC ";
            } else {
                $order = " ORDER BY aci.create_time DESC ";
            }
            
        }
        if ($school_id) {
            $whereSql .= " AND aci.school_id = " . $school_id;
        }
        if (isset($type)) {
            $whereSql .= " AND aci.type = " . $type;
        }
        if ($is_admin) {
            $whereSql .= " AND aci.is_admin = " . $is_admin;
        }
        
        $sql = $this->_mixSql() . $whereSql . $order . $limit;
        
        $result = $this->getRowsBySql($sql);
        //设置缓存时效性
        //RedisClient::instance($this->_redisString)->setex($mcKey, $this->_indexMcTime, serialize($result));
        return $result;
    }
    
    /**
     * 获取外校活动列表
     * @param $offset
     * @param $limits
     * @param null $school_id
     * @param null $is_admin
     */
    public function getOtherSchoolList($offset, $limits, $school_id = NULL, $is_admin = NULL)
    {
        $time = time();
        
        $whereSql = " WHERE aci.status = 1 AND aci.flag & 1 AND aci.flag != -1 ";
        if ($school_id) {
            $whereSql .= " AND aci.school_id != " . $school_id;
        }
        if ($is_admin) {
            $whereSql .= " AND aci.is_admin = " . $is_admin;
        }
        $order = " ORDER BY aci.create_time DESC ";
        $sql = $this->_mixSql() . $whereSql . $order;
        $result = $this->getRowsBySql($sql);
        return $result;
    }
    
    /**
     *
     * 增加编辑活动数据
     * @param array $post
     * @return int|mixed
     */
    public function addDataBase(array $post)
    {
        $acstatModel = new AcstatModel();
        Fn::writeLog("addDataBase数据：" . var_export($post, true));
        $uuid = Fn::getUuid();
        
        if ("0" == $post['allow_apply']) {
            
            $dataArray = [
                'title' => $post['title'],
                'img' => $post['img'],
                'type' => $post['type'],
                'start_time' => $post['start_time'],
                'end_time' => $post['end_time'],
                'locate' => $post['locate'],
                'is_allow_apply' => $post['allow_apply'],
                'single_feed_id' => $post['c_fid'],
                'group_feed_id' => $post['fid'],
                'user_id' => $post['uid'],
                'create_time' => $post['create_time'],
                'is_group' => $post['isgroup'],
                'uuid' => $uuid,
                'card_no' => $post['u_no'] ? $post['u_no'] : 0,
                'nickname' => $post['nickname'],
                'flag' => -1,
                'update_time' => $post['create_time'],
                'is_admin' => 2,
                'price' => $post['price'],
                'school_id' => $post['school_id']
            ];
        } else {
            
            $switch_status = 1 | 1;
            if (1 == $post['checktype']) {
                $switch_status = $switch_status | 2;
            }
            
            if (1 == $post['need_checkin']) {
                $switch_status = $switch_status | 4;
            }
            $dataArray = [
                'title' => $post['title'],
                'img' => $post['img'],
                'type' => $post['type'],
                'start_time' => $post['start_time'],
                'end_time' => $post['end_time'],
                'locate' => $post['locate'],
                'is_allow_apply' => $post['allow_apply'],
                'price' => $post['price'],
                'is_need_check' => $post['checktype'],
                'max_people' => $post['max'],
                'apply_end_time' => $post['apply_end_time'],
                'is_need_checkin' => $post['need_checkin'],
                'single_feed_id' => $post['c_fid'],
                'group_feed_id' => $post['fid'],
                'user_id' => $post['uid'],
                'create_time' => $post['create_time'],
                'is_group' => $post['isgroup'],
                'uuid' => $uuid,
                'card_no' => $post['u_no'] ? $post['u_no'] : 0,
                'switch_status' => $switch_status,
                'nickname' => $post['nickname'],
                'flag' => -1,
                'update_time' => $post['create_time'],
                'is_admin' => 2,
                'school_id' => $post['school_id']
            ];
            
        }
        
        $ac_id = $this->addGetInsertId($dataArray);
        
        if (!$ac_id) {
            Fn::writeLog("活动新增失败" . $this->setErrorNo(ERROR_SYS));
            return false;
        }
        
        $extDataArray = [
            'activity_id' => $ac_id,
            'lng' => isset($post['longtitude']) ? $post['longtitude'] : 0,
            'lat' => isset($post['latitude']) ? $post['latitude'] : 0,
            'custom_field' => isset($post['custom_field']) ? json_encode($post['custom_field'], JSON_UNESCAPED_UNICODE) : '',
            'description' => isset($post['description']) ? $post['description'] : '',
            'images' => isset($post['images']) ? $post['images'] : '',
            'phone' => isset($post['tel']) ? $post['tel'] : 0,
//            'group_record' => 0,
            'is_group_record' => 0,
            'address' => $post['address']
        ];
        Fn::writeLog("发布活动扩展表信息:" . json_encode($extDataArray));
        $result = $this->add($extDataArray, false, false, 'ac_ext');
        
        //Mongodb中增加经纬度.如果经纬度均为空则不插入MongoDB
        if ($post['longtitude'] && $post['latitude']) {
            $this->addMongodb($ac_id, $post);
        }
        
        $acstatModel->addOne(['activity_id' => $ac_id]);//统计表中新添一条记录
        //发布者默认心动
        User::heartBeat($post['uid'], 1, $ac_id);
        $acstatModel->incrLoveNum($ac_id);
        /******************动态-Start*****************/
        //$this->_addToTrends($post,$uuid);
        /******************动态-End*****************/
        if ($result) {
            return $ac_id;
        } else {
            return false;
        }
        
    }
    
    /**
     * MongoDB中添加经纬度
     * @param array $info
     * @return string
     */
    public function addMongodb($ac_id, $post)
    {
        $mongodbData = [
            '_id' => $ac_id + 0,
            'poi' => [
                floatval($post['longtitude']),
                floatval($post['latitude'])
            ],
        ];
        try {
            $result = MongoData::getInstance($this->_mongoDbstring)->insert($this->_collections, $mongodbData);
        } catch (Exception $e) {
            Fn::writeLog("插入mongo数据失败:" . $e->getMessage());
            return false;
        }
        
        
        if (!$result) {
            Fn::writeLog("插入mongo数据失败:" . var_export($mongodbData, true), "/logs/mongo_err.log");
            return false;
        }
        
        return 'ok';
    }
    
    /**
     * 更新Mongodb经纬度
     * @param $ac_id
     * @param $post
     * @return bool|string
     */
    public function updateMongodb($ac_id, $post)
    {
        $filter = array(
            '_id' => intval($ac_id)
        );
        $mongodbData = [
            '_id' => intval($ac_id),
            'poi' => [
                floatval($post['longtitude']),
                floatval($post['latitude'])
            ],
        ];
        try {
            $result = MongoData::getInstance($this->_mongoDbstring)->where($filter)->update($this->_collections, $mongodbData, ['upsert' => true]);
        } catch (Exception $e) {
            Fn::writeLog("更新mongo数据失败:" . $e->getMessage());
            return false;
        }
        
        if (!$result) {
            Fn::writeLog("更新mongo数据失败:" . var_export($mongodbData, true), "/logs/mongo_err.log");
            return false;
        }
        return 'ok';
    }
    
    /**
     * 更新活动数据信息
     * @param array $post
     */
    public function updateDataBase(array $post, $loginUseId)
    {
//        Fn::writeLog("活动编辑数据：" . var_export($post, true));
        $time = time();
        $noticeModel = new NoticeModel();
        $feedType = '';//$feedType c-代表个人活动 g-代表群组活动
        //根据活动ID查询获取活动信息
        if (is_numeric($post['ac_id'])) {
            $acInfo = $this->getActivityInfo($post['ac_id']);//根据活动ID查询
            $acInfo && $acExtInfo = $this->getActivityExtInfo($acInfo['activity_id']);
        } else {
            $acInfo = $this->getByAcInfoWithUuid(Fn::filterString($post['ac_id']));//根据活动唯一标识UUID查询
            $acInfo && $acExtInfo = $this->getActivityExtInfo($acInfo['activity_id']);
        }
        if ($acInfo) {
            //合并数组
            $acInfo = array_merge($acInfo, $acExtInfo);
        } else {
            Fn::outputToJson($this->setErrorNo(ERROR_SYS), "该活动不存在");
        }
        
        $applySql = "SELECT * FROM ac_apply WHERE activity_id = '{$acInfo['activity_id']}' AND verify_status != 2 AND status != 0";
        $applyResult = $this->getRowsBySql($applySql);
        
        
        $whereSql = $extWhereSql = $message = $messagePre = '';
        
        //更改操作状态开关
        if (1 == $post['allow_apply']) {
            $switch_status = $post['allow_apply'] | 1;
            if (1 == $post['checktype']) {
                $switch_status = $switch_status | 2;
            }
            if (1 == $post['need_checkin']) {
                $switch_status = $switch_status | 4;
            }
            if ($acInfo['switch_status'] != $switch_status) {
                $whereSql .= " switch_status = {$switch_status} ,";
            }
        } else if (0 == $post['allow_apply']) {
            $whereSql .= " switch_status = 0 ,";
        }
        /******************************************/
        if (1 == $post['isgroup']) {
            if ($acInfo['group_feed_id'] != $post['fid']) {
                $whereSql .= " group_feed_id = '{$post['fid']}' ,";
            }
            $feedType = 'g';
        } else {
            if ($acInfo['single_feed_id'] != $post['c_fid']) {
                $whereSql .= " single_feed_id = '{$post['c_fid']}' ,";
            }
            $feedType = 'c';
        }
        
        if ($acInfo['img'] != str_replace('\\', '', $post['img'])) {
            $whereSql .= " img='{$post['img']}' ,";
        }
        
        if ($acInfo['title'] != $post['title']) {
            $message .= '活动名称、';
            $whereSql .= " title='{$post['title']}' ,";
        }
        
        if ($acInfo['start_time'] != $post['start_time']) {
            $message .= '开始时间、';
            $whereSql .= " start_time='{$post['start_time']}' ,";
        }
        if ($acInfo['end_time'] != $post['end_time']) {
            $message .= '结束时间、';
            $whereSql .= " end_time='{$post['end_time']}' ,";
        }
        
        if ($acInfo['is_allow_apply'] != $post['allow_apply']) {
            $whereSql .= " is_allow_apply='{$post['allow_apply']}' ,";
        }
        
        if ($acInfo['locate'] != $post['locate']) {
            $message .= '活动地点、';
            $whereSql .= " locate='{$post['locate']}' ,";
        }
        
        if ($acInfo['type'] != $post['type']) {
            $whereSql .= " type='{$post['type']}' ,";
        }
        
        if ($post['apply_end_time']) {
            if ($acInfo['apply_end_time'] != $post['apply_end_time']) {
                $message .= '报名截止时间、';
                $whereSql .= " apply_end_time='{$post['apply_end_time']}' ,";
            }
        }
        
        if ($acInfo['max_people'] != $post['max']) {
            $whereSql .= " max_people='{$post['max']}' ,";
        }
        
        if ($acInfo['price'] != $post['price']) {
            $message .= '活动费用、';
            $whereSql .= " price='{$post['price']}', ";
        }
        if ($acInfo['is_need_check'] != $post['checktype']) {
            $whereSql .= " is_need_check='{$post['checktype']}', ";
        }
        
        
        if ($acInfo['is_need_checkin'] != $post['need_checkin']) {
            $whereSql .= " is_need_checkin='{$post['need_checkin']}', ";
        }
        
        /*************************扩展表**************************************/
        if ($acInfo['description'] != $post['description']) {
            $message .= '活动描述、';
            $extWhereSql .= " description='{$post['description']}',";
        }
        
        $acInfo['phone'] = empty($acInfo['phone']) ? 0 : $acInfo['phone'];
        if ($acInfo['phone'] != $post['phone']) {
            $message .= '主办方电话、';
            $extWhereSql .= " phone='{$post['phone']}',";
        }
        
        if ($acInfo['images'] != $post['images']) {
            $extWhereSql .= " images = '{$post['images']}',";
        }
        if ($post['custom_field']) {
            $custom_field = json_encode($post['custom_field'], JSON_UNESCAPED_UNICODE);
            $extWhereSql .= " custom_field = '{$custom_field}',";
        }
        //更新经纬度
        if (($acInfo['lng'] != $post['longtitude'])) {
            $extWhereSql .= " lng = '{$post['longtitude']}' , ";
        }
        if (($acInfo['lat'] != $post['latitude'])) {
            $extWhereSql .= " lat = '{$post['latitude']}',";
        }
        $acInfo['address'] = empty($acInfo['address']) ? '' : $acInfo['address'];
        if ($acInfo['address'] != $post['address']) {
            $extWhereSql .= " address = '{$post['address']}',";
        }
        /*************************扩展表End**************************************/
        
        //字段存在变动的情况下执行以下Sql
        if ($whereSql || $extWhereSql) {
            Fn::writeLog('activity/编辑: activity_id:' . $acInfo['activity_id'] . ' wheresql:' . $whereSql . " extwhere:" . $extWhereSql);
            $whereSql .= " update_time = {$time}, ";
            $whereSql .= " nickname = '{$post['nickname']}', ";
            
            //is_admin:1代表后台发布编辑时flag不变;2为用户发布的活动,编辑时有审核通过变为待审核,已推荐变为取消推荐
            if (2 == $acInfo['is_admin']) {
                /***************后台审核状态*******************/
                if (($acInfo['flag'] & 1) && ($acInfo['flag'] & 4) && ($acInfo['flag'] != -1)) {
                    //如果是审核通过且推荐
                    $flag = $acInfo['flag'] ^ 1;
                    $whereSql .= " flag = " . $flag . ',';
                } else if (($acInfo['flag'] & 1) && ($acInfo['flag'] != -1)) {
                    //如果为审核通过状态编辑模式下，更改为待审核状态
                    $flag = -1;
                    $whereSql .= " flag = " . $flag . ',';
                } else {
                    $whereSql .= " flag = -1 ,";
                }
            } else if (1 == $acInfo['is_admin']) {
                $whereSql .= "flag = " . $acInfo['flag'];//维持原状
            }
            
            //发送通知术语
            $messagePre = '您报名的活动【' . $acInfo['title'] . '】';
            $tailContent = '有变更,请去查看确定是否更改活动计划';
            
            //更新主表Sql语句
            $sql = "UPDATE " . $this->_table . " SET " . trim($whereSql, ',') . " WHERE activity_id= " . $acInfo['activity_id'];
            
            $result = $this->update($sql);
            Fn::writeLog("活动主表更新状态:" . json_encode($result));
            //更新活动扩展表信息
            if ($extWhereSql) {
                $extSql = "UPDATE ac_ext SET " . trim($extWhereSql, ',') . " WHERE activity_id=" . $acInfo['activity_id'];
                $extrResultStatus = $this->update($extSql);
                Fn::writeLog("活动扩展表更新状态:" . json_encode($extrResultStatus));
            }
            
            //更新mongodb--更新经纬度
            $this->updateMongodb($acInfo['activity_id'], $post);
            
            //判断是否发通知
            if ($result === false) {
                return false;
            } else {
                if (1 == $acInfo['is_group']) {
                    $feed_id = $acInfo['group_feed_id'];
                } else {
                    $feed_id = $acInfo['single_feed_id'];
                }
                if ($applyResult) {
                    $userTempArray = array();
                    foreach ($applyResult as $val) {
                        $userTempArray[] = $val['user_id'];
                    }
                    $userIdStr = implode(',', $userTempArray);
                    unset($userTempArray);
                    $userList = User::getUserList($loginUseId, $userIdStr);
                    
                    foreach ($userList as $val) {
                        $codeData = array(
                            'visitor' => array(
                                'feed_id' => $val['feedId'],
                                'uid' => $val['userId']
                            ),
                            'owner' => array(
                                'feed_id' => $feed_id
                            )
                        );
                        $contentArr = [
                            'url' => Fn::generatePageUrl(3, $post['ac_id'], $codeData, $feedType),
                            'msg' => trim($messagePre . $message, '、') . $tailContent
                        ];
                        $noticeInfo = array(
                            'fromFeedId' => $feed_id,
                            'toFeedId' => $val['feedId'],
                            'toUid' => $val['toon_uid'],
                            'contentArr' => $contentArr
                        );
                        if (!empty($message)) {
                            $noticeModel->addToList($noticeInfo);
                        }
                        
                    }
                }
                
                $this->removeAllMc();
                $this->removeCategoryMc();
                return true;
            }
        } else {
            $sql = "UPDATE " . $this->_table . " SET update_time = {$time} WHERE activity_id= " . $acInfo['activity_id'];
            $result = $this->update($sql);
            return $result;
        }
        
    }
    
    /**
     * 查看活动详情
     * @param array $info -- id-活动ID，mark 标识
     * @return array
     */
    public function details($ac_id)
    {
        $typeStatus = $checkType = '';
        //获取活动基本信息
        $acInfo = $acExtInfo = array();
        if (is_numeric($ac_id)) {
            $acInfo = $this->getActivityInfo($ac_id);
            $acInfo && $acExtInfo = $this->getActivityExtInfo($acInfo['activity_id']);
            $acInfo && $contentInfo = $this->_getNoticeInfo($acInfo['uuid']);
        } else {
            $acInfo = $this->getByAcInfoWithUuid(Fn::filterString($ac_id));
            $acInfo && $acExtInfo = $this->getActivityExtInfo($acInfo['activity_id']);
            $acInfo && $contentInfo = $this->_getNoticeInfo(Fn::filterString($ac_id));
        }
        
        $acInfo = array_merge($acInfo, $acExtInfo);
        
        if ($acInfo) {
            if (1 == $acInfo['is_group']) {
                $feed_id = $acInfo['group_feed_id'];
                $feedType = "g";
            } else {
                $feed_id = $acInfo['single_feed_id'];
                $feedType = "c";
            }
            $groupInfo = $this->detailChatInfo($acInfo['activity_id'], '');
            
            if ($groupInfo && $groupInfo['group_id']) {
                $group_id = $groupInfo['group_id'];
            } else {
                $group_id = '0';
            }
            // 统计已报名/已签到人数
            $arrayNum = $this->_getCheckInList($acInfo['activity_id'], $acInfo['title'], $feed_id, $acInfo['user_id'], $feedType, $group_id, $acInfo['is_group_record']);
            $dataArray = $errmsg = array();

//            $feedInfo = $toonMoel->getFeedInfoByRedis($feed_id);
            $feedInfo = User::getUserDetail($acInfo['user_id']);
            empty($feedInfo) && Fn::writeLog('details方法获取用户信息失败');
            
            $dataArray = array(
                'username' => $feedInfo['name'],
                'personal_sign' => $feedInfo['subtitle'],
                'avatar' => $feedInfo['avatar'],
                'school_id' => $feedInfo['school_id'],
//                'u_no' => isset($feedInfo['cardNo']) ? $feedInfo['cardNo'] : 0
            );
            $dataArray['id'] = $acInfo['activity_id'];
            $dataArray['c_fid'] = $acInfo['single_feed_id'];
            $dataArray['fid'] = $acInfo['group_feed_id'];
            $dataArray['id'] = $acInfo['activity_id'];
            $dataArray['uid'] = $acInfo['user_id'];
            $dataArray['title'] = $acInfo['title'];
            $dataArray['apply_sum'] = $arrayNum['sum'];
            $dataArray['apply_list'] = $arrayNum['list'];
            $dataArray['check_num'] = $arrayNum['checkPendingNum'];//待审核人数
            $dataArray['check_list'] = $arrayNum['check_list'];//待审核列表
            $dataArray['suceed_checkin'] = $arrayNum['succeedNum'];//已成功签到
            $dataArray['suceed_checkin_list'] = $arrayNum['checkList'];
            $acInfo['description'] = Fn::br2nl($acInfo['description']);
            $dataArray['description'] = str_replace('\\', '', str_replace("img&nbspsrc", "img src", ($acInfo['description'])));
            $dataArray['img'] = json_decode($acInfo['img'], true);
            $dataArray['type'] = $acInfo['type'];
            $dataArray['start_time'] = $acInfo['start_time'];
            $dataArray['end_time'] = $acInfo['end_time'];
            $dataArray['locate'] = $acInfo['locate'];
            $dataArray['address'] = $acInfo['address'];
            $dataArray['allow_apply'] = $acInfo['switch_status'] & 1;
            $dataArray['flag'] = $acInfo['flag'];
            $dataArray['isgroup'] = $acInfo['is_group'];
            $dataArray['longtitude'] = $acInfo['lng'];
            $dataArray['latitude'] = $acInfo['lat'];
            $dataArray['is_admin'] = $acInfo['is_admin'];
            if ($acInfo['price'] == 0.00 || $acInfo['price'] == 0) {
                $dataArray['price'] = 0;
            } else {
                $dataArray['price'] = floatval($acInfo['price']);
            }
            if ($acInfo['switch_status'] & 2) {
                $typeStatus = 1;
            } else {
                $typeStatus = 0;
            }
            $dataArray['checktype'] = $typeStatus;
            $dataArray['max'] = $acInfo['max_people'];
            
            $dataArray['status'] = $acInfo['status'];
            $dataArray['custom_field'] = json_decode($acInfo['custom_field'], true);
            $dataArray['apply_end_time'] = $acInfo['apply_end_time'];
            
            if ($acInfo['switch_status'] & 4) {
                $checkType = 1;
            } else {
                $checkType = 0;
            }
            $dataArray['need_checkin'] = $checkType;
            //$dataArray['checkin_start_time'] = $acInfo['checkin_start_time'];
            //$dataArray['checkin_end_time'] = $acInfo['checkin_end_time'];
            $dataArray['create_time'] = $acInfo['create_time'];
            $images = json_decode($acInfo['images'], true);
            $images = $images ? $images : [];
            $dataArray['images'] = $images;
            //$dataArray['publicity'] = $acInfo['publicity'];
            $dataArray['uuid'] = $acInfo['uuid'];
            $dataArray['phone'] = $acInfo['phone'];
            $dataArray['group_id'] = $group_id;
            $dataArray['switch_status'] = $acInfo['switch_status'];
            $dataArray['ac_notice'] = $contentInfo ? $contentInfo : " ";
            
            unset($typeStatus, $checkType);
            return $dataArray;
        } else {
            return false;
        }
    }
    
    
    /**获取我发起/报名活动列表信息
     * @param array $info
     * @return array
     */
    public function getMyListData($uid, $mark, $offset, $limit, $types = NULL)
    {
        $where = $limits = '';
        
        if (1 == $mark) {//发起方列表
            if ($types != 1) {
                $limits = " LIMIT 10 ";
                if ($offset > 0) {
                    $where = " AND activity_id < {$offset} ";
                }
            }
            
            
            $sql = "SELECT * FROM " . $this->_table . " WHERE user_id = " . $uid . " AND status = 1  " . $where . " ORDER BY create_time DESC  " . $limits;
            //Fn::writeLog("活动发起方列表：".var_export($sql,true));
        } else if (2 == $mark) {//参与方列表
            if ($types != 1) {
                $limits = " LIMIT 10 ";
                if ($offset > 0) {
                    $where = " AND aci.activity_id < {$offset} ";
                }
            }
            
            $sql = "SELECT aci.single_feed_id,aci.group_feed_id,aci.activity_id,aly.id AS acpid,aci.user_id,aci.title,aci.img,aci.is_group,aci.start_time,aci.end_time,aci.create_time,
                    aci.switch_status,aci.apply_end_time,aci.price,aly.feed_id,aly.verify_status,aly.checkin_status,aci.uuid, aly.status AS apply_status
                    FROM ac_apply aly
                  LEFT JOIN ac_activity aci ON (aci.activity_id = aly.activity_id)
                  WHERE aci.status = 1 AND aly.user_id = " . $uid . $where . " ORDER BY aly.create_time DESC " . $limits;
            //Fn::writeLog("活动参与方列表：".var_export($sql,true));
        }
        
        return $this->getRowsBySql($sql);
    }
    
    /**
     * 根据心动获取的活动ID获取活动信息
     * @param $ids
     * @param null $offset
     * @param null $limit
     * @return array
     */
    public function getListByAcId($ids)
    {
        $sql = "SELECT * FROM " . $this->_table . " WHERE status = 1 AND activity_id IN (" . $ids . ") ORDER BY start_time ASC ";
//        Fn::writeLog("getListByAcId:".var_export($sql,true));
        return $this->getRowsBySql($sql);
        
    }
    
    /**
     * 废弃
     * 活动搜索接口
     * @param $params
     * @return array
     */
    public function getAcList($offset, $limit, $title)
    {
        
        $limit = " LIMIT " . $limit;
        $where = '';
        if ($title) {
            $title = Fn::filterString($title);
            $where = " WHERE aci.title LIKE '%{$title}%' AND aci.flag & 1 AND  aci.status = 1 ";
            if ($offset > 0) {
                $where .= " AND aci.id < {$offset} ";
            }
            
            $order = " ORDER BY aci.create_time DESC ";
            $sql = $this->_mixSql() . $where . $order . $limit;
            
            return $this->getRowsBySql($sql);
        }
        
    }
    
    /**
     * 废弃
     * 公开活动--个人
     * @param $id --名片id
     * @param $time
     * @return array
     */
    public function getByOpenFeedList($id, $time, $frame)
    {
        
        $whereSql = " WHERE isgroup = 0 AND c_fid = '{$id}' AND start_time > '{$time}' AND status = 1 ";
        if ('af' == $frame) {
            $whereSql .= ' AND publicity = 1 ';
        }
        
        $sql = "SELECT id,c_fid AS feedId,title,create_time AS createdTimestamp, img AS image,start_time,end_time FROM " . $this->_table . " $whereSql ORDER BY create_time DESC ,id DESC ";
        return $this->getRowsBySql($sql);
    }
    
    /**
     * 废弃
     * 公开活动-- 群组
     * @param $id
     * @param $time
     */
    public function getByOpenGroupList($id, $time, $frame)
    {
        $whereSql = " WHERE isgroup = 1 AND fid = '{$id}' AND start_time > '{$time}' AND status = 1";
        if ('af' == $frame) {
            $whereSql .= ' AND publicity = 1 ';
        }
        
        $sql = "SELECT id,fid AS groupId,title, create_time AS createdTimestamp,img AS image,start_time,end_time FROM " . $this->_table . "  $whereSql ORDER BY create_time DESC,id DESC ";
        return $this->getRowsBySql($sql);
    }
    
    /**
     * 废弃
     * 获取该活动报名个数
     * @param $ac_id
     * @return mixed
     */
    public function getApplierCount($ac_id)
    {
        $sql = "SELECT `ac_id` AS `id`, COUNT(id) AS applierCount  FROM `ac_apply` WHERE `ac_id` = '{$ac_id}' AND `verify_status`= 1 GROUP BY `ac_id` ";
        $queryResult = $this->getRowsBySql($sql);
        if ($queryResult) {
            return $queryResult[0]['applierCount'];
        }
        
    }
    
    
    /**
     * 废弃
     * 获取群下所有活动
     * @param $feed_id --活动feed_id
     * @param $offet --偏移量
     * @param $limit --条数控制
     * @param $frame
     * @return array
     */
    public function getGroupList($feed_id, $offet, $limit, $frame, $time)
    {
        $where = '';
        $where .= " WHERE aci.fid = '{$feed_id}' AND aci.isgroup = 1 AND aci.status = 1 AND {$time} < aci.end_time ";
        $order = " ORDER BY aci.create_time DESC ";
        $limit = " LIMIT {$limit}";
        if ($offet > 0) {
            $where .= " AND aci.id < {$offet} ";
        }
        $sql = $this->_mixSql() . $where . $order . $limit;
        return $this->getRowsBySql($sql);
//        return  $this->getRows($where, array(), "create_time DESC ", $limit);
    
    }
    
    /**
     * 根据活动ID获取当前活动的附属信息
     * @param $ac_id
     * @return array
     */
    public function getActivityExtInfo($ac_id)
    {
        $where = " activity_id = '{$ac_id}'";
        return $this->getRow($where, '', null, 'ac_ext');
    }
    
    /**根据uuid获取活动信息
     * @param $uuid
     * @return array
     */
    public function getByAcInfoWithUuid($uuid)
    {
        $where = "uuid = '{$uuid}' AND status != 0 ";
        return $this->getRow($where);
    }
    
    /**
     * 根据活动ID获取活动基本信息
     * @param $ac_id
     * @return array
     */
    public function getActivityInfo($ac_id)
    {
        $where = "activity_id = '{$ac_id}' AND status != 0 ";
        return $this->getRow($where);
    }
    
    /**
     * 获取活动详情,包含全部状态
     * @param $ac_id
     * @return array|bool
     */
    public function getActivity($ac_id)
    {
        $where = "activity_id = '{$ac_id}'";
        return $this->getRow($where);
    }
    
    /**
     * 获取在status=1的活动
     * @return array
     * @auth liweiwei
     */
    public function getAcListByStatus($limit)
    {
        $sql = "SELECT * FROM `ac_activity` WHERE `status`=1 ORDER BY `activity_id` DESC LIMIT " . $limit;
        return $this->getRowsBySql($sql);
    }
    
    /**
     * 删除活动，伪删除，更新status字段
     * @param int $acId
     * @return int
     * @auth liweiwei
     */
    public function hideOne($acId)
    {
        return $this->update(array('status' => 2), "activity_id = {$acId}");
    }
    
    
    /**
     * 精品推荐
     * @param $offset
     * @param $limit
     * @param $mark
     * @return array
     */
    public function getPanelList($offset, $limit, $time, $poi = NULL)
    {
        
        // 先读缓存
//        $mcKey = $this->_mcPrefix."panellist::$offset::$limit";
//        $listFromMc = RedisClient::instance($this->_redisString)->get($mcKey);
//        if (!empty($listFromMc)) {
//            return unserialize($listFromMc);
//        }
        
        $order = $where = $limits = "";
        $where = " WHERE  aci.status = 1 AND aci.flag & 1 AND aci.flag & 4 AND {$time} < aci.end_time AND aci.flag != -1 AND aci.is_admin = 2";
        
        if ('index' == $poi) {
            if ($limit) {
                $limits = " LIMIT " . $limit;
            }
        }
        
        
        $order = " ORDER BY aci.order_no DESC, aci.start_time ASC ";
        $sql = $this->_mixSql() . $where . $order . $limits;
        $dataList = $this->getRowsBySql($sql);
        
        // 设置缓存3分钟
        //RedisClient::instance($this->_redisString)->setex($mcKey, $this->_indexMcTime, serialize($dataList));
        
        return $dataList;
    }
    
    /**
     * 热门活动
     * @param $limit
     * @param $offser
     * @param $mark
     * @return array
     */
    
    public function getHotList($offset, $limit, $time, $poi = null)
    {
        // 先读缓存
//        $mcKey = $this->_mcPrefix."hotlist::$offset::$limit";
//        $listFromMc = RedisClient::instance($this->_redisString)->get($mcKey);
//        if (!empty($listFromMc)) {
//            return unserialize($listFromMc);
//        }
        
        $where = $whereSql = $limits = '';
        if ('index' == $poi) {
            $whereSql = " AND !(aci.flag & 4)";
            if ($limit) {
                $limits = " LIMIT " . $limit;
            }
        }
        
        $where = " WHERE aci.status = 1 AND aci.flag & 1 AND {$time} < aci.end_time " . $whereSql;
        
        $order = "ORDER BY applierCount DESC,aci.start_time ASC";
        
        $sql = $this->_mixSql() . $where . $order . $limits;
        
        $dataList = $this->getRowsBySql($sql);
        
        // 设置缓存
        //RedisClient::instance($this->_redisString)->setex($mcKey, $this->_indexMcTime, serialize($dataList));
        
        return $dataList;
    }
    
    /**
     * 近期活动
     * @param $limit
     * @param $offset
     * @param $mark
     * @return array
     */
    public function getRecentList($offset, $limit, $time, $poi = null)
    {
        // 先读缓存
        $mcKey = $this->_mcPrefix . "recentlist::$offset::$limit";
        $listFromMc = RedisClient::instance($this->_redisString)->get($mcKey);
        if (!empty($listFromMc)) {
            return unserialize($listFromMc);
        }
        
        $where = $whereSql = $limits = '';
        if ('index' == $poi) {
            $whereSql = " AND !(aci.flag & 4)";
            if ($limit) {
                $limits = " LIMIT $limit ";
            }
        }
        $where = " WHERE aci.status = 1 AND aci.flag & 1 AND {$time} < aci.end_time " . $whereSql;
        
        $order = " ORDER BY aci.start_time ASC,applierCount DESC ";
        
        $sql = $this->_mixSql() . $where . $order . $limits;
        
        $dataList = $this->getRowsBySql($sql);
        
        // 设置缓存
        RedisClient::instance($this->_redisString)->setex($mcKey, $this->_indexMcTime, serialize($dataList));
        
        return $dataList;
    }
    
    
    /**
     * 新增公告并向待审核和已审核人员发送通知
     * @param $post
     * @return int|mixed
     */
    public function addNotice($post)
    {
        $noticeModel = new NoticeModel();
        $applyModel = new ApplyModel();
        
        $acInfo = $this->getByAcInfoWithUuid($post['ac_id']);
        if (1 == $acInfo['is_group']) {
            $feed_id = $acInfo['group_feed_id'];
            $feedType = "g";
        } else {
            $feed_id = $acInfo['single_feed_id'];
            $feedType = "c";
        }
        
        $content = $post['content'] ? Fn::nl2br($post['content']) : '';
        
        $data = [
            'activity_id' => $post['ac_id'],
            'content' => $content,
            'create_time' => time()
        ];
        
        $result = $this->add($data, false, false, 'ac_notice');
        
        Fn::writeLog("活动公告返回结果状态" . $result);
        $applyList = $applyModel->getApplyList("activity_id = {$acInfo['activity_id']} AND status = 1 AND verify_status != 2 ", "create_time DESC", 0);
        $message = "来自活动【" . $acInfo['title'] . "】的公告：\n" . strip_tags(Fn::br2nl($content));
        
        
        if ($result && $applyList) {
            $userId_array = array();
            foreach ($applyList as $val) {
                $userId_array[] = $val['user_id'];
            }
            $userIdStr = implode(',', $userId_array);
            unset($userId_array);
            $userList = User::getUserList($post['loginUser'], $userIdStr);
            if ($userList) {
                foreach ($userList as $key => $val) {
                    $codeData = [
                        'visitor' => array(
                            'feed_id' => $val['feedId'],
                            'uid' => $val['userId']
                        ),
                        'owner' => array(
                            'feed_id' => $val['feedId']
                        )
                    ];
                    $contentArr = [
                        'url' => Fn::generatePageUrl(3, $post['ac_id'], $codeData, $feedType),
                        'msg' => $message
                    ];
                    $noticeInfo = [
                        'fromFeedId' => $feed_id,
                        'toFeedId' => $val['feedId'],
                        'toUid' => $val['toon_uid'],
                        'contentArr' => $contentArr
                    ];
                    $noticeModel->addToList($noticeInfo);
                }
            }
            
            return true;
        }
    }
    
    /**
     * 公告列表
     * @param $offset
     * @param $limit
     * @param $id
     * @return array
     */
    public function getNoticeList($offset, $limit, $id)
    {
        $where = "activity_id = '{$id}' ";
        $order = " create_time DESC ";
        $num = $this->_getNoticeNum($id);
        if ($num >= 10) {
            if ($offset > 0) {
                $where .= " AND activity_id < {$offset} ";
            }
        }
        
        
        $this->_table = "ac_notice";
        return $this->getRows($where, array(), $order, $limit);
    }
    
    /**
     * 删除公告
     * @param $id
     */
    public function deleteNotice($id)
    {
        $where = "activity_id= {$id}";
        $this->delete($where, 'ac_notice');
    }
    
    /**
     * 根据活动id获取群聊相关信息
     * @param $ac_id
     * @param $gid
     */
    public function detailChatInfo($ac_id, $gid)
    {
        $where = $whereSql = "";
        $this->_table = "ac_group_chat";
        if ($gid) {
            $whereSql = " AND group_id = {$gid}";
        }
        $where = " activity_id = {$ac_id}" . $whereSql;
        return $this->getRow($where);
    }
    
    /**
     * 保存群聊ID
     * @param $ac_id
     * @param $gid
     * @return int
     */
    public function addChatInfo($ac_id, $gid)
    {
        $this->_table = "ac_group_chat";
        $data = [
            'activity_id' => $ac_id,
            'group_id' => $gid,
            'create_time' => time()
        ];
        
        return $this->add($data);
    }
    
    /**
     * 根据活动ID更新活动群聊表中的group_id
     * @param $ac_id
     * @param $gid
     * @return bool|unknown
     */
    public function updateChatInfo($ac_id, $gid)
    {
        $this->_table = "ac_group_chat";
        $data = [
            'group_id' => $gid
        ];
        $where = [
            'activity_id' => $ac_id
        ];
        return $this->update($data, $where);
    }
    
    /**
     * 仅限ac_apply表
     * 根据type不同值，拼写不同where条件和order条件
     * 查询报名或签到人员信息
     * @param $type
     * @param $ac_id
     * @return array
     */
    public function applyList($type, $ac_id)
    {
        $where = "";
        if (1 == $type) {
            $where = "status = 1 AND verify_status = 1 AND activity_id='{$ac_id}'";
            $order = "create_time DESC ";
            $num = 0;
        } else if (2 == $type) {
            $where = "status = 1 AND verify_status = 1 AND activity_id='{$ac_id}'";
            $order = "create_time DESC ";
            $num = 0;
        }
        $model = new ApplyModel();
        $list = $model->getApplyList($where, $order, $num);
        return $list;
    }
    
    /**
     * 向剩余已报名人员发送群聊通知
     * @param $list
     * @param $acInfo
     * @return bool
     */
    public function remainingPeopleWithApply($list, $acInfo, $loginUid)
    {
        $noticeModel = new NoticeModel();
        
        if (1 == $acInfo['is_group']) {
            $feed_id = $acInfo['group_feed_id'];
            $feedType = "g";
        } else {
            $feed_id = $acInfo['single_feed_id'];
            $feedType = "c";
        }
        
        $sliceArray = array_slice($list, 2);
        if ($sliceArray) {
            $tmpUserArray = array();
            foreach ($sliceArray as $val) {
                $tmpUserArray[] = $val['user_id'];
            }
            $userIdStr = implode(',', $tmpUserArray);
            unset($tmpUserArray);
            $userList = User::getUserList($loginUid, $userIdStr);
            $message = "您参加的活动【" . $acInfo['title'] . "】，已创建活动群聊，参与群聊，认识更多小伙伴，快去和大家打招呼吧。";
            foreach ($userList as $key => $val) {
                
                $codeData = [
                    'visitor' => array(
                        'feed_id' => $val['feedId'],
                        'uid' => $val['userId']
                    ),
                    'owner' => array(
                        'feed_id' => $feed_id
                    )
                ];
                $contentArr = [
                    'url' => Fn::generatePageUrl(3, $acInfo['activity_id'], $codeData, $feedType),
                    'msg' => $message,
                    'needHeadFlag' => 0
                ];
                $noticeInfo = [
                    'fromFeedId' => $feed_id,
                    'toFeedId' => $val['feedId'],
                    'toUid' => $val['toon_uid'],
                    'contentArr' => $contentArr
                ];
                $noticeModel->addToList($noticeInfo);
            }
            return true;
        }
    }
    
    /**
     * 更新推荐后flag字段
     * @param int $acId
     * @param int $flag
     * @return int
     */
    public function updateRecommendFlag($acId, $flag)
    {
        return $this->update(array('flag' => $flag), "activity_id = {$acId}");
    }
    
    /**
     * 后台及定时任务相关操作
     */
    
    /**
     * 查询未开始活动且状态为1的数据
     * @param $time
     * @return array
     */
    public function getListForActivity($time)
    {
        /*****优化*****/
        $where = " status = 1 AND start_time > {$time}";
        return $this->getRows($where);
        /*************/

//        $sql = "SELECT id,title,start_time,allow_apply,need_checkin,checkin_start_time,c_fid,fid,isgroup,record_notice, switch_status FROM ac_info WHERE status = 1 AND start_time > {$time} ";
//        return $this->getRowsBySql($sql);
    }
    
    /**
     * 保存发送通知记录
     * @param $id
     * @param $mark
     */
    public function updateRecordNotice($id, $mark, $record)
    {
//         $record = 0;
        if (1 == $mark) {
            $status = $record | 1;
        } else if (2 == $mark) {
            $status = $record | 2;
        }
        $this->_table = "ac_activity";
        $sql = "UPDATE ". $this->_table . " SET record_notice = {$status} WHERE activity_id={$id}";
        $resultStatus = $this->update($sql);
        return $resultStatus;
    }
    
    /**
     * 根据$mark标识来分别获取报名人员和签到人员信息
     * @param $mark
     * @param $ac_id
     * @return array
     */
    public function getApplyInfo($mark, $ac_id)
    {
        $applyModel = new ApplyModel();
        $order = $limit = "";
        $where = " status = 1 AND activity_id='{$ac_id}' ";
        if (1 == $mark) {
            $where .= " AND `verify_status` = 1 ";
            $order = " `create_time` DESC  ";
        } else {
            $where .= "  AND `verify_status` = 1";
            $order = " `checkin_time` DESC ";
        }
        $list = $applyModel->getApplyList($where, $order, $limit);
        unset($where, $order, $limit);
        return $list;
    }
    
    /**
     * 对于已结束活动状态更改已删除
     * @param $acId
     * @return bool|unknown
     */
    public function changeStatus($acId)
    {
//        $acId = intval($acId);
//        $sql = "UPDATE ".$this->_table." SET status = 0 WHERE id={$acId}";
//        return $this->update($sql);
        /***优化之后***/
        $data = [
            'status' => 0
        ];
        $where = [
            'activity_id' => $acId
        ];
        return $this->update($data, $where);
    }
    
    /**
     * 获取某条活动的报名签到个数
     * @param $id
     * @return array
     */
    public function getAcStatInfo($id)
    {
        $this->_table = "ac_stat";
        $id = intval($id);
        $where = "";
        $where .= "activity_id = {$id}";
        return $this->getRow($where);
    }
    
    /**
     * 获取最新的一条活动公告
     */
    private function _getNoticeInfo($ac_id)
    {
        $newContent = '';
        $sql = "SELECT * FROM ac_notice WHERE  activity_id = '{$ac_id}' ORDER BY create_time DESC LIMIT 1 ";
        $result = $this->getRowsBySql($sql);
        if ($result) {
            
            foreach ($result as $val) {
                $newContent = $val['content'];
            }
        }
        
        return $newContent;
    }
    
    /**
     * 根据部分业务可复用Sql查询
     * @return string
     */
    private function _mixSql()
    {
//        $sql = "SELECT distinct aci.id,aci.title,aci.img,aci.type,aci.start_time,aci.end_time,aci.locate,aci.switch_status,aci.apply_end_time,aci.uuid,aci.price,aci.c_fid,aci.fid,aly.applierCount,ace.longtitude,ace.latitude FROM ".$this->_table." AS aci
//                LEFT JOIN (SELECT ac_id,COUNT(ac_id) AS applierCount FROM ac_apply WHERE verify_status = 1 GROUP BY ac_id ) AS aly ON (aci.id = aly.ac_id)
//                LEFT JOIN ac_ext AS ace ON (aci.id = ace.ac_id)";
//        return $sql;
        
        $sql = "SELECT aci.*,ace.lat AS latitude,ace.lng AS longtitude,(acs.apply_num+acs.love_num) AS applierCount FROM " . $this->_table . " AS aci
                  LEFT JOIN ac_ext AS ace ON (aci.activity_id = ace.activity_id)
                  LEFT JOIN ac_stat AS acs ON (aci.activity_id = acs.activity_id)";
        return $sql;
    }
    
    /**
     * @param $list
     * @return mixed
     */
    private function _getFeedInfo($list)
    {
        foreach ($list as $key => $val) {
//            $feedInfo = $toonModel->getFeedInfoByRedis($val['feed_id']);
            $feedInfo = User::getUserDetail($val['user_id']);
            if ($feedInfo) {
                $list[$key]['f_title'] = $feedInfo['name'];
                $list[$key]['subtitle'] = $feedInfo['subtitle'];
                $list[$key]['avatarId'] = $feedInfo['avatar'];
                $list[$key]['user_toon_id'] = $feedInfo['toon_uid'];
            }
            
        }
        return $list;
    }
    
    /**
     * 统计已报名/已签到人数
     * @param array $info
     */
    private function _getCheckInList($ac_id, $title, $feed_id, $uid, $feedType, $group_id, $group_record)
    {
        $noticeModel = new NoticeModel();
        $applyModel = new ApplyModel();
        $acUserInfo = User::getUserDetail($uid);
        
        $array = array();
        
        //已审核通过
        $sum = 0;
        $sum = $applyModel->getApplyNum($ac_id, 1);
        
        $array['sum'] = $sum ? intval($sum) : 0;
        //已审核列表
        $list = $applyModel->getApplyList("status = 1 AND `verify_status` = 1 AND activity_id='{$ac_id}'", '`create_time` DESC ', $array['sum']);
        $list = $this->_jsonToArrayForCusInfo($list);
        
        $list = $this->_getFeedInfo($list);
        
        $array['list'] = $list ? $list : 0;
        /*************START**************/
        if (empty($group_id)) {
            if ($array['sum'] == 2 && $list) {
                $codeData = array(
                    'visitor' => array(
                        'feed_id' => $feed_id,
                        'uid' => $uid
                    ),
                    'owner' => array(
                        'feed_id' => $feed_id
                    )
                );
                
                $message = "您发布的活动【" . $title . "】，可以创建群聊啦，快去创建群聊和报名的小伙伴们交流吧。";
                $contentArr = [
                    'url' => Fn::generatePageUrl(3, $ac_id, $codeData, $feedType),
                    'msg' => $message,
                    'needHeadFlag' => 0
                ];
                
                $noticeInfo = array(
                    'fromFeedId' => $feed_id,
                    'toFeedId' => $feed_id,
                    'toUid' => $acUserInfo['toon_uid'],
                    'contentArr' => $contentArr
                );
                
                if (0 == $group_record) {
                    $noticeModel->addToList($noticeInfo);
                    $this->updateAcExtForGroup($ac_id);
                }
                
            }
        }
        /**************END****************/
        //待审核
        $array['checkPendingNum'] = $applyModel->getApplyNum($ac_id, 0);
        if ($array['checkPendingNum'] >= 100) {
            $array['checkPendingNum'] = '99+';
        }
        $check_list = $applyModel->getApplyList("status = 1 AND `verify_status` = 0 AND activity_id='{$ac_id}'", '`create_time` DESC ', 0);
        $check_list = $this->_jsonToArrayForCusInfo($check_list);
        $check_list = $this->_getFeedInfo($check_list);
        $array['check_list'] = $check_list ? $check_list : array();
        //签到
        $sql = "SELECT COUNT(id) FROM ac_apply WHERE activity_id = {$ac_id} AND status = 1 AND checkin_status = 1";
        $total = $this->getRowsBySql($sql);
        $checkInNum = $total[0]['COUNT(id)'];
        
        //已成功签到
        $array['succeedNum'] = $checkInNum ? $checkInNum : 0;
        //已成功签到列表
        $checkList = $applyModel->getApplyList("status = 1 AND `checkin_status` = 1 AND activity_id='{$ac_id}'", '`checkin_time` DESC ', 0);
        $checkList = $this->_jsonToArrayForCusInfo($checkList);
        $checkList = $this->_getFeedInfo($checkList);
        $array['checkList'] = $checkList ? $checkList : 0;
        
        return $array;
    }
    
    /**
     * 根据活动ID更新活动扩展表is_group_record字段值 1代表已发送过通知;默认0为未发送通知
     * @param $id
     * @return bool|unknown
     */
    public function updateAcExtForGroup($id)
    {
        $this->_table = "ac_ext";
        $data = [
            'is_group_record' => 1
        ];
        $where = [
            'activity_id' => $id
        ];
        return $this->update($data, $where);
    }
    
    /**
     * json格式的cus_info值转换为数组格式
     * @param $list
     * @return array
     */
    private function _jsonToArrayForCusInfo($list)
    {
        if (empty($list)) {
            return array();
        }
        foreach ($list as $key => $val) {
            $list[$key]['cus_info'] = $val['cus_info'] ? json_decode($val['cus_info'], true) : array();
        }
        return $list;
    }
    
    /**
     * 组合符合动态的数据格式--废弃
     * @param $post
     * @param $uuid
     * @return bool
     */
    private function _addToTrends($post, $uuid)
    {
        
        $toon = new ToonModel();
        
        if (0 == $post['isgroup']) {
            $feed_id = $post['c_fid'];
        } else {
            $feed_id = $post['fid'];
        }
        $feedInfo = $toon->getFeedInfoByRedis($feed_id);
        
        $feedMark = explode('_', $feed_id);
        $diffusionType = '';
        if ("g" == $feedMark['0']) {
            $diffusionType = 2;
            $feed_type = 'g';
            $entryType = 2;
        } else if ("c" == $feedMark['0']) {
            $diffusionType = 1;
            $feed_type = 'c';
            $entryType = 1;
        } else if ("s" == $feedMark['0']) {
            $diffusionType = 3;
            $feed_type = 's';
            $entryType = 1;
        }
        if ($post['price'] == 0.00 || $post['price'] == 0) {
            $post['price'] = '免费';
        } else {
            $post['price'] = floatval($post['price']) . '元/人';
        }
        $img = json_decode($post['img'], true);
        
        $shareContentArr = array(
            'imgUrl' => $img['url'] ? $img['url'] : ' ',
            'title' => $post['title']
        );
        $url = Yaf_Registry::get('config')->get('site.info.url');
        
        if (empty($url)) {
            Fn::writeLog("入动态获的跳转链接失败");
            return false;
        }
        $linkUrl = $url . '/html/src/index.html?entry=3&ac_id=' . $uuid . '&feed_type=' . $feed_type . '&entryType=' . $entryType;
        $rssContentArr = array(
            'from' => array(
                'value' => '来自' . $feedInfo['title'] . '发起的活动',
            ),
            'image' => array(
                'value' => $img['url'] ? $img['url'] : ' ',
                'width' => $img['width'] ? $img['width'] : 0,
                'height' => $img['height'] ? $img['height'] : 0
            ),
            'title' => array(
                'value' => $post['title']
            ),
            'time' => array(
                'value' => date('m月d日 H:i', $post['start_time']) . '至' . date('m月d日 H:i', $post['end_time'])
            ),
            'location' => array(
                'longitude' => $post['longtitude'] ? $post['longtitude'] : 0,
                'latitude' => $post['latitude'] ? $post['latitude'] : 0,
                'location' => $post['locate']
            ),
            'price' => array(
                'value' => $post['price']
            ),
            'linkUrl' => $linkUrl
        );
        
        $trendResult = Toon::addToTrends($feed_id, $linkUrl, $diffusionType, json_encode($rssContentArr), $shareContentArr);
        Fn::writeLog("动态返回结果状态：" . json_encode($trendResult));
        
        return $trendResult;
        
    }
    
    /**
     * 获取公告条数
     * @param $ac_id
     * @return mixed
     */
    private function _getNoticeNum($ac_id)
    {
        $sql = " SELECT COUNT(activity_id) FROM ac_notice WHERE activity_id = '{$ac_id}'";
        $result = $this->getRowsBySql($sql);
        return $result[0]['COUNT(activity_id)'];
    }
    
}