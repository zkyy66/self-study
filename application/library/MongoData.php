<?php
class MongoData {
    
    protected $connection = false; //connection对象
    protected $db;                 //db对象
    protected $persist = false;  //是否使用持久化链接，默认false
    protected $selectes = array();
    protected $wheres   = array();
    protected $sorts    = array();
    protected $limit    = 999999;
    protected $offset   = 0;
    protected static $instances = array();
    
    /**
     * 
     * @param string $mongoString
     * @return boolean|Ambigous <multitype:, MongoData>
     */
    public static function getInstance( $mongoString = 'mongodev' ) {
        if (! $mongoString) {
            return false;
        }
        
        $mongodbConfig = Yaf_Registry::get('config')->get('mongodb.'.$mongoString);
        if (! $mongodbConfig) {
            throw new Exception("没有mongodb：{$mongoString}实例配置");
        }
        
        
        if (! isset(self::$instances[$mongoString])) {
            $mongoObj = new MongoData($mongodbConfig->toArray());
            self::$instances[$mongoString] = $mongoObj;
        }
        
        return self::$instances[$mongoString];
    }
    
    /**
     * 构造函数
     * @param array $mongoConfig
     * @throws Exception
     * @return MongoData
     */
    public function __construct( array $mongoConfig ) {
        if ( ! class_exists('Mongo')) {
            throw new Exception("没有安装Mongo扩展或者没有启用");
        }   

        try {
            $this->connection = new MongoClient($mongoConfig['uri'], $mongoConfig['options']);
            $this->db = $this->connection->{$mongoConfig['options']['db']};
            return $this;
        } catch (Exception $e) {
            throw new Exception("链接mongo失败：{$e->getMessage()}", $e->getCode());
        }
        
    }
    
    public function __destruct() {
        $this->_clear();
        $this->db = null;
        $this->connection->close(true);
    }
    
    /**
     * 删除database
     * @param string $database
     * @throws Exception
     * @return boolean
     */
    public static function dropDb($database = null) {
        if (empty($database)) {
            throw new Exception("Drop mongo database 失败，因为database为空");
            return false;
        } 

        try {
            static::getInstance($database)->connection->{$database}->drop();
            return true;
        } catch (Exception $e) {
            throw new Exception("删除mongo database `{$database}` 失败: $e->getMessage()", $e->getCode());
        }
    }
    
    /**
     * 删除collection集合
     * @param unknown $db
     * @param unknown $collection
     * @throws Exception
     * @return boolean
     */
    public static function dropCollection($db, $collection) {
        if (empty($db)) {
            throw new Exception("删除mongo collection失败，因为db为空");
        }
        
        if (empty($collection)) {
            throw new Exception("删除mongo collection失败，因为collection为空");
        }
        
        try {
            static::getInstance($db)->db->{$collection}->drop();
            return true;
        } catch (Exception $e) {
            throw new Exception("删除mongo database `{$db}` 失败: $e->getMessage()", $e->getCode());
        }
    }
    
    /**
     * 选择条件
     * @param unknown $includes
     * @param unknown $excludes
     * @return MongoData
     */
    public function select($includes = array(), $excludes = array()) {
        if (! is_array($includes)) {
            $includes = array($includes);
        }
        if (! is_array($excludes)) {
            $excludes = array($excludes);
        }
        
        if (! empty($includes)) {
            foreach ($includes as $col) {
                $this->selectes[$col] = 1;
            }
        } else {
            foreach ($excludes as $col) {
                $this->selectes[$col] = 0;
            }
        }
        
        return $this;
    }
    
    /**
     * where条件
     * @param array $wheres
     * @return MongoData
     */
    public function where($wheres = array()) {
        foreach ($wheres as $wh => $val) {
            if (is_numeric($val)) {
                $val = intval($val);
            }
            
            $this->wheres[$wh] = $val;
        }
        
        return $this;
    }
    
