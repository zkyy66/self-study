<?php
/**
 * PDO数据库驱动
 * @author 李福强
 *
 */
class Db_Pdo extends Db {
    
    protected $PDOStatement = null;
    private   $table        = '';
    
    /**
     * Db_Pdo构造函数
     * @param unknown $config
     */
    public function __construct( $config = array() ) {
        
        $this->config = $config;
    }
    
    /**
     * 链接数据库
     * @param string $config
     * @param number $linkNum
     */
    public function connect($config = '', $linkNum = 0) {
        if (! isset($this->linkID[$linkNum])) {
            if (empty($config)) {
                $config = $this->config;
            }
            
            $dsn = 'mysql:host='.$config['host'].':'.$config['port'].';dbname='.$config['dbname'];
            try {
                $this->linkID[$linkNum] = new PDO($dsn, $config['username'], $config['password'], array(
                   PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}" 
                ));
            } catch (PDOException $e) {
                Db::Error($e->getMessage());
            }
        }
        
        return $this->linkID[$linkNum];
    }
    
    /**
     * 释放查询结果
     */
    public function free() {
        $this->PDOStatement = null;
    }
    
    /**
     * 查询单挑
     * @param unknown $sql
     * @param unknown $bind
     * @return boolean|array
     */
    public function queryOne($sql, $bind = array(), $master = false) {
        $this->initConnect($master);
        if (! $this->_linkID) {
            return false;
        }
        
        if (! empty($bind)) {
            $this->queryStr .= '[ ' . print_r($bind, true) . ' ]';
        }
        
        if (! empty($this->PDOStatement)) {
            $this->free();
        }
        
        $this->PDOStatement = $this->_linkID->prepare($sql);
        if (false === $this->PDOStatement) {
            self::Error($this->pdoError());
        }
        
        $result = $this->PDOStatement->execute($bind);
        
        if (false === $result) {
            $this->pdoError();
            return false;
        }
        
        return $this->PDOStatement->fetch(PDO::FETCH_ASSOC);        
    }
    
    /**
     * 查询所有
     * @param unknown $sql
     * @param unknown $bind
     * @return boolean|array
     */
    public function query($sql, $bind = array(), $master = false) {
        $this->initConnect($master);
        if (! $this->_linkID) {
            return false;
        }
    
        if (! empty($bind)) {
            $this->queryStr .= '[ ' . print_r($bind, true) . ' ]';
        }
    
        if (! empty($this->PDOStatement)) {
            $this->free();
        }
    
        $this->PDOStatement = $this->_linkID->prepare($sql);
        if (false === $this->PDOStatement) {
            Db::Error($this->pdoError());
        }
    
        $result = $this->PDOStatement->execute($bind);
    
        if (false === $result) {
            Db::Error($this->pdoError());
            return false;
        }
    
        return $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 执行语句
     * @see Db::execute()
     */
    public function execute( $sql, $bind = array(), $getInsertId = FALSE ) {
        $this->initConnect(true);
        if (! $this->_linkID) {
            Db::Error("没有数据库链接");
            return false;
        }
        
        $this->queryStr = $sql;
        if (! empty($bind)) {
            $this->queryStr .= '[ ' . print_r($bind, true) . ' ]';
        }
        //获取前次查询的结果
        if ( !empty($this->PDOStatement)) {
            $this->free();
        }
        
        $this->PDOStatement = $this->_linkID->prepare($sql);
        if (false === $this->PDOStatement) {
            Db::Error($this->pdoError());
        }
        
        $result = $this->PDOStatement->execute($bind);
        
        if (false === $result) {
            Db::Error($this->pdoError());
            return false;
        } 
        
        $res = $this->PDOStatement->rowCount();
        if( $getInsertId ){
            $res =  $this->_linkID->lastInsertId();
        }
        return $res;
        
    }
    
    /**
     * 参数绑定
     * @param unknown $bind
     */
    protected function bindPdoParam($bind) {
        foreach ($bind as $key => $val) {
            if (is_array($val)) {
                array_unshift($val, $key);
            } else {
                if (is_numeric($val)) {
                    $val = array($key, $val, PDO::PARAM_INT);
                } else {
                    $val = array($key, $val, PDO::PARAM_STR);
                }
            }
            call_user_func_array(array($this->PDOStatement, 'bindValue'), $val);
        }
    }
    
    /**
     * 启动事务
     * @return void
     */
    public function startTrans() {
        $this->initConnect(true);
        
        if (! $this->_linkID) {
            return false;
        }
        
        if ($this->transTimes == 0) {
            $this->_linkID->beginTransaction();
        }
        
        $this->transTimes++;
        return ;
    }
    
    /**
     * 事务提交
     */
    public function commit() {
        if ($this->transTimes > 0) {
            $result = $this->_linkID->commit();
            $this->transTimes = 0;
            
            if (! $result) {
                $this->pdoError();
                return false;
            }
        }
        
        return true;
    }
    
    
    /**
     * 事务回滚
     */
    public function rollback() {
        if ($this->transTimes > 0) {
            $result = $this->_linkID->rollback();
            $this->transTimes = 0;
            
            if (! $result) {
                $this->pdoError();
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     * @access public
     * @return string
     */
    public function pdoError() {
        if($this->PDOStatement) {
            $error = $this->PDOStatement->errorInfo();
            $this->error = $error[1].':'.$error[2];
        }else{
            $this->error = '';
        }
        if('' != $this->queryStr){
            $this->error .= "\n [ SQL语句 ] : ".$this->queryStr;
        }
        
        return $this->error;
    }
}