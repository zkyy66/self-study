<?php
/**
* @description 运营后台控制器
* @author liweiwei
* @version 2016-11-1上午9:26:20
*/

class IndexController extends AdminBaseController
{
    public $acModel, $acAdminModel,$noticeModel;

    
    public function init()
    {
        $this->acModel      = new ActivityModel();
        $this->acAdminModel = new ActivityadminModel();
        $this->noticeModel  = new NoticeModel();
        
        header("Access-Control-Allow-Origin:*");
        parent::init();
    }
    
    /**
     * 获取活动列表，包含tab切换，搜索
     */
    public function ListAction()
    {
        
        $tab        = intval($this->request->getQuery('tab', 0)); // 0-全部 1-未上墙 2-已上墙 3-已下墙
        $title      = urldecode(trim($this->request->getQuery('title', ''))); // 活动标题
        $u_no       = trim($this->request->getQuery('u_no', '')); // 名片号
        $operator   = urldecode(trim($this->request->getQuery('operator', ''))); // 操作者姓名
        $apply_end_time_s = urldecode($this->request->getQuery('apply_start', '')); // 报名截止时间搜索范围开始
        $apply_end_time_e = urldecode($this->request->getQuery('apply_end', '')); // 报名截止时间搜索范围结束
        $ac_start_time_s  = urldecode($this->request->getQuery('start_start', '')); // 活动开始时间搜索范围开始
        $ac_start_time_e  = urldecode($this->request->getQuery('start_end', '')); // 活动开始时间搜索范围结束
        $ac_create_time_s = urldecode($this->request->getQuery('create_start', '')); // 活动发布时间搜索范围开始
        $ac_create_time_e = urldecode($this->request->getQuery('create_end', '')); // 活动发布时间搜索范围结束
        $ac_nickName = urldecode($this->request->getQuery('nickname',''));//用户昵称
        
        $page     = intval($this->request->getQuery('page', 1));
        $perpage  = intval($this->request->getQuery('rows', 10));
        
        // 根据tab找到对应的wall_type
        $tabToWall = array(0=>-1, 1=>0, 2=>1, 3=>2);
        $onwallType = $tabToWall[$tab];
       
        // 组织搜索条件
        $filter = array();
//        if (!in_array($onwallType, array(-1, 0, 1, 2))) {
//            Fn::outputToJson(self::ERR_PARAM, '参数不正确');
//        } else if ($onwallType != -1) {
//            $flagArr = array(0=>0, 1=>1, 2=>2,3=>3);
//            $filter['flag'] = $flagArr[$onwallType];
//        }
       $filter['tab'] = $tab;
        if ($title) {
            $filter['title'] = $title;
        }
        if ($u_no) {
            $filter['u_no'] = $u_no;
        }
        if ($operator) {
            $filter['operator'] = $operator;
        }
        if ($apply_end_time_s) {
            $filter['apply_end_time_s'] = strtotime($apply_end_time_s);
        }
        if ($apply_end_time_e) {
            $filter['apply_end_time_e'] = strtotime($apply_end_time_e);
        }
        if ($ac_start_time_s) {
            $filter['ac_start_time_s'] = strtotime($ac_start_time_s);
        }
        if ($ac_start_time_e) {
            $filter['ac_start_time_e'] = strtotime($ac_start_time_e);
        }
        if ($ac_create_time_s) {
            $filter['ac_create_time_s'] = strtotime($ac_create_time_s);
        }
        if ($ac_create_time_e) {
            $filter['ac_create_time_e'] = strtotime($ac_create_time_e);
        }
        if ($ac_nickName) {
            $filter['nickname'] = $ac_nickName;
        }
        // 获取符合条件的总个数，用于计算分页
        $total = $this->acAdminModel->getNum($filter);
      
        if (!$total) {
            exit(json_encode(array('rows'=>array(), 'total'=>0)));
        }
        
        // 根据分页组织limit语句
        $limit = Fn::getLimitStrAction($total, $perpage, $page);
        
        // 组织order语句
//        if ($onwallType == 1) {
//            $order = " ORDER BY aci.`ord` DESC, aci.`create_time` DESC ";
//        } else {
//            $order = " ORDER BY aci.`create_time` DESC ";
//        }
        if (0 == $tab) {
            $order = " ORDER BY aci.update_time DESC ";
        } else {
            $order  = " ORDER BY acop.update_time DESC ";
        }
       
        $list = $this->acAdminModel->getList($filter, $order, $limit);
       
        // 组织前台展示的数据
        $typeArr = array(0=>'娱乐', 1=>'兴趣',2=>'户外', 3=>'展览', 4=>'演出', 5=>'会议', 6=>'运动', 7=>'讲座沙龙');
        foreach ($list as $k=>$v) {
            $tmpData[$k] = array(
                'id'             => $v['id'],
                'title'          => $v['title'],
                'type'           => $typeArr[$v['type']],
                'u_no'           => $v['u_no'],
                'create_time'    => $v['create_time'],
                'start_time'     => $v['start_time'],
                'apply_end_time' => $v['apply_end_time'],
                'wall_type_str'  => '未上墙',
                'read_type_str'  => '未读',
                'wall_type'      => '0',
                'read_type'      => '0',
                'operator'       => ''.$v['staff'],
                'ord'            => $v['ord'],
                'publicity' => $v['publicity'],
                'publicity_str' => $v['publicity'] == 1? '公开' : '不公开',
                'nickname' => $v['nickname'] ? $v['nickname'] : ''
            );
            
            if ($v['end_time'] < time()) {
                $tmpData[$k]['is_end'] = 1;
                $tmpData[$k]['title'] = '【已结束】'.$tmpData[$k]['title'];
            } elseif ($v['start_time'] < time()) {
                $tmpData[$k]['is_end'] = 0;
                $tmpData[$k]['title'] = '【进行中】'.$tmpData[$k]['title'];
            } else {
                $tmpData[$k]['is_end'] = 0;
                $tmpData[$k]['title'] = '【未开始】'.$tmpData[$k]['title'];
            }
            //可操作状态 1-审核是否通过 2-是否处理 4-是否推荐 8-存在/不存在
           
            if (0 == $tab) {
                $tmpData[$k]['wall_type'] = 1;
            } else if (1 == $tab){
                $tmpData[$k]['wall_type'] = 2;
            } else if (2 == $tab) {
                $tmpData[$k]['wall_type'] = 3;
            } else if (3 == $tab) {
                $tmpData[$k]['wall_type'] = 4;
            }
            
            // flag:上墙状态 0-初始状态 1-已读 2-已编辑 4-已修改 8-备选 16-上墙 32-下墙
//            if ($v['flag']&16) {
//                $tmpData[$k]['wall_type_str'] = '已上墙';
//                $tmpData[$k]['wall_type'] = 1;
//            } else if ($v['flag']&32) {
//                $tmpData[$k]['wall_type_str'] = '已下墙';
//                $tmpData[$k]['wall_type'] = 2;
//            } else {
//                $tmpData[$k]['wall_type_str'] = '未上墙';
//                $tmpData[$k]['wall_type'] = 0;
//            }
//            if ($v['flag']&1) {
//                $tmpData[$k]['read_type_str'] = '已读';
//                $tmpData[$k]['read_type'] = 1;
//            } else {
//                $tmpData[$k]['read_type_str'] = '未读';
//                $tmpData[$k]['read_type'] = 0;
//            }
            $tmpData[$k]['op']='';
        }
        
        exit(json_encode(array('rows'=>$tmpData, 'total'=>$total)));
    }
    