    /**
     * orWhere条件
     * @param unknown $wheres
     * @return MongoData
     */
    public function orWhere($wheres = array()) {
        if (count($wheres) > 0) {
            if (! isset($this->wheres['$or']) || ! is_array($this->wheres['$or'])) {
                $this->wheres['$or'] = array();
            }
            
            foreach ($wheres as $wh => $val) {
                $this->wheres['$or'][] = array($wh => $val);
            }
        }
        
        return $this;
    }
    
    /**
     * where in条件
     * @param string $field
     * @param unknown $in
     * @return MongoData
     */
    public function whereIn($field = '', $in = array()) {
        $this->_where_init($field);
        $this->wheres[$field]['$in'] = $in;
        return $this;
    }
    
    /**
     * where not in条件
     * @param string $field
     * @param unknown $in
     * @return MongoData
     */
    public function whereNotIn($field = '', $in = array()) {
        $this->_where_init($field);
        $this->wheres[$field]['$nin'] = $in;
        return $this;
    }
    
    /**
     * where  大于条件
     * @param string $field
     * @param unknown $x
     * @return MongoData
     */
    public function whereGt($field = '', $x)
    {
        $this->_where_init($field);
        $this->wheres[$field]['$gt'] = $x;
        return $this;
    }
    
    /**
     * where 大于等于条件
     * @param string $field
     * @param unknown $x
     * @return MongoData
     */
    public function whereGte($field = '', $x)
    {
        $this->_where_init($field);
        $this->wheres[$field]['$gte'] = $x;
        return($this);
    }
    
    /**
     * where 小于条件
     * @param string $field
     * @param unknown $x
     * @return MongoData
     */
    public function whereLt($field = '', $x)
    {
        $this->_where_init($field);
        $this->wheres[$field]['$lt'] = $x;
        return($this);
    }
    
    /**
     * where 小于等于条件
     * @param string $field
     * @param unknown $x
     * @return MongoData
     */
    public function whereLte($field = '', $x)
    {
        $this->_where_init($field);
        $this->wheres[$field]['$lte'] = $x;
        return $this;
    }
    
    /**
     * where between条件，包含等于
     * @param string $field
     * @param unknown $x
     * @param unknown $y
     * @return MongoData
     */
    public function whereBetween($field = '', $x, $y)
    {
        $this->_where_init($field);
        $this->wheres[$field]['$gte'] = $x;
        $this->wheres[$field]['$lte'] = $y;
        return $this;
    }
    
    /**
     * where between 条件，不包含等于
     * @param string $field
     * @param unknown $x
     * @param unknown $y
     * @return MongoData
     */
    public function whereBetweenNe($field = '', $x, $y)
    {
        $this->_where_init($field);
        $this->wheres[$field]['$gt'] = $x;
        $this->wheres[$field]['$lt'] = $y;
        return $this;
    }
    
    /**
     * where 不等于条件
     * @param string $field
     * @param unknown $x
     * @return MongoData
     */
    public function whereNe($field = '', $x)
    {
        $this->_where_init($field);
        $this->wheres[$field]['$ne'] = $x;
        return $this;
    }
    
    /**
     * where near查询
     * @param string $field
     * @param unknown $co
     * @return MongoData
     */
    public function whereNear($field = '', $co = array())
    {
        $this->_where_init($field);
        $this->where[$field]['$near'] = $co;
        return $this;
    }
    
    /**
     * like查询
     * @param string $field 字段名称
     * @param string $value 值
     * @param string $flags 正则表达式标记
     *              i： 大小写不敏感
     *              m: 多行
     *              x: 能够包含注释
     *              l: 语言环境
     *              s: dotall, "."匹配任何字符，包括换行符
     *              u: 匹配Unicode
     * @param string $disableStartWildcard 是否开启开始通配符^
     * @param string $disableEndWildcard   是否开启结束通配符$
     * @return MongoData
     * 
     * @usage $mongodb->like('foo', 'bar', 'im', false, false)
     */
    public function like($field, $value, $flags = 'i', $enableStartWildcard = false, $enableEndWildcard = false) {
        $field = (string) trim($field);
        $value = (string) trim($value);
        
        if (! $field || ! $value) {
            return $this;
        }
        
        $value = quotemeta($value);
        $this->_where_init($field);
        
        (bool) $enableStartWildcard === true && $value = '^'.$value;
        (bool) $enableEndWildcard   === true && $value .= '$';
        
        $regex = "/$value/$flags";
        $this->wheres[$field] = new MongoRegex($regex);
        
        return $this;
    }
    
