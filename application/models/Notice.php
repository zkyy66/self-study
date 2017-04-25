<?php
/**
 * @description 通知相关的处理
 * @author liweiwei
 * @version 2016-11-1上午11:55:26
 */

class NoticeModel extends BaseModel
{
    public $_redisString = "main";
    public $mcKey = "ZANZAN::Activity::notice::list";
    public $failedMcKey = "ZANZAN::Activity::notice::failedlist";
    
    /**
     * 获得队列的长度
     */
    public function getLen($mcKey=null)
    {
        if (!$mcKey) {
            $mcKey = $this->mcKey;
        }
        return RedisClient::instance($this->_redisString)->lLen($mcKey);
    }
    
    /**
     * 显示所有内容
     */
    public function showAll($mcKey=null)
    {
        if (!$mcKey) {
            $mcKey = $this->mcKey;
        }
        $len = RedisClient::instance($this->_redisString)->lLen($mcKey);
        return RedisClient::instance($this->_redisString)->lrange($mcKey, 0, $len);
    }
    
    public function delByKey($mcKey)
    {
        return RedisClient::instance($this->_redisString)->del($mcKey);
    }
    
    /**
     * 获得一个消息
     * @param $mcKey 队列名
     * @return array
     */
    public function getOne($mcKey=null)
    {
        if (!$mcKey) {
            $mcKey = $this->mcKey;
        }
        $len = RedisClient::instance($this->_redisString)->lLen($mcKey);
        if (!$len) {
            return array();
        }
        
        $ret = RedisClient::instance($this->_redisString)->lPop($mcKey);
        return json_decode($ret, true);
    }
    
    /**
     * 向队列里面添加新记录
     * @param $infoArr 消息数组
     * @param $mcKey 队列名
     */
    public function addToList($infoArr, $mcKey = null)
    {
        if (!$mcKey) {
            $mcKey = $this->mcKey;
        }
        return RedisClient::instance($this->_redisString)->rPush($mcKey, json_encode($infoArr));
    }
}