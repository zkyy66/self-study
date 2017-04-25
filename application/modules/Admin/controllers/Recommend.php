<?php
/**
* @description 运营后台控制器
* @author liweiwei
* @version 2016-11-1上午9:26:20
*/

class RecommendController extends AdminBaseController
{
    public $acModel, $acAdminModel, $noticeModel, $toonModel;
    
    public function init()
    {
        $this->acModel      = new ActivityModel();
        $this->acAdminModel = new ActivityadminModel();
        $this->noticeModel  = new NoticeModel();
        $this->toonModel    = new ToonModel();
        
        header("Access-Control-Allow-Origin:*");
        parent::init();
    }
    
    /**
     * 获取活动列表，包含tab切换，搜索
     */
    public function ListAction()
    {
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
        
        $get_recommend    = intval($this->request->getQuery('get_recommend', 0)); // 1-获取推荐的数据 0-获取审核通过的数据
      
        $page     = intval($this->request->getQuery('page', 1));
        $perpage  = intval($this->request->getQuery('rows', 10));
        
        // 组织搜索条件
        $filter = array();

        $filter['get_recommend'] = $get_recommend;
        if ($title) {
            $filter['title'] = $title;
        }
        if ($u_no) {
            $filter['u_no'] = $u_no;
        }
        if ($operator) {
            $filter['operator'] = $operator;
        }
        if ($ac_nickName) {
            $filter['nickname'] = $ac_nickName;
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
        
         $filter['ac_end_time_s'] = time();
         $filter['publicity'] = 1;
        
        // 获取符合条件的总个数，用于计算分页
        $total = $this->acAdminModel->getNum($filter);
        if (!$total) {
            exit(json_encode(array('rows'=>array(), 'total'=>0)));
        }
        
        // 根据分页组织limit语句
        $limit = Fn::getLimitStrAction($total, $perpage, $page);
        
        if ($get_recommend) {
            $order = " ORDER BY aci.`ord` DESC , aci.`start_time` ASC ";
        } else {
            $order = " ORDER BY aci.`start_time` ASC ";
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
                'nickname'       => $v['nickname'],
                'create_time'    => $v['create_time'],
                'start_time'     => $v['start_time'],
                'end_time'      =>  $v['end_time'],     
                'apply_end_time' => $v['apply_end_time'],
                'wall_type_str'  => '未上墙',
                'read_type_str'  => '未读',
                'wall_type'      => '0',
                'read_type'      => '0',
                'operator'       => ''.$v['staff'],
                'ord'            => $v['ord'],
                'publicity'      => $v['publicity'],
                'publicity_str'  => $v['publicity'] == 1? '公开' : '不公开',
                'recommend_type' => $v['flag'] & 4 ? 1 : 0,       
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
            
            $tmpData[$k]['op']='';
        }
        
        exit(json_encode(array('rows'=>$tmpData, 'total'=>$total)));
    }
    
    /**
     * 修改推荐状态
     * @param ac_id 活动id
     * @param order 顺序
     */
    public function changeRecommendAction()
    {
        $acId         = $this->request->getPost('id');
        $addrecommend = intval($this->request->getPost('addrecommend', 1)); // 1-加推荐 0-取消推荐
        
        if (is_array($acId) && isset($acId[0])) {
            $acId = $acId[0];
        }
        if (!$acId) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        $acModel = new ActivityModel();
        $acInfo  = $acModel->getActivityInfo($acId);
        if (!$acInfo) {
            Fn::outputToJson(self::ERR_PARAM, '参数错误');
        }
        
        if ($acInfo['flag'] == -1) {
            $acInfo['flag'] = 0;
        }
        if (!$addrecommend && ($acInfo['flag'] & 4)) {
            $flag = $acInfo['flag'] ^ 4;
            $acModel->updateRecommendFlag($acId, $flag);
        } else if ($addrecommend && (!($acInfo['flag'] & 4))) {
            $flag = $acInfo['flag'] | 4;
            $acModel->updateRecommendFlag($acId, $flag);
        }
        
        // 删除首页推荐列表的缓存
        $this->acModel->removeAllMc();
        
        // 添加操作记录
        $this->acAdminModel->addOpRecord($acId, $this->_operatorInfo['name'], $addrecommend == 1 ? '推荐':'取消推荐');
    
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
        
        // 删除首页推荐列表的缓存
        $this->acModel->removeRecommendMc();
        
        // 添加操作记录
        $this->acAdminModel->addOpRecord($acId, $this->_operatorInfo['name'], '更新顺序');
        
        Fn::outputToJson(self::OK, 'OK');
    }
    
    
}