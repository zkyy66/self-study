<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2016-12-27
 * @Time: 2016-12-27 12:21
 */
class ActivitymanagementController extends AdminBaseController {
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
     * 后台审核回复语列表
     */
    public function checkListAction() {
        $getParam = $this->request->getQuery();
        $page = intval($getParam['page']);
        $row = intval($getParam['rows']);
        // 获取符合条件的总个数，用于计算分页
        $total = $this->acAdminModel->getContentNum();
        if (!$total) {
            exit(json_encode(array('rows'=>array(), 'total'=>0)));
        }
        
        // 根据分页组织limit语句
        $limit = Fn::getLimitStrAction($total, $row, $page);
        $list = $this->acAdminModel->getContentList($limit,'');
        foreach ($list as $key => $val) {
            $list[$key]['create_time'] = date('Y-m-d H:i:s',$val['create_time']);
            if ($val['update_time']) {
                $list[$key]['update_time'] = date('Y-m-d H:i:s',$val['update_time']);
            }
            
        }
        exit(json_encode(array('rows'=>$list, 'total'=>$total)));
        
    }
    
    
    
    /**
     * 新增活动回复术语
     */
    public function addCheckContentAction() {
        $post = $this->request->getPost();
        strlen($post['content']) > 60 && exit(json_encode(-1));
        $result = $this->acAdminModel->addCheckContent($post);
        if ($result) {
            exit(json_encode($result));
        } else {
            exit(json_encode([]));
        }
    }
    
    /**
     * 编辑
     */
    public function editContentAction() {
        $post = $this->request->getPost();
        strlen($post['content']) > 60 && exit(json_encode(-1));
        if (2 == $post['mark']) {
            $result = $this->acAdminModel->editContent($post['id'],$post['mark'],'');
        } else if (1 == $post['mark']) {
            $result = $this->acAdminModel->editContent($post['id'],$post['mark'],$post['content']);
        }
        if ($result) {
            exit(json_encode($result));
        } else {
            exit(json_encode([]));
        }
    }
    
    
    /**
     * 获取所有术语
     */
    public function getContentListAction() {
        $list = $this->acAdminModel->getContentList('','');
        
        exit(json_encode($list));
    }
    /**
     * 活动审核
     * 通过/不通过/不处理
     */
    public function checkStatusAction() {
        $post = $this->request->getPost();
        empty($post['id']) && Fn::outputToJson(self::ERR_PARAM, '缺少必要参数');
        if (2 == $post['mark']) {
            if (!is_numeric($post['cid'])) {
                exit(json_encode(-1));
            }
            $mark = $post['mark'];
        } else {
            $mark = $post['mark'];
        }
        $detailInfo = $this->acModel->getActivityInfo(intval($post['id']));
        empty($detailInfo) && Fn::outputToJson(self::ERR_PARAM, '该活动存在异常');
        $result = $this->acAdminModel->checkStatus($post['id'],$mark,$post['cid'],$detailInfo['flag']);
        
        // 删除首页推荐列表的缓存
        $this->acModel->removeAllMc();
        //删除分类列表缓存
        $this->acModel->removeCategoryMc();
        // 添加操作记录
        $this->acAdminModel->addOpRecord($post['id'], $this->_operatorInfo['name'], '审核操作');
        exit(json_encode($result));
    }
}