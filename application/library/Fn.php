<?php
/**
 * 公用函数类
 * @description 包含所有非业务相关的所有公共函数
 * @author zhaowei
 * @version 2016-05-24
 */
class Fn {
    
    const ALARM_SMS     = 1;
    const ALARM_MAIL    = 2;
    const ALARM_LOG     = 3;
    
    /*
     * 根据传入参数获取pdo数据类型
     *
     * @param string $val 传入值
     * @return string 
     */
	public static function select_datatype( $val ) {
	    
		if ( is_string( $val ) )  return PDO::PARAM_STR;
		if ( is_int( $val ) )     return PDO::PARAM_INT;
		if ( is_null( $val ) )    return PDO::PARAM_NULL;
        if ( is_bool( $val ) )    return PDO::PARAM_BOOL;
        return PDO::PARAM_STR;
	}

    /*
     * 显示调试信息
     *
     * @param string $str 传入值 
     */
    public static function writeLog( $str , $fileName = '' ) {
        if (! $fileName) {
            $fileName =  'error.log';
        }

        $str = '['.date("Y-m-d H:i:s").'] '.$str;
        Log::write($str, Log::INFO, $fileName);
    }
    
    public static function writeStdOut( $file, $content ) {
        $fh = fopen('php://stdout', 'w');
        fwrite($fh, $content."\n");
        fclose($fh);
    }
    /**
     * 通过CURL库进POST数据提交
     *
     * @param string $postUrl  url address
     * @param array $data  post data
     * @param int $timeout connect time out
     * @param bool $debug 打开 header 数据
     * @return string
     */
    public static function curlPost( $postUrl, $data = '', $timeout = 30, $header = [], $debug = false ) {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $postUrl );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HEADER, $debug );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $header );
        curl_setopt( $ch, CURLINFO_HEADER_OUT, $debug );
        curl_setopt( $ch, CURLOPT_TIMEOUT, $timeout );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );

        $result = curl_exec( $ch );
        $info   = curl_getinfo( $ch );   //用于调试信息
        curl_close( $ch );

        if ( $result === false ) {
            self::writeLog( json_encode( $info ) );
            return false;
        }
        return trim( $result );
    }

    /**
     * 获取url返回值，curl方法
     */
    public static function curlGet( $url, $timeout = 1 ) {
        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, $timeout );
        
        $ret  = curl_exec( $ch );
        $info = curl_getinfo($ch);   //用于调试信息
        curl_close($ch);
        
        if ( $ret === false ) {
            self::writeLog( json_encode( $info ) );
            return false;
        }
        return trim( $ret );
    }
  
    /**
     * 二维数组按指定的键值排序
     */
    public static function array_sort( $array, $keys, $type='asc' ) {
        if( !isset( $array ) || !is_array( $array ) || empty( $array ) ) return '';
        if( !isset( $keys ) || trim( $keys ) == '' ) return '';
        if( !isset( $type ) || $type == '' || !in_array( strtolower( $type ), array( 'asc', 'desc' ) ) ) return '';
        
        $keysvalue  = [];
        foreach( $array as $key => $val ) {
            $val[ $keys ]   = str_replace( '-', '', $val[ $keys ] );
            $val[ $keys ]   = str_replace( ' ', '', $val[ $keys ] );
            $val[ $keys ]   = str_replace( ':', '', $val[ $keys ] );
            $keysvalue[]    = $val[ $keys ];
        }
        
        asort( $keysvalue ); //key值排序
        reset( $keysvalue ); //指针重新指向数组第一个
        foreach( $keysvalue as $key => $vals ) 
            $keysort[] = $key;
        
        $keysvalue  = [];
        $count      = count( $keysort );
        if( strtolower( $type ) != 'asc' ) {
            for( $i = $count - 1; $i >= 0; $i-- ) 
                $keysvalue[] = $array[ $keysort[ $i ] ];
        }else{
            for( $i = 0; $i < $count; $i++ )
                $keysvalue[] = $array[ $keysort[ $i ] ];
        }
        return $keysvalue;
    }
    
    /**
    * 从结果集中总取出last names列，用相应的id作为键值
    * 原数据:
    * $records = array(
        array(
            'id' => 2135,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ),
      );
    * array_column($records, 'last_name', 'id');
    * 结果:
    * Array
        (
            [2135] => Doe
            [3245] => Smith
            [5342] => Jones
            [5623] => Doe
        )
     */
    public static function array_column($input, $columnKey, $indexKey = NULL)
    {
        $columnKeyIsNumber = (is_numeric($columnKey)) ? TRUE : FALSE;
        $indexKeyIsNull = (is_null($indexKey)) ? TRUE : FALSE;
        $indexKeyIsNumber = (is_numeric($indexKey)) ? TRUE : FALSE;
        $result = array();

        foreach ((array)$input AS $key => $row)
        {
            if ($columnKeyIsNumber)
            {
                $tmp = array_slice($row, $columnKey, 1);
                $tmp = (is_array($tmp) && !empty($tmp)) ? current($tmp) : NULL;
            }
            else
            {
                $tmp = isset($row[$columnKey]) ? $row[$columnKey] : NULL;
            }
            if ( ! $indexKeyIsNull)
            {
                if ($indexKeyIsNumber)
                {
                    $key = array_slice($row, $indexKey, 1);
                    $key = (is_array($key) && ! empty($key)) ? current($key) : NULL;
                    $key = is_null($key) ? 0 : $key;
                }
                else
                {
                    $key = isset($row[$indexKey]) ? $row[$indexKey] : 0;
                }
            }

            $result[$key] = $tmp;
        }

        return $result;
    }
    
    /**
     * 内容审核接口
     * @params $content String
     * @params $projectName String 
     */
    public static function audiing_logs( $content, $projectName ) {
        
        $log        = '/home/logs/' . $projectName . '-auditing.log';
        $countFile  = '/home/logs/count';
        $fileSize   = 0;
        $maxSize    = 1024 * 1024 * 10;
        
        if( file_exists( $log ) )  $fileSize    = filesize( $log );
        if( $fileSize >= $maxSize ) {
            if( file_exists('/home/logs/count') ) {
                $count  = file_get_contents( $countFile );
                if( $count != false )
                    $newFile    = $log . '.' . $count;
            }
            else {
                $newFile    = $log . '.0';
            } 
            file_put_contents( $countFile, intval( $count ) + 1 );
            rename( $log, $newFile );
        }
        file_put_contents( $log, $content . "\n\n", FILE_APPEND );
    }

    /**
     * @param string $prifix
     * @return string
     */
    public static function getUuid( $prifix = '' ) {
        $chars  = md5( uniqid( mt_rand(), true ) );
        $uuid   = substr( $chars, 0, 8 ) . '-';
        $uuid   .= substr( $chars, 8, 4 ) . '-';
        $uuid   .= substr( $chars, 12, 4 ) . '-';
        $uuid   .= substr( $chars, 16, 4 ) . '-';
        $uuid   .= substr( $chars, 20, 12 );
        return $prifix . $uuid;
    }
    
    /**
     * 根据经纬度计算两地距离， 返回单位为米
     * @param unknown $lat1
     * @param unknown $lng1
     * @param unknown $lat2
     * @param unknown $lng2
     * @return number
     */
    public static function getDistance($lat1, $lng1, $lat2, $lng2) {
        //地球半径
        $R = 6378137;
        //将角度转为狐度
        $radLat1 = deg2rad($lat1);
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);
        //结果
        $s = acos(cos($radLat1)*cos($radLat2)*cos($radLng1-$radLng2)+sin($radLat1)*sin($radLat2))*$R;
        //精度
        $s = round($s* 10000)/10000;
        return  round($s);
    }
    
    /**
     * 输出json数据
     * @param unknown $code
     * @param unknown $message
     * @param string $data
     * @param string $noCache
     */
    public static function outputToJson( $code, $message, $data = null, $noCache = true ) {
        header("Content-type: application/json; charset=utf-8");
        
        if ( $noCache ) {
            header("Cache-Control: no-cache");
        }
        
        $msg = array(
            'meta' => array(
                'code' => $code,
                'message'  => $message,
                'timestamp' => self::getMillisecond(),
            ),
            'data' => $data
        );
    
        $msg = json_encode($msg);
        
        header('Content-Length:' . strlen($msg));
        
        echo $msg;
        exit(0);        
    }
    
    /**
     * 计算字符长度，包括中文
     *
     * @param unknown_type $String
     * @return unknown
     */
    public static function getStrLen($str)
    {
        $I = 0;
        $StringLast = array();
        $Length = strlen($str);
        while ( $I < $Length ) {
            $StringTMP = substr($str, $I, 1);
            if (ord($StringTMP) >= 224) {
                if ($I + 3 > $Length) {
                    break;
                }
                $StringTMP = substr($str, $I, 3);
                $I = $I + 3;
            }
            elseif (ord($StringTMP) >= 192) {
                if ($I + 2 > $Length) {
                    break;
                }
                $StringTMP = substr($str, $I, 2);
                $I = $I + 2;
            }
            else {
                $I = $I + 1;
            }
            $StringLast[] = $StringTMP;
        }
    
        return count($StringLast);
    }
    
    /**
     * 截取字符串
     * @param unknown $string
     * @param unknown $length
     * @param string $append
     * @return unknown|string
     */
    public static function subStrForAllEnc($string, $length, $append = false)
    {
        if (strlen($string) <= $length) {
            return $string;
        }
        else {
            $I = 0;
            $J = 0;
            $strLen = strlen($string);
            while ( $I < $length && $J < $strLen ) {
                $StringTMP = substr($string, $J, 1);
                if (ord($StringTMP) >= 224) {
                    if ($J + 3 > $strLen) {
                        break;
                    }
                    $StringTMP = substr($string, $J, 3);
                    $J = $J + 3;
                }
                elseif (ord($StringTMP) >= 192) {
                    if ($J + 2 > $strLen) {
                        break;
                    }
                    $StringTMP = substr($string, $J, 2);
                    $J = $J + 2;
                }
                else {
                    $J = $J + 1;
                }
                $StringLast[] = $StringTMP;
                $I++;
            }
            $StringLast = implode("", $StringLast);
            if ($StringLast != $string && $append) {
                $StringLast .= "...";
            }
            return $StringLast;
        }
    }
    

    /**
     * 验证必填参数
     * @param array $params 参数一维数组
     * @param boolen $result
     */
    public static function checkNecessaryParams($params){
        $result = true;

        foreach ($params as $k => $v) {
            if(empty($v)){
                $result = false;
                self::writeLog(date("Y-m-d H:i:s")."\n".$k."必填\n");
                break;
            }
        }

        return $result;
    }
    
    /**
     * 获取13位时间戳
     * @return string
     */
    public static function getMillisecond() {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }
    
    /**
     * 报警函数
     * @param unknown $level    报警级别
     * @param unknown $title    报警title
     * @param unknown $msg      报警详情
     */
    public static function alarm( $level, $title, $msg ) {
    
    }


    /**
     * 日期统一格式化
     * @param $tm
     * @return false|string
     */
    public static function dateformat( $tm ) {
        return date( 'Y-m-d H:i', $tm );
    }

    /**
     * 调试输出
     */
    public static function p($arr) {
        echo '<pre>';
        print_r($arr);
        die();
    }

    /**
     * @param $string
     * @param int $force
     * @return array|mixed|string
     */
    public static function daddcslashes($string, $force = 1) {
        if (is_array ( $string )) {
            $keys = array_keys ( $string );
            foreach ( $keys as $key ) {
                $val = $string [$key];
                unset ( $string [$key] );
                $key = addcslashes ( $key, "\n\r\\'\"\032" );
                $key = str_replace ( "_", "\_", $key ); // 转义掉”_”
                $key = str_replace ( "%", "\%", $key ); // 转义掉”%”
                $string [$key] = daddcslashes ( $val, $force );
            }
        } else {
            $string = addcslashes ( $string, "\n\r\\'\"\032" );
            $string = str_replace ( "_", "\_", $string ); // 转义掉”_”
            $string = str_replace ( "%", "\%", $string ); // 转义掉”%”
        }
        return $string;
    }
    
    /**
     * 验证手机号格式
     */
    public static function checkPhone($phonenumber)
    {
        if(preg_match("/^1[34578]{1}\d{9}$/", $phonenumber)){
            return true;
        }
        return false;
    }

    /**
     * 验证身份证长度
     * @param $card_id
     * @return bool
     */
    public static function checkCard($card_id) {
        if (preg_match("/^(\d{18,18}|\d{15,15}|\d{17,17}X)$/",strtoupper($card_id))) {
            return true;
        }

        return false;
    }

    /**
     * 验证邮箱格式
     * @param $email
     * @return bool
     */
    public static function checkEmail($email) {
        if (preg_match("/^([0-9A-Za-z\\-_\\.]+)@([0-9a-z]+\\.[a-z]{2,3}(\\.[a-z]{2})?)$/i",$email)) {
            return true;
        }
        return false;
    }
    /**
     * 发邮件
     * @param array $mail_from 邮件发送方信息 array('smtp_host' =>'smtp.xx.com','email'=>'xxx@163.com','name'=>'','password'=>'xx')
     * @param string|array $mail_to 接收邮件方  例如：xxx@163.com 、 array('xxx@163.com', 'xxx@qq.com')
     * @param string $title 邮件主题
     * @param string $content 邮件内容，可以保护html代码
     * @param string $attachment 附件路径
     * @param string $content_type 邮件类型 包括：TEXT|HTML
     * @param string $charset 编码
     * @author liweiwei
     */
    public static function sendMail($mailTo, $title, $content, $attachment = null, $content_type='TEXT', $charset='utf8')
    {
        require_once APP_PATH . '/application/library/PHPMailer/PHPMailerAutoload.php';

        $content_type = strtoupper($content_type);

        $mail = new PHPMailer(); // 实例化phpmailer

        $mail->IsSMTP(); // 设置发送邮件的协议：SMTP

        $mail->Host         = '172.28.2.240'; // 发送邮件的服务器  例如：smtp.exmail.qq.com、smtp.sina.cn、smtp.163.com
        $mail->Port = 25;
        $mail->SMTPAuth     = true; // 打开SMTP
        $mail->Username     = 'zanzanapp@syswin.com'; // SMTP账户---公司RTX账号
        $mail->Password     = 'syswin#123'; // SMTP密码
        $mail->SMTPSecure   = 'ssl';

        if ($content_type == 'HTML') {
            $mail->IsHTML(true);
        } //是否使用HTML格式

        $mail->From     = 'zanzanapp@syswin.com';
        $mail->FromName = '赞赞官方';

        // 添加多个发送人
        if (is_array($mailTo)) {
            foreach ($mailTo as $val) {
                $mail->AddAddress($val);
            }
        } else {
            $mail->AddAddress($mailTo);
        }

        if ($attachment) {
            $mail->AddAttachment($attachment); // 设置附件，服务器路径
        }

        //设置字符集编码
        if ($charset != 'utf8') {
            $mail->CharSet = $charset;
        } else {
            $mail->CharSet = "UTF-8";
        }

        $mail->Subject = "=?UTF-8?B?" . base64_encode($title) . "?=";

        //邮件内容（可以是HTML邮件）
        $mail->Body = $content;

        if (! $mail->send()) {
            Fn::writeLog('发送邮件失败: '.$mail->ErrorInfo);
            return false;
        }

        return true;
    }
    
    /**
     * 活动专用发通知
     * @author liweiwei
     */
    public static function sendNoticeForActivity($fromFeedId, $toFeedId, $toUid, $contentArr)
    {
        $imContent = array(
                'catalogId' => 138,
                'catalog'   => "社交娱乐", // 位于通知列表页面标题
                'subCatalog' => "活动通知", // 打开通知后，顶部展示
                "headFeed"   => $fromFeedId,
                'finishFlag' => 0,
                'summary' => '',// // 位于通知列表页面标题下面的一行小字，点开通知后也有这个展示
                'actionType' => 1,
                'headFlag' => 1,
                'content'       => json_encode($contentArr),
                "subCatalogId" =>  0,
        );
        if (!isset($contentArr['url'])) {
            $imContent['actionType'] = 0;
        }
        if (isset($contentArr['needHeadFlag']) && $contentArr['needHeadFlag'] == 0) {
            $imContent['headFlag'] = 0;
        }
        
        return Toon::sendImMsg('portal', $fromFeedId, $toFeedId, $toUid, $imContent, Toon::IM_MSG_TYPE_NOTICE, $errMsg);
    }
    
    /**
     * 根据总页数，每页数量，当前页，组织limit语句
     * @param int $total
     * @param int $perpage
     * @param int $page
     * return string 
     * @author liweiwei
     */
    public static function getLimitStrAction($total, $perpage, $page)
    {
        if (!$total || !$perpage) {
            return '';
        }
            
        $pages = ceil($total/$perpage);
        if ($page > $pages) {
            $page = $pages;
        }
        if ($page < 1) {
            $page = 1;
        }
        return "LIMIT ".($page-1)*$perpage.", $perpage";
    }
    
    /**
     * 用户-redis
     * @param $key
     * @param $redisSting
     * @param $mark
     * @param null $indexTime
     * @param array|NULL $data
     * @return bool|mixed
     */
    public static function getUserInfoByRedis($key,$redisSting,$mark,$indexTime=NULL, array $data=NULL) {
        if (1 == $mark) {
            $result = RedisClient::instance($redisSting)->setex($key, $indexTime, serialize($data));
            
        } else if (2 == $mark) {
            $list = RedisClient::instance($redisSting)->get($key);
            if (false === $list) {
                return false;
            }
            $result = unserialize($list);
        } else if (3 == $mark) {
            $result = RedisClient::instance($redisSting)->expire($key,$indexTime);
        }
        return $result;
    }
    /**
     * @param $seesion_id
     * @return mixed
     */
    public static function getSessionByUser($seesion_id) {
        session_id($seesion_id);
        session_start();
        return $_SESSION;
    }
    
    /**
     * 根据session_id获取session中存取的uid
     * @param $seesion_id
     * @return int 用户id
     * @author liweiwei
     */
    public static function getUidBySessionId($sessionId)
    {
        $sessionInfo = self::getSessionByUser($sessionId); // array(feed_id, title, subtitle, avatarId, user_id, session_id)
        return isset($sessionInfo['user_id']) ? $sessionInfo['user_id'] : 0;
    }
    
    /**
     *  发通知的链接需要带上code信息，用此生成
     * @param array $data: code中要包含的内容
     * @return string
     * @author liweiwei
     */
    public static function generateCode($data, $isgroup)
    {
        if ($isgroup) {
            $appId = Toon::getBaseToonParams('group')['authAppId'];
            $appType = 'group';
        } else {
            $appId = Toon::getBaseToonParams('portal')['authAppId'];
            $appType = 'portal';
        }
        $ret = Toon::generateCypherText($appType, $appId, $data, $errMsg);
        return strval($ret);
    }
    
    /**
     * 从配置中获取静态页面的地址
     * @return string
     * @author liweiwei
     */
    public static function getHtmlUrl()
    {
        return Yaf_Registry::get('config')->get('site.info.staticurl');
    }

    /**
     * 从配置文件中获取Server地址
     * @return mixed
     */
    public static function getServerUrl() {
        return Yaf_Registry::get('config')->get('site.info.url');
    }
    /**
     * 生成通知的链接
     * @param int $entry 入口，3-活动详情 4-审核列表页面
     * @param int $acId 活动id
     * @param array $codeData 要加密成code的数组信息。
     * @param string $feedType c-代表个人活动 g-代表群组活动
     * @return string
     * @author liweiwei
     */
    public static function generatePageUrl($entry, $acId=null, $codeData=null, $feedType=null)
    {
        $url = Fn::getHtmlUrl();
        
        $paramArr = array();
        if ($entry) {
            $paramArr[] = "entry={$entry}";
        }
        if ($acId) {
            $paramArr[] = "ac_id={$acId}";
        }
        if ($feedType) {
            $paramArr[] = "feed_type={$feedType}";
        }
        if ($codeData) {
            // 交换visitor 和 owner
            $paramArr[] = "code=".Fn::generateCode($codeData, $feedType=='g');
        }
        if (count($paramArr) > 0) {
            return $url."?".implode('&', $paramArr);
        } else {
            return $url;
        }
    }
    
    /**
     * @param $string
     * @return string
     */
    public static function strToHex($string)//字符串转十六进制
    {
        $hex="";
        for($i=0;$i<strlen($string);$i++)
            $hex.=dechex(ord($string[$i]));
            $hex=strtoupper($hex);
            return $hex;
    }
    /**
    public static function hexToStr($hex)//十六进制转字符串
    {
        $string="";
        for($i=0;$i<strlen($hex)-1;$i+=2)
            $string.=chr(hexdec($hex[$i].$hex[$i+1]));
        return  $string;
    }
    
    /**
     * 获取userAgent
     * @return Ambigous <string, unknown>
     */
    public static function getHttpUserAgent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }
    
    /**
     * 产生随机字串，可用来自动生成密码
     * 默认长度6位 字母和数字混合 支持中文
     * @param string $len 长度
     * @param string $type 字串类型
     * 0 字母 1 数字 其它 混合
     * @param string $addChars 额外字符
     * @return string
     */
    public static function randString($len = 6, $type = '', $addChars = '')
    {
        $str = '';
        switch ($type) {
            case 0:
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz' . $addChars;
                break;
            case 1:
                $chars = str_repeat('0123456789', 3);
                break;
            case 2:
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ' . $addChars;
                break;
            case 3:
                $chars = 'abcdefghijklmnopqrstuvwxyz' . $addChars;
                break;
            case 4:
                $chars = "们以我到他会作时要动国产的一是工就年阶义发成部民可出能方进在了不和有大这主中人上为来分生对于学下级地个用同行面说种过命度革而多子后自社加小机也经力线本电高量长党得实家定深法表着水理化争现所二起政三好十战无农使性前等反体合斗路图把结第里正新开论之物从当两些还天资事队批点育重其思与间内去因件日利相由压员气业代全组数果期导平各基或月毛然如应形想制心样干都向变关问比展那它最及外没看治提五解系林者米群头意只明四道马认次文通但条较克又公孔领军流入接席位情运器并飞原油放立题质指建区验活众很教决特此常石强极土少已根共直团统式转别造切九你取西持总料连任志观调七么山程百报更见必真保热委手改管处己将修支识病象几先老光专什六型具示复安带每东增则完风回南广劳轮科北打积车计给节做务被整联步类集号列温装即毫知轴研单色坚据速防史拉世设达尔场织历花受求传口断况采精金界品判参层止边清至万确究书术状厂须离再目海交权且儿青才证低越际八试规斯近注办布门铁需走议县兵固除般引齿千胜细影济白格效置推空配刀叶率述今选养德话查差半敌始片施响收华觉备名红续均药标记难存测士身紧液派准斤角降维板许破述技消底床田势端感往神便贺村构照容非搞亚磨族火段算适讲按值美态黄易彪服早班麦削信排台声该击素张密害侯草何树肥继右属市严径螺检左页抗苏显苦英快称坏移约巴材省黑武培著河帝仅针怎植京助升王眼她抓含苗副杂普谈围食射源例致酸旧却充足短划剂宣环落首尺波承粉践府鱼随考刻靠够满夫失包住促枝局菌杆周护岩师举曲春元超负砂封换太模贫减阳扬江析亩木言球朝医校古呢稻宋听唯输滑站另卫字鼓刚写刘微略范供阿块某功套友限项余倒卷创律雨让骨远帮初皮播优占死毒圈伟季训控激找叫云互跟裂粮粒母练塞钢顶策双留误础吸阻故寸盾晚丝女散焊功株亲院冷彻弹错散商视艺灭版烈零室轻血倍缺厘泵察绝富城冲喷壤简否柱李望盘磁雄似困巩益洲脱投送奴侧润盖挥距触星松送获兴独官混纪依未突架宽冬章湿偏纹吃执阀矿寨责熟稳夺硬价努翻奇甲预职评读背协损棉侵灰虽矛厚罗泥辟告卵箱掌氧恩爱停曾溶营终纲孟钱待尽俄缩沙退陈讨奋械载胞幼哪剥迫旋征槽倒握担仍呀鲜吧卡粗介钻逐弱脚怕盐末阴丰雾冠丙街莱贝辐肠付吉渗瑞惊顿挤秒悬姆烂森糖圣凹陶词迟蚕亿矩康遵牧遭幅园腔订香肉弟屋敏恢忘编印蜂急拿扩伤飞露核缘游振操央伍域甚迅辉异序免纸夜乡久隶缸夹念兰映沟乙吗儒杀汽磷艰晶插埃燃欢铁补咱芽永瓦倾阵碳演威附牙芽永瓦斜灌欧献顺猪洋腐请透司危括脉宜笑若尾束壮暴企菜穗楚汉愈绿拖牛份染既秋遍锻玉夏疗尖殖井费州访吹荣铜沿替滚客召旱悟刺脑措贯藏敢令隙炉壳硫煤迎铸粘探临薄旬善福纵择礼愿伏残雷延烟句纯渐耕跑泽慢栽鲁赤繁境潮横掉锥希池败船假亮谓托伙哲怀割摆贡呈劲财仪沉炼麻罪祖息车穿货销齐鼠抽画饲龙库守筑房歌寒喜哥洗蚀废纳腹乎录镜妇恶脂庄擦险赞钟摇典柄辩竹谷卖乱虚桥奥伯赶垂途额壁网截野遗静谋弄挂课镇妄盛耐援扎虑键归符庆聚绕摩忙舞遇索顾胶羊湖钉仁音迹碎伸灯避泛亡答勇频皇柳哈揭甘诺概宪浓岛袭谁洪谢炮浇斑讯懂灵蛋闭孩释乳巨徒私银伊景坦累匀霉杜乐勒隔弯绩招绍胡呼痛峰零柴簧午跳居尚丁秦稍追梁折耗碱殊岗挖氏刃剧堆赫荷胸衡勤膜篇登驻案刊秧缓凸役剪川雪链渔啦脸户洛孢勃盟买杨宗焦赛旗滤硅炭股坐蒸凝竟陷枪黎救冒暗洞犯筒您宋弧爆谬涂味津臂障褐陆啊健尊豆拔莫抵桑坡缝警挑污冰柬嘴啥饭塑寄赵喊垫丹渡耳刨虎笔稀昆浪萨茶滴浅拥穴覆伦娘吨浸袖珠雌妈紫戏塔锤震岁貌洁剖牢锋疑霸闪埔猛诉刷狠忽灾闹乔唐漏闻沈熔氯荒茎男凡抢像浆旁玻亦忠唱蒙予纷捕锁尤乘乌智淡允叛畜俘摸锈扫毕璃宝芯爷鉴秘净蒋钙肩腾枯抛轨堂拌爸循诱祝励肯酒绳穷塘燥泡袋朗喂铝软渠颗惯贸粪综墙趋彼届墨碍启逆卸航衣孙龄岭骗休借" . $addChars;
                break;
            default:
                // 默认去掉了容易混淆的字符oOLl和数字01，要添加请使用addChars参数
                $chars = 'ABCDEFGHIJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789' . $addChars;
                break;
        }
        if ($len > 10) {
            //位数过长重复字符串一定次数
            $chars = 1 == $type ? str_repeat($chars, $len) : str_repeat($chars, 5);
        }
        if (4 != $type) {
            $chars = str_shuffle($chars);
            $str   = substr($chars, 0, $len);
        } else {
            // 中文随机字
            for ($i = 0; $i < $len; $i++) {
                $str .= self::msubstr($chars, floor(mt_rand(0, mb_strlen($chars, 'utf-8') - 1)), 1, 'utf-8', false);
            }
        }
        return $str;
    }

    /**
     * 处理emoji表情转化
     * @param $str
     * @return string
     */
    public static function emojitostr($str) {
        $strEncode= '';
        $length = mb_strlen($str,'utf-8');
        for($i = 0;$i < $length;$i++) {
            $_tmpStr = mb_substr($str,$i,1,'utf-8');
            if (strlen($_tmpStr) >= 4) {
                $strEncode .= '[[EMOJI]]';
            } else {
                $strEncode .= $_tmpStr;
            }
        }
        return $strEncode;
    }
    /**
     * 
     * @param 过滤字符串 $str
     */
    public static function filterString($str)
    {
        if (!$str) {
            return '';
        }
        // PHP 5.4 之前 PHP 指令 magic_quotes_gpc 默认是 on， 实际上所有的 GET、POST 和 COOKIE 数据都用被 addslashes() 了。
        // 不要对已经被 magic_quotes_gpc 转义过的字符串使用 addslashes()，因为这样会导致双层转义。
        if(PHP_VERSION >= 6 || !get_magic_quotes_gpc()) {
            return addslashes($str);
        }
    
        return $str;
//         return htmlspecialchars($str, ENT_QUOTES);
//         return @mysql_escape_string($str);
    }
    
    /**
     * @param $string
     * @return mixed
     */
    public static function nl2br($string)
    {
        return str_replace(array("\r\n", "\r", "\n"), "<br/>", $string);
    }
    
    /**
     * @param $string
     * @return mixed
     */
    public static function br2nl($string){
        return preg_replace('/<br\\s*?\/??>/i', chr(13), $string);
    }
    
    /**
     * 获取评论点赞key和密钥
     * @return mixed
     */
    public static function getCommentConfig() {
        $commentConfigs = Yaf_Registry::get('config')->get('site.info.comment')->toArray();
        if (!$commentConfigs) {
            return array();
        }
        return $commentConfigs;
    }
    
    /**
     * 子应用配置
     * @return array
     */
    public static function getAppChildConfig() {
        $appConfigs = Yaf_Registry::get('config')->get('site.info.signature');
        if (!$appConfigs) {
            Fn::writeLog('Fn/getAppChildConfig:获取子应用配置失败');
            return array();
        }
        
        $appConfigs = $appConfigs->get('params')->toArray();
        if (!$appConfigs) {
            Fn::writeLog('Fn/getAppChildConfig:获取子应用配置失败');
            return array();
        }
        return $appConfigs;
    }
    
    /**
     * 子应用签名
     * @param $appType
     * @param $param
     * @return array|string
     */
    public static function generateSignature($appType,$param) {
        
        $prefix = 'site.info.' . $appType;
        
        if (! $toonConfig = Yaf_Registry::get('config')->get($prefix)) {
            return array();
        }
        
        $authConfig = $toonConfig->get('params')->toArray();
        
        $appSecret = $authConfig['appSecret'];
        
        unset($authConfig['appSecret'],$authConfig['feedApiUrl']);
        
        ksort($param);
        $combString = '';
        
        foreach ($param as $key => $val) {
            $combString .= $key.$val;
        }
//        echo $appSecret.$combString.$appSecret;die;
        $authKey = strtoupper( md5( $appSecret.$combString.$appSecret ) );
        
        return $authKey;
    }
    
    /**
     * 晒签名
     * @param $appType
     * @param $param
     * @return array|string
     */
    public static function generateShaiSignature($appType,$param) {
        
        if (! $toonConfig = Yaf_Registry::get('config')->get($appType)) {
            return array();
        }
        $authConfig = $toonConfig->toArray();
        
        $appSecret = $authConfig['appSecret'];
        
        unset($authConfig['appSecret'],$authConfig['shaiUrl']);
        ksort($param);
        $combString = '';
        
        foreach ($param as $key => $val) {
            $combString .= $key.$val;
        }
        
        $authKey = strtoupper( md5( $appSecret.$combString.$appSecret ) );
        
        return $authKey;
    }
    /**
     * 对多位数组进行排序
     * @param $multi_array 数组
     * @param $sort_key需要传入的键名
     * @param $sort排序类型
     */
    public static function multiArraySort($multi_array, $sort_key, $sort = SORT_DESC) {
        if (is_array($multi_array)) {
            foreach ($multi_array as $row_array) {
                if (is_array($row_array)) {
                    $key_array[] = $row_array[$sort_key];
                } else {
                    return FALSE;
                }
            }
        } else {
            return FALSE;
        }
        array_multisort($key_array, $sort, $multi_array);
        return $multi_array;
    }
}