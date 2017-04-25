<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2016-10-20
 * @Time: 2016-10-20 10:08
 */
class ToonController extends Controller {
    /**
     * 初始化
     */
    public function init() {
        header("Access-Control-Allow-Origin:*");
        parent::init();
        $this->checkPortalTicket = false;
        
        if ($this->getRequest()->getActionName() == 'get-by-poi') {
            $this->getRequest()->setActionName('getByPoi');
        }
    }

    /**
     * 获取FeedInfo信息
     */
    public function getListFeedInfoAction() {

        $post = json_decode(file_get_contents('php://input'),true);
        if (!is_array($post) || empty($post)) {
             Fn::outputToJson(self::ERR_PARAM,'数据格式错误');
        }
        if (empty($post['feed_id'])) {
            Fn::outputToJson(self::ERR_PARAM,'参数丢失');
        }
        $toonModel = new ToonModel();

        $info = $toonModel->getFeedInfoByRedis($post['feed_id']);
        Fn::outputToJson('0', 'ok', $info);
    }

    /**
     * 根据距离获取附近活动信息
     * @return string
     */
    public function getByPoiAction() {
        $getParams = $this->request->getQuery();
        $type = $getParams['t'];
        $poi  = $getParams['poi'];
        if (empty($getParams['limit'])) {
            $limit = 20;
        } else {
            $limit = $getParams['limit'];
        }

        if ($poi) {
            $poi = json_decode( $poi, true );
        } else {
            return json_encode([]);
        }

       
        $toonObj = new ToonModel();

        $result = $toonObj->getNearInfo($poi);

        if( false == $result  || !isset( $result['results'] ) ) {
            return json_encode([]);
        }
        
        if ($result) {
            $id = [];
            foreach( $result['results'] as $value ) {
                $id[]   = $value['obj']['_id'];
            }
            $time = time();
            $resultList = $toonObj->queryMixData($id, $time, $limit);
            if ($resultList) {
               die($resultList);
            } else {
                die();
            }

        }
    }
}