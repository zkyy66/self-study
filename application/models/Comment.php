<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2017-03-01
 * @Time: 2017-03-01 18:42
 */
class CommentModel extends BaseModel {
    public $_table      = 'ac_comment';
    
    public function addComment ($post) {
        $data = array(
            'fid' => $post['toId'] ? $post['toId'] : 0,
            'ac_id' => $post['ac_id'],
            'userid' => $post['userId'],
//            'feed_id' => $post['feed_id'],
            'create_time' => time()
        );
        try {
            return $this->add($data);
        } catch (Exception $e) {
            Fn::writeLog('CommentModel/addComment:'.$e->getMessage());
            return false;
        }
        
    }
}