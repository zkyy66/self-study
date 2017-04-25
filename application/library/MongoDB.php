<?php
class MongoDB {
    
    private $_db                    = null;
    private $_collection            = null;
    private static $_instances      = [];
    private $_connObj               = null;
//     private static $_confs = [
//         'mongodev' => [
//             'uri'       => 'mongodb://172.28.50.179:28010',
//             'options'   => [],
//         ],
//     ];
    
    /**
     * 获取MongoDB实例
     * @param string $mongoString
     * @return MongoDB
     */
    public static function getInstance( $mongoString = 'mongodev' ) {
        if (! $mongoString) {
            return false;
        }
        
        $mongodbConfig = Yaf_Registry::get('config')->get('mongodb.'.$mongoString);
        if (! $mongodbConfig) {
            MongoDB::Error("没有mongodb：{$mongoString}实例配置");
            
            return false;
        }
       
        
        if (! isset(self::$_instances[$mongoString])) {
            $mongoObj = new MongoDB($mongodbConfig->toArray());
            self::$_instances[$mongoString] = $mongoObj;
        }
        
        return self::$_instances[$mongoString];
        
    }

    private function __construct( array $mongoConfig) {
        try {
            $this->_connObj = new MongoDB\Driver\Manager($mongoConfig['uri'], $mongoConfig['options']);
//             MongoDB::Error(var_export($this->_connObj, true));
        } catch (Exception $e) {
            MongoDB::Error("连接mongo错误：" . $e->getMessage() . " \n\t" . var_export($mongoConfig, true));
            
            return false;
        }
    }

    public function setDb($db) {
        $this->_db = $db;
        return $this;
    }
    
    public function getDb() {
        return $this->_db;
    }
    
    public function setCollection( $collection ) {
        $this->_collection = $collection;
        return $this;
    }
    
    public function getCollection () {
        return $this->_collection;
    }
    
    /**
     * 查询
     * @param array $filter
     * @param unknown $queryOptions
     * @return boolean
     */
    public function query( array $filter, $queryOptions = [] ,$db = '', $collection = '') {
//        if (empty($this->getDb()) || empty($this->getCollection())) {
//            MongoDB::Error("缺少db和collection");
//            return false;
//        }
//
//        $namespace = $this->getDb().'.'.$this->getCollection();

        if (empty($db) || empty($collection)) {
            MongoDB::Error("缺少db和collection");
            return false;
        }

        $namespace = $db.'.'.$collection;
        $query = new MongoDB\Driver\Query($filter, $queryOptions);
        $cursor = $this->_connObj->executeQuery($namespace, $query);
        return $cursor->toArray();
    }
    
    /**
     * 插入数据
     * @param array $row
     * @param string $db
     * @param string $collection
     * @return boolean
     */
    public function insert(array $row, $db = '', $collection = '') {
        if (! empty($db)) {
            $this->_db = $db;
        }
        if (! empty($collection)) {
            $this->_collection = $collection;
        }
    
        $params = [
            [
                'operType' => 'insert',
                'data'     => $row,
            ]
        ];
    
        return $this->bulkWrite($params, false);
    }
    
    /**
     * 更新操作
     * @param array $filter
     * @param array $data
     * @param unknown $updateOptions
     * @param string $db
     * @param string $collection
     * @return boolean
     */
    public function update(array $filter, array $data, $updateOptions = [], $db = '', $collection = '') {
        if (! empty($db)) {
            $this->_db = $db;
        }
        if (! empty($collection)) {
            $this->_collection = $collection;
        }
        
        $params = [
            [
                'operType'          => 'update',
                'filter'            => $filter,
                'data'              => $data,
                'updateOptions'     => $updateOptions,
            ]
        ];
        return $this->bulkWrite($params, false);
    }
    
