<?php
class Log {
    
    //日志级别， 从上到下， 由低到高
    const EMERG     = 'EMERG';    //严重错误： 导致系统崩溃无法使用
    const ALERT     = 'ALERT';    //警戒性错误： 必须被立即修改的错误
    const CRIT      = 'CRIT';     //临界值错误： 超过临界值的错误，比如最大是24， 输出25
    const ERR       = 'ERR';      //一般性错误： 一般性错误
    const WARN      = 'WARN';     //警告行错误： 需要发出警告的错误
    const NOTICE    = 'NOTICE';   //通知： 程序可以允许但还不够完美的错误
    const INFO      = 'INFO';     //信息： 程序输出信息
    const DEBUG     = 'DEBUG';    //调试： 调试信息
    
    /**
     * 记录错误日志
     * @param unknown $message
     * @param unknown $level
     * @param string $fileName
     * @return boolean
     */
    public static function write($message, $level = self::ERR, $fileName = '') {
        if (empty($fileName)) {
            $fileName = date('Y_m_d') . '.log';
        }
        
        $logRoot = '';
        
        if (is_object(Yaf_Registry::get('config'))) {
            $logRoot = Yaf_Registry::get('config')->get('site.info.logRoot');
        }
        
        if (! $logRoot) {
            $logRoot = '/home/logs/';
        }
        
        $destination = $logRoot . $fileName;
        $baseDir = dirname($destination);
        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0755, true);
        }
        
        $message = "{$level}: {$message}\r\n";
        
        if (APP_ENV == 'product') {
            return Fn::writeStdOut($destination, $message);
        }
        return error_log($message, 3, $destination);
    }
    
    /**
     * 记录mysql错误日志
     * @param unknown $message
     */
    public static function dbError($message) {
        $date = date('Ymd');
        $fileName = 'db/dbErr_' . $date . '.log';
        return self::write($message, self::EMERG, $fileName);
    }
    
    /**
     * 记录mongo相关的错误日志
     * @param unknown $message
     * @return boolean
     */
    public static function mongoError($message) {
        $date = date('Ymd');
        $fileName = 'db/mongoErr_' . $date . '.log';
        return self::write($message, self::EMERG, $fileName);
    }
    
    /**
     * 记录和toon相关的错误日志
     * @param unknown $message
     */
    public static function toonError($message) {
        $date = date('Ymd');
        $fileName = 'toon/toonErr_' . $date . '.log';
        return self::write($message, self::ALERT, $fileName);
    }
    
}