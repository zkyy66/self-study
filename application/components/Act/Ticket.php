<?php
/**
 * ticket票逻辑
 * @author lifuqiang
 *

 * @description
 * @author by Yaoyuan.
 * @version: 2017-03-07
 */
class Act_Ticket {
    
    const REDIS_STRING = 'main';
    const REDIS_PREFIX = 'ZANZAN::Activity:Ticket::';
    
    /**
     * 生成ticket
     * @param unknown $userId
     * @param unknown $shoolId
     * @return boolean|string
     */
    public static function generateTicket($userId, $shoolId,$toonUid=NULL) {
        $ua = Fn::getHttpUserAgent();
        
        //产生ticket
        $ticket = md5($ua . "userId:" . $userId);
        
        $mcKey = self::REDIS_PREFIX . $ticket;
        
        //存储内容
        $content = serialize([
            'userId'    => $userId,
            'schoolId'  => $shoolId,
            'toonUid'   => $toonUid
        ]);
        
        //60分钟有效期
        $ret = RedisClient::instance(self::REDIS_STRING)->setex($mcKey, 3600, $content);
        if (! $ret) {
            return false;
        }
        
        return $ticket;
    }
    
    /**
     * 根据ticket获取内容
     *      若返回的为空数组， 则请求不合法或ticket已失效。
     * @param unknown $ticket
     * @return array
     */
    public static function getContentByTicket($ticket) {
        $ticket = trim($ticket);
        
        if (empty($ticket)) {
            return [];
        }
        
        $mcKey = self::REDIS_PREFIX . $ticket;
        
        $content = RedisClient::instance(self::REDIS_STRING)->get($mcKey);
        
        if (! $content) {
            return [];
        }
        
        $content = unserialize($content);
        
        //验证是否来自合法的票
        $ua      = Fn::getHttpUserAgent();
        if (md5($ua . "userId:" . $content['userId']) != $ticket) {
            //非法用户
            return [];
        }
        
        //延时60分钟
        RedisClient::instance(self::REDIS_STRING)->expireAt($mcKey, time()+3600);
        
        return $content;
    }
}