    /**
     * 删除活动接口，伪删除，更新status字段的值
     */
    public function deleteAction()
    {
        $acId = intval($this->request->getPost('id', 0));
        if (!$acId) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        $acInfo = $this->acModel->details($acId);
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        
        // 删除活动
        if (!$this->acAdminModel->deleteOne($acId)) {
            Fn::outputToJson(self::ERR_PARAM, '删除失败，请稍后重试');
        }
        
        // 删除的活动也要取消推荐
        $flag = $acInfo['flag'];
        if ($flag&4) {
            $flag = $flag^4;
        }
        $this->acAdminModel->changeFlag($acId, $flag);
        
        // 添加操作记录
        $this->acAdminModel->addOpRecord($acId, $this->_operatorInfo['name'], '删除');
        $this->acAdminModel->checkNotice($acId,4,'');
        
        // 删除首页推荐列表的缓存
        $this->acModel->removeAllMc();
        
        Fn::outputToJson(self::OK, 'OK');
    }
    
    /**
     * 修改显示顺序
     * @param ac_id 活动id
     * @param order 顺序
     */
    public function changeOrderAction()
    {
        $acId     = intval($this->request->getPost('id', 0));
        $newOrder = intval($this->request->getPost('ord', 0));
        if (!$acId) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        
        if (!$this->acAdminModel->changeOrder($acId, $newOrder)) {
            Fn::outputToJson(self::ERR_PARAM, '更新失败，请稍后重试');
        }
        
        // 添加操作记录
        $this->acAdminModel->addOpRecord($acId, $this->_operatorInfo['name'], '更新顺序');
        
        Fn::outputToJson(self::OK, 'OK');
    }
    
