<?php 
 /**
 * Redis  封装类
 * @description Redis 操作类
 * @author zhaowei
 * @version 2016-05-24
 */
class RedisClient{

    /*单例容器*/
    private static $_redisPool  = array();

    /**
     * (单例模式)
     * @return object
     */
    public static function instance( $redisString ) {
        
        $redisConfig = Yaf_Registry::get('config')->get('redis.'.$redisString);
        if (! $redisConfig) {
            Fn::writeLog("没有redis：{$redisString}实例配置"); 
            throw new RedisException("没有redis：{$redisString}实例配置");
        }
        
        if(! isset(self::$_redisPool[$redisString])) {
            try {                
                $redisObj = new Redis();
                $redisObj->connect($redisConfig['host'], $redisConfig['port']);
                $redisObj->auth($redisConfig['password']);
                self::$_redisPool[$redisString] = $redisObj;
                return $redisObj;
                
            } catch (RedisException $e) {                
                Fn::writeLog($e->getMessage()); 
            }
        } else {
            return self::$_redisPool[$redisString];
        }
        
    }
    
    
    
    /**
     * 防止new
     */
    private function __clone() {}
    /**
     * 禁止初始化
     */
    private function __construct() {}  
} 