    /**
     * 排序
     *      在MongoDB中使用使用sort()方法对数据进行排序，sort()方法可以通过参数指定排序的字段，
     *      并使用 1 和 -1 来指定排序的方式，其中 1 为升序排列，而-1是用于降序排列。
     *      使用时必须设置value为-1, false, desc DESC, 这些表示降序，其他表示升序
     * @param array $fields
     * @return MongoData
     * 
     * @usage $mongodb->orderBy(['col' => -1])
     */
    public function orderBy($fields = array()) {
        foreach ($fields as $col => $val) {
            if ($val == -1 || $val === false || strtolower($val) == 'desc') {
                $this->sorts[$col] = -1;
            } else {
                $this->sorts[$col] = 1;
            }
        }
        
        return $this;
    }
    
    /**
     * limit限制
     * @param number $limit
     * @return MongoData
     */
    public function limit($limit = 99999) {
        $limit = intval($limit);
        if ($limit > 0) {
            $this->limit = $limit;
        }
        
        return $this;
    }
    
    /**
     * 设置偏移量
     * @param number $offset
     * @return MongoData
     */
    public function offset($offset = 0) {
        $offset = intval($offset);
        if ($offset > 0) {
            $this->offset = $offset;
        }
        
        return $this;
    }
    
    /**
     * 基于传递的参数获取documents
     * @param string $collection
     * @param array $where
     * @param number $offset
     * @param number $limit
     * @return multitype:Ambigous <boolean, unknown>
     */
    public function getWhere($collection, $where = array(), $offset = 0, $limit = 99999) {
        return $this->where($where)->limit($limit)->offset($offset)->get($collection);
    }
    
    /**
     * 基于mongo 基类获取数据
     * @param unknown $collection
     * @return boolean|unknown
     */
    public function getCursor($collection) {
        if (empty($collection)) {
            throw new Exception("缺少collection名称");
            return false;
        }
        
        $documents = $this->db->{$collection}->find($this->wheres, $this->selectes)->limit((int) $this->limit)->skip((int) $this->offset)->sort((array) $this->sorts);
        
        $this->_clear();
        return $documents;
    }
    
    /**
     * 获取数据
     * @param string $collection
     * @return multitype:Ambigous <boolean, unknown>
     */
    public function get($collection) {
        
        $query = json_encode(array(
            'type'			=> 'find',
            'collection'	=> $collection,
            'select'		=> $this->selectes,
            'where'			=> $this->wheres,
            'limit'			=> $this->limit,
            'offset'		=> $this->offset,
            'sort'			=> $this->sorts,
        ));
        Fn::writeLog($query);
        
        $documents = $this->getCursor($collection);
        
        $returns = array();
        if ($documents && ! empty($documents)) {
            foreach ($documents as $doc) {
                $returns[] = $doc;
            }
        }
        
        return $returns;
    }
    
    /**
     * 获取单条数据
     * @param string $collection
     * @return boolean|unknown
     */
    public function getOne($collection = '') {
        if (empty($collection)) {
            throw new Exception("缺少collection参数，不允许为空");
            return false;
        }
        
        $returns = $this->db->{$collection}->findOne($this->wheres, $this->selectes);
        
        $this->_clear();
        return $returns;
    }
    