    /**
     * 上下墙
     * @param ac_id 活动id
     * @param wall_type 上墙还是下墙
     * @param time 定时时间  Y-m-d H:i:s
     */
    public function onOffWallAction()
    {
        $acId     = intval($this->request->getPost('id', 0));
        $wallType = intval($this->request->getPost('wall_type', 0)); // 2-下墙 1-上墙
        $time     = trim($this->request->getPost('time', '')); // 定时时间，如果是空的，即立即生效
        
        if (!$acId || !$wallType || !in_array($wallType, array(1, 2))) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        
        $acInfo = $this->acModel->details($acId);
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        
        // 设置的时间大于当前时间，才加到定时任务表
        if ($time && strtotime($time) > time()) {
            $acWallTaskModel = new WallTaskModel();
            $row = array('ac_id'=>$acId, 'wall_type'=>$wallType, 'time'=>strtotime($time), 'create_time'=>time());
            if (!$acWallTaskModel->addWallTask($row)) {
                Fn::outputToJson(self::ERR_SYS, '更新失败，请稍后重试');
            }
            // 添加操作记录
            $this->acAdminModel->addOpRecord($acId, $this->_operatorInfo['name'], ($wallType == 1? '定时上墙':'定时下墙'));
            
            Fn::outputToJson(self::OK, '定时任务添加成功', array('ac_id'=>$acId, 'wall_type'=>$wallType));
        }
        
        // 设置的时间小于当前时间，或者没设置时间，视为立即执行
        $flag = $acInfo['flag'];
        if ($wallType == 2) {
            // 去掉上墙的状态，添加下墙的状态
            // flag:上墙状态 0-初始状态 1-已读 2-已编辑 4-已修改 8-备选 16-上墙 32-下墙
            if ($flag&16) {
                $flag = $flag^16;
            }
            if (!($flag&32)) {
                $flag = $flag|32;
            }
        } elseif ($wallType == 1) {
            // 去掉下墙的状态，添加上墙的状态
            if ($flag&32) {
                $flag = $flag^32;
            }
            if (!($flag&16)) {
                $flag = $flag|16;
            }
        }
        
        if (!$this->acAdminModel->changeFlag($acId, $flag)) {
            Fn::outputToJson(self::ERR_SYS, '更新失败，请稍后重试');
        }
        
//         // 添加操作记录
//         $this->acAdminModel->addOpRecord($acId, $this->_operatorInfo['name'], ($wallType == 1? '上墙':'下墙'));
        
//         // 如果是上墙，需要发通知
//         if ($wallType == 1) {
//             $codeArr = array(
//                     'visitor'=>array('uid'=>$acInfo['uid'], 'feed_id'=>$acInfo['c_fid']),
//                     'owner'=>array('feed_id'=>$acInfo['c_fid'], 'uid'=>$acInfo['uid'],),
//             );
//             if ($acInfo['isgroup']) {
//                 $codeArr['owner']['feed_id'] = $acInfo['fid'];
//                 $feedType = 'g'; // 代表群组活动
//             } else {
//                 $feedType = 'c'; // 代表个人活动
//             }
//             $contentArr = [
//                 'url' => Fn::generatePageUrl(null, $acId, $codeArr, $feedType),
//                 'msg' => "你发布的活动【{$acInfo['title']}】已上墙，快去查看吧",
//                 'needHeadFlag'=>0,
//                 "buttonTitle" => '去看看',
//             ];
            
//             $noticeInfo = array('fromFeedId'=>$acInfo['c_fid'], 'toFeedId'=>$acInfo['c_fid'], 'toUid'=>$acInfo['uid'], 'contentArr'=>$contentArr);
//             $this->noticeModel->addToList($noticeInfo);
//         }
        
        Fn::outputToJson(self::OK, 'OK', array('ac_id'=>$acId, 'wall_type'=>$wallType));
    }
    
