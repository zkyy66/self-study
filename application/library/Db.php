<?php
/**
 * 数据库连接类
 * @author dell
 *
 */
class Db {
	
    protected $autoFree = false;
    
    //数据库连接池， 支持多个连接
    protected $linkID = array();
    
    //当前链接ID
    protected $_linkID = null;
    	
    //当前sql指令
    protected $queryStr = '';
    
    //事物指令数
    protected $transTimes = 0;
    
    protected $config = null;  //数据库链接配置
    
    //参数绑定
    protected $bind   = array();  
    
    protected static $_instance = array();
	
    public static function getInstance( $dbString ) {
    	
    	//根据数据库链接字产生唯一guid
//     	$guid = Fn::to_guid_string($dbString);
    	
        if (! isset(self::$_instance[$dbString])) {
        	$obj = new Db();
        	self::$_instance[$dbString] = $obj->factory($dbString);
        }
        
        return self::$_instance[$dbString];
    }
	
    /**
     * 实例化数据库驱动
     * @param string $dbString 数据库链接字
     * @return Db
     */
    public function factory( $dbString ) {
    //获取数据库配置
    $dbConfig = Yaf_Registry::get('dbConfig');
    if (! isset($dbConfig[$dbString])) {
        Db::Error("[{$dbString}], 没有数据库配置");
    }
    
    $dbConfig = $dbConfig[$dbString];
    
    if (empty($dbConfig['driver'])) {
        Db::Error("[{$dbString}], 配置没有数据库驱动类型");
    }
    
    $driver = ucfirst($dbConfig['driver']);
    $class  = 'Db_' . $driver;
    
    //检查驱动
    if (! class_exists($class)) {
        Db::Error("[{$dbString}], 数据库驱动类不存在");
    }
    
    $db = new $class($dbConfig);
    
    return $db;		
    }
		
    /**
     * 链接数据库， 支持一主多从
     * @param string $master
     */
    public  function initConnect( $master = true ) {
        if ($master) {
            //链接主数据库
            $dbConfig = $this->config['master'];
            $this->_linkID = $this->connect($dbConfig, 'master');
        } else {
            $dbConfig = $this->config['slave'];
            $r = floor(mt_rand(0, count($dbConfig)-1));
            $this->_linkID = $this->connect($dbConfig[$r], $r);
        }	
        return true;
    }
	
    /**
     * 添加数据 
     * 默认预处理
     * @param string $tableName  数据表名
     * @param array $data  要插入的数据： 数组形式
     * @param boolean $replace 是否采用replace模式插入，默认为false
     * @return number|boolean 返回受影响的行数
     */
    public function insert( $tableName , $data, $replace = false, $getInsertId = false ) {
        $fields = $values = array();
        $bind   = array();
        
        $fieldArr = array_keys($data);
        $placeArr = array_fill(0, count($data), '?');
        $bindArr  = array_values($data);
        $sql      = ($replace ? 'REPLACE' : 'INSERT') . ' INTO ' . $tableName . ' ('.implode(',', $fieldArr).') VALUES ('. implode(',', $placeArr) .')';
        
        return $this->execute($sql, $bindArr, $getInsertId);
    }
	
    	/**
    	 * 按照sql语句插入数据
    	 * @param string $sql
    	 */
    	public function insertBySql( $sql, $getInsertId = false ) {
    	    return $this->execute($sql, array(), $getInsertId);
    	}
	
    /**
     * 更新操作
     * 当where条件为数组形式时，会走预处理
     * @param unknown $tableName
     * @param array $data
     * @param string|array $where
     */
    public function update( $tableName, $data, $where ) {
        if (! $where) {
            Db::Error("更新语句缺少where条件");
        }
        
        $fieldArr = array();
        $bindArr  = array();
        foreach ($data as $key => $val) {
            $key = $this->removeFilterBadChar($key);
            $fieldArr[] = $key.'=?';
            $bindArr[] = $val;
        }
        if (is_array($where)) {
            $whereArr = $where;
            $where    = $this->arrayToWhere($whereArr);
        } else {
            $whereArr = [];
        }
        $sql = "UPDATE `{$tableName}` SET " . join(',', $fieldArr) . ' WHERE ' . $where;
        
        if (is_array($whereArr) && !empty($whereArr)) {
            foreach ($whereArr as $v) {
                $bindArr[] = $v;
            }
        }
        
        return $this->execute($sql, $bindArr);
    }
	
    /**
     * 按照sql语句更新
     * @param string $sql 建议传递预处理sql，用问号做占位符
     * @param array $bindArr 需要预处理绑定的值
     */
    public function updateBySql($sql, $bindArr = []) {
        if (false === stripos($sql, 'where')) {
            Db::Error("更新语句缺少where条件");
        }
        
        return $this->execute($sql, $bindArr);
    }
	
    /**
     * 删除
     * 当where条件为数组形式时，会走预处理
     * @param unknown $table
     * @param unknown $where
     */
    public function delete( $tableName, $where ) {
        if (! $where) {
            Db::Error("删除语句缺少where语句");
        }
        
        if (is_array($where)) {
            $bindArr = $where;
            $where   = $this->arrayToWhere($bindArr);
        } else {
            $bindArr = [];
        }
        
        $sql = "DELETE FROM `{$tableName}` WHERE {$where}";
        
        return $this->execute($sql, $bindArr);
    }
	