    /**
     * 统计
     * @param string $collection
     * @param string $foundOnly
     *      是否基于limit及skip返回统计数据
     *      Send cursor limit and skip information to the count function, if applicable.
     * @return boolean|unknown
     */
    public function count($collection, $foundOnly = false) {
        if (empty($collection)) {
            throw new Exception("缺少collection参数， 不允许为空");
            return false;
        }
        
        $count = $this->db->{$collection}->find($this->wheres)->limit((int) $this->limit)->skip((int) $this->offset)->count($foundOnly);
        $this->_clear();
        
        return $count;
    }
    
    /**
     * 插入document到collection中
     * @param string $collection
     * @param array $data
     * @return boolean
     */
    public function insert($collection, $data = array()) {
        if (empty($collection)) {
            throw new Exception("Mongo 缺少collection参数，不允许为空");
            return false;
        }
        
        if (empty($data) || ! is_array($data)) {
            throw new Exception("插入mongo中的数据不允许为空, 且必须是数组");
            return false;
        }
        
        try {
            $ret = $this->db->{$collection}->insert($data, ['fsync' => true]);
            
            return true;
        } catch (Exception $e) {
            throw new Exception("插入mongo数据失败: $e->getMessage()", $e->getCode());
            return false;
        }
    }
    
    /**
     * 更新数据
     * @param unknown $collection
     * @param unknown $data
     * @param unknown $options
     * @param string $literal
     * @return boolean
     */
    public function update($collection, $data = array(), $options = array(), $literal = false) {
        if (empty($collection)) {
            throw new Exception("Mongo 缺少collection参数，不允许为空");
            return false;
        }
        
        if (empty($data) || ! is_array($data)) {
            throw new Exception("更新mongo中的数据不允许为空, 且必须是数组");
            return false;
        }
        
        try {
            $options = array_merge($options, array('fsync' => true, 'multiple' => false));
            
            $this->db->{$collection}->update($this->wheres, ($literal ? $data : array('$set' => $data)), $options);
            
            $this->_clear();
            
            return true;
        } catch (Exception $e) {
            throw new Exception("更新mongo数据失败: $e->getMessage()", $e->getCode());
            return false;
        }
    }
    
    /*
    * 更新多条数据
    * @param unknown $collection
    * @param unknown $data
    * @param unknown $options
    * @param string $literal
    * @return boolean
    */
    public function updateAll($collection, $data = array(), $options = array(), $literal = false) {
        if (empty($collection)) {
            throw new Exception("Mongo 缺少collection参数，不允许为空");
            return false;
        }
    
        if (empty($data) || ! is_array($data)) {
            throw new Exception("更新mongo中的数据不允许为空, 且必须是数组");
            return false;
        }
    
        try {
            $options = array_merge($options, array('fsync' => true, 'multiple' => true));
    
            $this->db->{$collection}->update($this->wheres, ($literal ? $data : array('$set' => $data)), $options);
    
            $this->_clear();
    
            return true;
        } catch (Exception $e) {
            throw new Exception("更新mongo数据失败: $e->getMessage()", $e->getCode());
            return false;
        }
    }
    
    /**
     * 删除单条数据
     * @param unknown $collection
     * @return boolean
     */
    public function delete($collection) {
        if (empty($collection)) {
            throw new Exception("Mongo 缺少collection参数，不允许为空");
            return false;
        }
        
        try {
            $this->db->{$collection}->remove($this->wheres, array('fsync' => true, 'justOne' => true));
            $this->_clear();
            return true;
        } catch (Exception $e) {
            throw new Exception("Mongo删除数据失败: $e->getMessage()", $e->getCode());
            
            return false;
        }
    }
    
    /**
     * 删除所有数据
     * @param unknown $collection
     * @return boolean
     */
    public function deleteAll($collection) {
        if (empty($collection)) {
            throw new Exception("Mongo 缺少collection参数，不允许为空");
            return false;
        }
        
        try {
            $this->db->{$collection}->remove($this->wheres, array('fsync' => true, 'justOne' => false));
            $this->_clear();
            return true;
        } catch (Exception $e) {
            throw new Exception("Mongo删除数据失败: $e->getMessage()", $e->getCode());
        
            return false;
        }
    }
    
