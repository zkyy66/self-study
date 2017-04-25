<?php
/**
 * @description
 * @author by Yaoyuan.
 * @version: 2017-03-01
 * @Time: 2017-03-01 17:12
 */
class ResponseCode {
    const OK = 0;
    const UNAUTHORIZED_ACCESS= 1001;
    const EXP_PARAM = 1002;
    const USER_MATCH_FAILED = 1003;
    const TOO_MANAY_WORDS = 1004;
    const ERR_DATA_FORMAT = 1005;
    const ILLEGAL_PERMISSION = 1006;
    const FORBIDDEN = 1010;
    const NOT_EXIST = 1020;  //不存在
    
    const ERR_DB_SYS     = 2000;
    const ERR_DB_CONNECT = 2001;
    const ERR_DB_GET_FAILED = 2002;
    const ERR_DB_UPDATE_FAILED = 2003;
    const ERR_DB_SAVE_FAILED = 2004;
}