    /**
     * 获取一条数据
     * 当条件为数组形式时，会走预处理
     * @param string $tableName
     * @param string|array $where 条件
     * @param array $selectFileds 可以指定抓取的字段
     * @param string $order 例如：id DESC
     * @param string $master 是否从主库中读取，默认从从库中获取
     */
    public function selectOne( $tableName, $where, $selectFileds = array(), $order = null, $master = false ) {
        $fields = '*';
        if (! empty($selectFileds)) {
            $fields = implode(',', $selectFileds);
        }
    
        if (is_array($where)) {
            $bindArr = $where;
            $where   = $this->arrayToWhere($bindArr);
        } else {
            $bindArr = [];
        }
        $sql = "SELECT {$fields} FROM `{$tableName}` WHERE {$where}";
    
        if ($order) {
            $sql .= " ORDER BY {$order}";
        }
        return $this->queryOne($sql, $bindArr, $master);
    }
	
    /**
     * 组合查询
     * 当条件为数组形式时，会走预处理
     * @param string $tableName
     * @param string|array $where
     * @param array $selectFields
     * @param string $order
     * @param string $limit
     * @param boolean $master 是否从主库中读取，默认从从库中获取
     */
    public function selectAll( $tableName, $where, $selectFields = array(), $order = null, $limit = null , $master = false ) {
        $fields = '*';
        if (! empty($selectFields)) {
            $fields = implode(',', $selectFields);
        }
        
        if (is_array($where)) {
            $bindArr = $where;
            $where   = $this->arrayToWhere($bindArr);
        } else {
            $bindArr = [];
        }
        $sql = "SELECT {$fields} FROM `{$tableName}` WHERE {$where}";
        if ($order) {
            $sql .= " ORDER BY {$order}";
        }
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        return $this->query($sql, $bindArr, $master);
    }
	
    /**
     * 按照sql语句查询
     * @param unknown $sql
     */
    public function setlectBySql( $sql , $master = false, $bindArr=[]) {
        return $this->query($sql, $bindArr, $master);
    }
	
    	/**
    	 * 绑定参数
    	 * @param unknown $name
    	 * @param unknown $val
    	 */
    	protected function bindParam( $name, $val ) {
    	    $this->bind[$name] = $val;
    	}
	
    /**
     * 数据库统一错误
     * @param unknown $msg
     * @param number $code
     * @throws Exception
     */
    protected static function Error( $msg, $code = 0 ) {
        Fn::writeLog($msg);
        throw new Exception($msg, $code);
    }
	
    /**
     * 将条件数组，拼凑预处理sql
     * @param array $filter
     * @return string
     *支持的格式
     * array('id'=>2)
     * array('add_time >'=>'2016-12-19')
     * array('add_time >='=>'2016-12-19')
     * array('add_time ='=>'2016-12-19')
     * array('add_time !='=>'2016-12-19')
     * array('title LIKE'=>'%微微%')
     * array('id ='=>array(1,2,3,4)) 与 array('id'=>array(1,2,3,4)) 相同
     */
    public function arrayToWhere(&$filter)
    {
        if (!$filter) {
            return '';
        }
    
        $where = '';
        $sql = '';
        $parm = array();
        foreach ($filter as $k => $v) {
            // 过滤 key
            $k = $this->removeFilterBadChar($k);
    
            if (is_array($v)) {
                // 例如：array('id ='=>array(1,2,3,4))
                if (!$v) continue;
                $where = $k . ' IN(' . implode(',', array_fill(0, count($v), '?')) . ')';
                foreach ($v as $v1) {
                    $parm[] = $v1;
                }
            } elseif (strpos($k, '>') || strpos($k, '<') || strpos($k, '!')  || strpos($k, '=') || stripos($k, 'LIKE') || stripos($k, '&')) {
            // 例如：array('add_time >'=>'2016-12-19') 
            // array('add_time >='=>'2016-12-19')
            // array('add_time ='=>'2016-12-19')
            // array('add_time !='=>'2016-12-19')
            // array('title LIKE'=>'%微微%')
                $where = $k . '?';
                $parm[] = $v;
            } else {
                // 例如：array('id'=>2)
                $where = $k . '=?';
                $parm[] = $v;
            }
    
            if (!$sql) {
                $sql = " {$where}";
            } else {
                // 例如：array('id'=>2, 'OR id'=>3)
                $sql = $sql . ' ' . ((false !== stripos($k, 'OR ') || false !== stripos($k, 'AND ')) ? '': 'AND ') . $where;
            }
        }
    
        $filter = $parm;
    
        return $sql;
    }
	
    /**
     * 过滤 Filter 中的非法字符
     */
    public function removeFilterBadChar($array)
    {
        if (is_numeric($array)) {
            return $array;
        } elseif (!is_array($array)) {
            return str_replace(array('"', "'", ',', ';', '*', '#', '/', '\\', '%'), '', $array);
        }
    
        foreach ($array as $k => $v) {
            $array[$k] = $this->removeFilterBadChar($v);
        }
        return $array;
    }
	
}