    /**
     * 执行command命令
     * @param unknown $query
     * @return unknown|boolean
     * 
     * @usage $mongodb->command(array('geoNear' => 'a', 'near' => (), 'num' => 10))
     */
    public function command($query = array()) {
        try {
            $run = $this->db->command($query);
            
            return $run;
        } catch (Exception $e) {
            throw new Exception("Mongo command 失败：{$e->getMessage()}", $e->getCode());
            return false;
        }
    }
    
    /**
     * 创建索引
     * @param unknown $collection
     * @param unknown $keys an ssociative array of keys, array(field => direction)
     * @param unknown $options
     * @throws Exception
     * @return boolean|MongoData
     * 
     * @usage $mongodb->addIndex($collection, array('first_name' => -1), array('unique' => true))
     */
    public function addIndex($collection, $keys = array(), $options = array()) {
        if (empty($collection)) {
            throw new Exception("添加Mongo索引，缺少参数collection");
        }
        
        if (empty($keys) ||! is_array($keys)) {
            throw new Exception("Mongo 索引无法创建，缺少keys参数，或者keys参数不为数组");
        }
        
        foreach ($keys as $col => $val) {
            if ($val == -1 || $val === false || strtolower($val) == 'desc') {
                $keys[$col] = -1;
            } else {
                $keys[$col] = 1;
            }
        }
        
        if ($this->db->{$collection}->ensureIndex($keys, $options) == true) {
            $this->_clear();
            
            return $this;
        } else {
            throw new Exception("创建mongo索引时出现错误");
        }
    }
    
    /**
     * 删除mongo索引
     * @param unknown $collection
     * @param array $keys  keys必须为数组， 数组的值需设置为1或者-1
     * @throws Exception
     * @return MongoData
     * 
     * @usage $mongodb->removeIndex($collection, array('firstName' => -1))
     */
    public function removeIndex($collection, $keys = array()) {
        if (empty($collection)) {
            throw new Exception("删除Mongo索引时，缺少collection参数");
        }
        
        if (empty($keys) || ! is_array($keys)) {
            throw new Exception("删除mongo索引时， keys参数不存在或keys参数不是数组");
        }
        
        if ($this->db->{$collection}->deleteIndex($keys) == true) {
            $this->_clear();
            return $this;
        } else {
            throw new Exception("删除Mongo索引时失败");
        } 
    }
    
    /**
     * 删除mongo所有的索引
     * @param unknown $collection
     * @throws Exception
     * @return MongoData
     */
    public function removeAllIndexes($collection) {
        if (empty($collection)) {
            throw new Exception("删除Mongo索引时，缺少collection参数");
        }
        
        $this->db->{$collection}->deleteIndexes();
        $this->_clear();
        
        return $this;
    }
    
    /**
     * 获取collection的所有索引信息
     * @param unknown $collection
     * @throws Exception
     */
    public function listIndexes($collection) {
        if (empty($collection)) {
            throw new Exception("删除Mongo索引时，缺少collection参数");
        }
        
        return $this->db->{$collection}->getIndexInfo();
    }
    
    /**
     * 按照集合名称返回集合对象
     * @param unknown $collection
     */
    public function getCollection($collection) {
        return $this->db->{$collection};
    }
    
    /**
     * 获取db的所有collection集合
     * @param bool $includeSystemCollections 是否包含系统集合，默认不包含
     */
    public function listCollections($includeSystemCollections = false) {
        $options = $includeSystemCollections ? array('includeSystemCollections' => $includeSystemCollections) : '';
        
        return $this->db->listCollections($options);
    }
    
    /**
     * 初始化where中的某个字段查询条件
     * @param unknown $param
     */
    private function _where_init($param) {
        if (! isset($this->wheres[$param])) {
            $this->wheres[$param] = array();
        }
    }
    
    /**
     * 重置设置条件
     */
    private function _clear() {
        $this->selectes = array();
        $this->wheres   = array();
        $this->limit    = 999999;
        $this->offset   = 0;
        $this->sorts    = array();
    }
    
}