<?php

/**
 * 请求信息处理类 Request.php
*/
class Request
{
    /**
     * 按照默认值的类型转换数据类型
     * @param mixed $value
     * @param mixed $default
     * @return mixed
     */
    private static function getValue($value, $default = '')
    {
        if (is_string($default)) {
            return (string)$value;
        }

        if (is_int($default)) {
            return (int)$value;
        }

        if (is_array($default)) {
            return (array)$value;
        }

        if (is_float($default)) {
            return (float)$value;
        }

        return $value;
    }

    /**
     * 判断请求是否是Ajax请求
     * @return bool
     */
    public static function isAjax()
    {
        if ( !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 'XMLHttpRequest' == $_SERVER['HTTP_X_REQUESTED_WITH'] ) {
            return true;
        }
        return false;
    }

    /**
     * 判断请求是否是Get请求
     * @return bool
     */
    public static function isGet()
    {
        return 'GET' == self::getMethod();
    }

    /**
     * 判断请求是否是Post请求
     * @return bool
     */
    public static function isPost()
    {
        return 'POST' == self::getMethod();
    }

    /**
     * 判断请求是否是Put请求
     * @return bool
     */
    public static function isPut()
    {
        return 'PUT' == self::getMethod();
    }

    /**
     * 判断请求是否是Delete请求
     * @return bool
     */
    public static function isDelete()
    {
        return 'DELETE' == self::getMethod();
    }

    /**
     * 获取请求方式
     * @return string
     */
    public static function getMethod()
    {
        return @$_SERVER['REQUEST_METHOD'];
    }

    /**
     * 获取客户端ip
     * @return string
     */
    public static function getClientIp()
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {

            $onlineip = $_SERVER['HTTP_CLIENT_IP'];

        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {

            // 多层代理ip
            $ips = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ips = explode(',', $ips);
            $onlineip = trim($ips[0]);

        } elseif ( isset($_SERVER['HTTP_X_REAL_IP']) ) {

            $onlineip =  $_SERVER['HTTP_X_REAL_IP'];

        } elseif (isset($_SERVER['REMOTE_ADDR'])) {

            $onlineip = $_SERVER['REMOTE_ADDR'];

        } else {

            return '';

        }

        return filter_var($onlineip, FILTER_VALIDATE_IP) !== false ? $onlineip : '';
    }

    /**
     * 获取HTTP_REFERER
     * @return string
     */
    public static function getRf()
    {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    }

    /**
     * 获取HTTP_USER_AGENT
     * @return string
     */
    public static function getUa()
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    }

    /**
     * 先获取$_GET[$key]，不存在再获取$_POST[$key]，值会按照默认值的类型进行类型转换
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getParam($key, $default = '')
    {
        $param = null;

        if (isset($_GET[$key])) {
            $param = $_GET[$key];
        } elseif (isset($_POST[$key])) {
            $param = $_POST[$key];
        } else {
            $postData = json_decode(file_get_contents("php://input"), true);
            if ($postData && isset($postData[$key])) {
                $param = $postData[$key];
            }
        }
        
        // 没有获取到值，则返回默认值
        if ($param === null) {
            return $default;
        }

        return self::getValue($param, $default);
    }

    /**
     * 获取$_GET[$key]，值会按照默认值的类型进行类型转换
     * @param string $key
     * @param mixed $default
     */
    public static function Get($key, $default = '')
    {
        if (isset($_GET[$key])) {
            return self::getValue($_GET[$key], $default);
        }

        return $default;
    }

    /**
     * 获取$_POST[$key]，值会按照默认值的类型进行类型转换
     * @param string $key
     * @param mixed $default
     */
    public static function Post($key, $default = '')
    {
        $postData = json_decode(file_get_contents("php://input"), true);
        if ($postData && isset($postData[$key])) {
            return self::getValue($postData[$key], $default);
        } 
        if (isset($_POST[$key])) {
            return self::getValue($_POST[$key], $default);
        }

        return $default;
    }
}
?>