    /**
     * 查看活动详情
     * @param id 活动id
     */
    public function viewAction()
    {
        $acId = $this->request->getQuery('id', 0);
        
        if (!$acId) {
            Fn::outputToJson(self::ERR_PARAM, '缺失必要参数');
        }
        
        $acInfo = $this->acModel->details($acId);
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM,'error', '该活动不存在');
        }
        
        // 组织返回的数据
        if (isset($acInfo['img']['url'])) {
            $acInfo['img'] = $acInfo['img']['url'];
        }
        $acInfo['start_time'] = empty($acInfo['start_time']) ? '': date("Y-m-d H:i", $acInfo['start_time']);
        if ($acInfo['end_time'] < time()) {
            $acInfo['is_end'] = 1;
        } else {
            $acInfo['is_end'] = 0;
        }
        $acInfo['apply_price'] = empty($acInfo['price']) ? '免费': $acInfo['price']."元/人";
        
        $acInfo['end_time'] = empty($acInfo['end_time']) ? '': date("Y-m-d H:i", $acInfo['end_time']);
        $acInfo['apply_end_time'] = empty($acInfo['apply_end_time']) ? '': date("Y-m-d H:i", $acInfo['apply_end_time']);
        $acInfo['checkin_start_time'] = empty($acInfo['checkin_start_time']) ? '': date("Y-m-d H:i", $acInfo['checkin_start_time']);
        $acInfo['checkin_end_time'] = empty($acInfo['checkin_end_time']) ? '': date("Y-m-d H:i", $acInfo['checkin_end_time']);
        if ($acInfo['allow_apply']) {
            $acInfo['allow_apply_str'] = '开启';
        } else {
            $acInfo['allow_apply_str'] = '关闭';
        }
        if ($acInfo['switch_status'] & 2) {
            $acInfo['checktype_str'] = '开启';
        } else {
            $acInfo['checktype_str'] = '关闭';
        }
        if ($acInfo['switch_status'] & 4) {
            $acInfo['need_checkin_str'] = '开启';
        } else {
            $acInfo['need_checkin_str'] = '关闭';
        }
        if ($acInfo['custom_field']) {
            $acInfo['custom_field_str'] = '';
            foreach ($acInfo['custom_field'] as $k=>$v) {
                $acInfo['custom_field_str'].= $v['value']." ";
            }
            $acInfo['custom_field_str'] = trim($acInfo['custom_field_str'], ' ');
        } else {
            $acInfo['custom_field_str'] = '';
        }
        
        if ($acInfo['images']) {
            $tmpHtml = '';
            foreach ($acInfo['images'] as $v) {
                $tmpHtml .= '<img class="descriptionimg" src="'.$v['url'].'" data-width="'.$v['width'].'" data-height="'.$v['height'].'">';
            }
            $acInfo['images'] = $tmpHtml;
        }
        $acInfo['phone'] = empty($acInfo['phone']) ? '暂无联系方式': $acInfo['phone'];
        $acInfo['url'] = 'http://signon.systoon.com/html/src/index.html?entry=101&ac_id='.$acInfo['uuid'];
        
        if ($acInfo['flag']&16) {
            $acInfo['wall_type'] = 1;
        } else if ($acInfo['flag']&32) {
            $acInfo['wall_type'] = 2;
        } else {
            $acInfo['wall_type'] = 0;
        }
        $acInfo['recommend_type'] =  $acInfo['flag'] & 4 ? 1 : 0;
//         // 设为已读
//         $flag = $acInfo['flag'];
//         if (!($flag & 1)) {
//             $flag = $flag|1;
//         }
//         $this->acAdminModel->changeFlag($acId, $flag);
        
        // 添加操作记录
//         $this->acAdminModel->addOpRecord($acId, $this->_operatorInfo['name'], '已读');
        
        Fn::outputToJson(self::OK,'ok', $acInfo);
    }
}