    /**
     * 删除操作
     * @param array $filter
     * @param unknown $deleteOptions
     * @param string $db
     * @param string $collection
     * @return boolean
     */
    public function delete(array $filter, $deleteOptions = [], $db = '', $collection = '') {
        if (! empty($db)) {
            $this->_db = $db;
        }
        if (! empty($collection)) {
            $this->_collection = $collection;
        }
        
        $params = [
            [
                'operType'          => 'delete',
                'filter'            => $filter,
                'deleteOptions'     => $deleteOptions,
            ]
        ];
        
        return $this->bulkWrite($params, false);
    }
    
    /**
     * 执行命令
     * @param array $params
     * @param string $db
     * @return boolean|unknown
     */
    public function runCommand(array $params, $db = '') {
        if (empty($db)) {
            $db = $this->_db;
        }
        
        if (empty($db)) {
            MongoDB::Error("MongoDB:  缺少db参数");
            return false;
        }
        
        $command = new MongoDB\Driver\Command($params);
        
        try {
            $cursor = $this->_connObj->executeCommand($db, $command);
            $response = $cursor->toArray()[0];
            return $response;
        } catch (Exception $e) {
            MongoDB::Error("执行命令错误：" . $e->getMessage() . " \n\t" . var_export($params, true));
            
            throw $e;
            
            return false;
            
        }
    }
    
    /**
     * 批量执行
     * @param array $params  要执行的集合
     *      $params = [
     *          [
     *              'operType'  => 'insert',
     *              'data'      => [],
     *          ],
     *          [
     *              'operType'  => 'update',
     *              'filter'    => [],
     *              'data'      => [],
     *              'updateOptions' => [],
     *          ],
     *          [
     *              'operType' => 'delete',
     *              'filter'   => [],
     *              'deleteOptions' => []
     *          ]
     *      ]
     * @param boolean $ordered  是否顺序执行
     * @return boolean
     */
    public function bulkWrite( array $params, $ordered = true ) {
        if (empty($this->getDb()) || empty($this->getCollection())) {
            MongoDB::Error("缺少db和collection");
            return false;
        }
        
        $namespace = $this->getDb().'.'.$this->getCollection();
        
        $bulk = new MongoDB\Driver\BulkWrite(['ordered' => $ordered]);
        foreach ($params as $param) {
            switch ($param['operType']) {
                case 'insert':
                    if (is_array($param['data'])) {
                        $bulk->insert($param['data']);
                    }
                    break;
                case 'update':
                    if (! isset($param['filter']) || ! isset($param['data'])) {
                        continue;
                    }
                    $updateOptions = isset($param['updateOptions']) ? $param['updateOptions'] : [];
                    $bulk->update($param['filter'], $param['data'], $param['updateOptions']);
                    break;
                case 'delete':
                    if (! isset($param['filter'])) {
                        continue;
                    } 
                    $deleteOptions = isset($param['deleteOptions']) ? $param['deleteOptions'] : [];
                    $bulk->delete($param['filter'], $deleteOptions);
                    break;
            }
        }
        
        $writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 1000);
        try {
            $result = $this->_connObj->executeBulkWrite($namespace, $bulk, $writeConcern);
        } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
            $result = $e->getWriteResult();
             
            //Check if the write concern could not be fulfilled
            if ($writeConcernError = $result->getWriteConcernError()) {
                $errMsg = sprintf("%s (%d): %s\n",
                    $writeConcernError->getMessage(),
                    $writeConcernError->getCode(),
                    var_export($writeConcernError->getInfo(), true)
                );
                
                MongoDB::Error($errMsg);
            }
             
            foreach ($result->getWriteErrors() as $writeErr) {
                $errMsg = sprintf("Operation#%d: %s (%d)\n",
                    $writeErr->getIndex(),
                    $writeErr->getMessage(),
                    $writeErr->getCode()
                 );
                
                MongoDB::Error($errMsg);
            }
            
            return false;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            $errMsg = sprintf("Other error: %s\n", $e->getMessage());
            MongoDB::Error($errMsg);
            
            return false;
        }
        
        return true;
    }
    public static function Error($msg) {
        Log::mongoError($msg);
    }

//    private function __destruct() {
//        $this->_connObj->close(true);
//    }
}