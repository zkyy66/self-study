<?php
if (! defined("ERROR_PARAM")){
    define("ERROR_PARAM", 1001);        //　参数错误
	define("ERROR_SYS", 1002);          //  系统错误
	define("ERROR_IOGIC", 1003);        //  业务逻辑错误
}
class BaseModel
{
    public $_dbString    = 'portal';
    public $_table       = '';
    
    public $_redisString = '';        //redis
    
    public $_errorNo     = 0;
    public $_errorMsg    = '';
    
    public $resErrorSys = false;
    
    public $table_status    = array();

    public $errorMsgConfig = array(
        ERROR_PARAM => "参数错误", 
        ERROR_SYS   => "系统错误", 
        ERROR_IOGIC => "数据逻辑错误"
    );
    
    function __construct(){}
    
    function regainError()
    {
        $this->_errorNo  = 0;
        $this->_errorMsg = '';
    }
    function setErrorNo($error_no, $errorMsg = "")
    {
        $this->_errorNo = $error_no;
        $this->_errorMsg = $errorMsg;
    }

    function getErrorNo()
    {
        return $this->_errorNo;
    }
    
    function getErrorMsg()
    {
        $msg = "";
        if (array_key_exists($this->_errorNo, $this->errorMsgConfig)) {
            $msg = $this->errorMsgConfig[$this->_errorNo];
        }
        
        if ($this->_errorMsg) {
            $msg .= ":" . $this->_errorMsg;
        }
        
        return $msg;
    }
  
    /**
     * 增加数据
     *   - 支持单条SQL语句，$row 需要为字符串型,$row必须是完整的sql语句，后台验证insert
     *   - 如果是sql
     *   - 只返回成功以否，不返回插入的自增ID
     *
     * @param string|array() $row  需要增加的表字段 		
     * @param string $table
     * @param string $dbString
     * @return int - 更新影响的行数 | false
     * 形式一：add(['title'=>'标题']) 推荐
     * 形式二：add('INSERT INTO `test`.`ac` (`id`, `title`) VALUES (1, 'FSDF');')，不推荐，用不上预处理
     */
    protected function add($row, $replace = false,$getInsertId=false,$table='', $dbString='')
    {
        if (! $table){
            $table = $this->_table;
        }
        if (! $dbString){
            $dbString = $this->_dbString;
        }
        $result = false;
        try {
            if (is_array($row)){
                if (! $this->_encodeTableRow($row)){
                    $this->setErrorNo(ERROR_PARAM);
                    return false;
                }
                $result = Db::getInstance($dbString)->insert($table, $row, $replace, $getInsertId);
            } else {
                $result = Db::getInstance($dbString)->insertBySql($row);
            }
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function add Exception: ".$e->getMessage());
        }

     
        if (false === $result){
            $this->setErrorNo(ERROR_SYS);
        }
        
        return $result;
    }
    
    /**
     * 增加数据(获取自增ID)
     *   - 支持单条SQL语句，$row 需要为字符串型,$row必须是完整的sql语句，后台验证insert
     *   - 如果是sql
     *   - 只返回成功以否，不返回插入的自增ID
     *
     * @param string|array() $row  需要增加的表字段
     * @param string $table
     * @param string $dbString
     * @return int - 更新影响的行数 | false
     * 形式一：addGetInsertId(['title'=>'标题'])
     * 形式二：addGetInsertId('INSERT INTO `test`.`ac` (`id`, `title`) VALUES (1, 'FSDF');')，不推荐，用不上预处理
     */
    protected function addGetInsertId($row, $replace = false, $table='', $dbString='')
    {
        if (! $table){
            $table = $this->_table;
        }
        if (! $dbString){
            $dbString = $this->_dbString;
        }
        $result = false;
        
        try {
            if (is_array($row)){
                if (! $this->_encodeTableRow($row)){
                    $this->setErrorNo(ERROR_PARAM);
                    return false;
                }
                $result = Db::getInstance($dbString)->insert($table, $row, $replace, TRUE);
            } else {
                $result = Db::getInstance($dbString)->insertBySql($row, TRUE);
            }
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function addGetInsertId Exception: ".$e->getMessage());
        }
        

         
        if (false === $result){
            $this->setErrorNo(ERROR_SYS);
        }
    
        return $result;
    }
    
    /**
     * 更新操作
     * @param string|array $row
     * @param string $where
     * @param string $table
     * @param string $dbString
     * @return boolean|unknown  返回受影响的行数
     * 形式一：update(['title'=>'标题'], 'id=1')，不推荐，用不上预处理
     * 形式二：update(['title'=>'标题'], ['id'=>1])
     * 形式三：update('UPDATE user SET name = xxx WHERE id = 1')，不推荐，用不上预处理
     * 形式四：update('UPDATE user SET name = ? WHERE id = ?', ['xxx', '1'])
     */
    protected function update($row, $where = '', $table='', $dbString=''){
        if (! $table){
            $table = $this->_table;
        }
        
        if (! $dbString){
            $dbString = $this->_dbString;
        }
        $result = false;
        try {
            if (is_array($row)){
                if (! $this->_encodeTableRow($row)){
                    $this->setErrorNo(ERROR_PARAM);
                    return false;
                }
                $result = Db::getInstance($dbString)->update($table, $row, $where);
            } else {
                // 当$row是一条sql时，where参数作为绑定数组，见形式四
                $bindArr = is_array($where) ? $where : [];
                $result = Db::getInstance($dbString)->updateBySql($row, $bindArr);
            }
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function update Exception: ".$e->getMessage());
        }

        
        if (false === $result){
            $this->setErrorNo(ERROR_SYS);
        }
        
        return $result;
    }
    
    /**
     * 删除数据，返回受影响的行数
     * @param unknown $where
     * @param string $table
     * @param string $dbString
     * 形式一：delete(['id'=>1])
     * 形式二：delete('id=1')，不推荐，用不上预处理
     */
    protected function delete($where, $table='', $dbString=''){
    
        if (! $where) {
            $this->setErrorNo(ERROR_PARAM);
            return false;
        }
        
        if (! $table){
            $table = $this->_table;
        }
        if (! $dbString){
            $dbString = $this->_dbString;
        }
        $result = false;
        try {
            $result = Db::getInstance($dbString)->delete($table, $where);
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function delete Exception: ".$e->getMessage());
        }
        
        if (false === $result){
            $this->setErrorNo(ERROR_SYS);
        }
        return $result;
    }
    
    /**
     * 获取单条数据
     * @param unknown $where
     * @param unknown $fields
     * @param string $order
     * @param string $table
     * @param string $dbString
     * 形式一：getRow(['id'=>'1'])
     * 形式二：getRow('id=1')，不推荐，用不上预处理
     */
    protected function getRow($where, $fields = array(), $order = null, $table = null, $dbString = null, $master = false){
        if (! $where) {
            $this->setErrorNo(ERROR_PARAM);
            return array();
        }
        
        if (! $table){
            $table = $this->_table;
        }
        if (! $dbString){
            $dbString = $this->_dbString;
        }
        $result = false;
        try {
            $result = Db::getInstance($dbString)->selectOne($table, $where, $fields, $order, $master);
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function getRow Exception: ".$e->getMessage());
        }
        

        if (false === $result){
            $this->setErrorNo(ERROR_SYS);
            return array();
        }
        
        $this->_decodeTableRow($result);
        
        return $result;
    }
    
    /**
     * 获取多条数据
     * @param unknown $where
     * @param unknown $fields
     * @param string $order
     * @param string $limit
     * @param string $table
     * @param string $dbString
     * 形式一：getRows(['id'=>'1'])
     * 形式二：getRows('id=1')，不推荐，用不上预处理
     */
    protected function getRows($where, $fields = array(), $order = null, $limit = null, $table = null, $dbString = null, $master = false){

        if (! $where) {
            $this->setErrorNo(ERROR_PARAM);
            return array();
        }
        
        if (! $table){
            $table = $this->_table;
        }
        if (! $dbString){
            $dbString = $this->_dbString;
        }
        $result = false;
        try {
            $result = Db::getInstance($dbString)->selectAll($table, $where, $fields, $order, $limit, $master);
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function getRows Exception: ".$e->getMessage());
        }
        

        if (false === $result){
            $this->setErrorNo(ERROR_SYS);
            return array();
        }
        
        foreach ($result as &$row) {
            $this->_decodeTableRow($row);
        }
        unset($row);
        
        return $result;
    }
    
    /**
     * 按照sql获取数据
     * @param unknown $sql
     * @param string $dbString
     * @param boolean $master 是否从主库获取
     * @param array $bindArr 临时新增预处理绑定值数组
     * @return array
     */
    protected function getRowsBySql($sql, $dbString = '', $master = false, $bindArr = array()) {
        if (! $sql) {
            $this->setErrorNo(ERROR_PARAM);
            return array();
        }
        
        if (! $dbString) {
            $dbString = $this->_dbString;
        }
        $result = false;
        try {
            $result = Db::getInstance($dbString)->setlectBySql($sql, $master, $bindArr);
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function getRowsBySql Exception: ".$e->getMessage());
        }
        
        
        if (false === $result) {
            $this->setErrorNo(ERROR_SYS);
            return array();
        }
        
        foreach ($result as &$row) {
            $this->_decodeTableRow($row);
        }
        unset($row);
        return $result;
    }
    
    /**
     * 按照sql获取单条数据
     * @param unknown $sql
     * @param string $dbString
     * @param string $master
     * @param array $bindArr 临时新增预处理绑定值数组
     * @return array 
     */
    protected function getRowBySql($sql, $dbString = '', $master = false, $bindArr = array()) {
        $result = $this->getRowsBySql($sql, $dbString, $master, $bindArr);
        
        if (empty($result)) {
            return array();
        }
        
        return $result[0];
    }
    
    /**
     * 从缓存中获取数据
     * @param unknown $mcKey
     * @param string $redisString
     */
    protected function getMcRow($mcKey, $redisString = '') {
        if (! $redisString) {
            $redisString = $this->_redisString;
        }
        $result = false;
        try {
            $row = RedisClient::instance($redisString)->get($mcKey);
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function getMcRow Exception: ".$e->getMessage());
        }
        
        
        if (false === $row) {
            return false;
        }
        
        if (! is_numeric($row)) {
            $row = unserialize($row);
        }
        
        return $row;
        
    }
    
    /**
     * 设置缓存
     * @param unknown $mcKey
     * @param unknown $row
     * @param number $time 若time＝0，表示永久缓存
     * @param unknown $redisString
     */
    protected function setMcRow($mcKey, $row, $time = 0, $redisString = '') {
        if (! $redisString) {
            $redisString = $this->_redisString;
        }
        
        if (! is_numeric($row)) {
            $row = serialize($row);
        }
        try {
            if ($time > 0) {
                return RedisClient::instance($redisString)->setex($mcKey, $time, $row);
            } else {
                return RedisClient::instance($redisString)->set($mcKey, $row);
            }
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function setMcRow Exception: ".$e->getMessage());
        }
        return false;

    }
    
    /**
     * 删除mc缓存
     * @param unknown $mcKey
     * @param string $redisString
     */
    protected function deleteMcRow($mcKey, $redisString = '') {
        if (! $redisString) {
            $redisString = $this->_redisString;
        }
        try {
            return RedisClient::instance($redisString)->del($mcKey);
        } catch (Exception $e) {
            Fn::writeLog("数据库操作失败：Base.php/function deleteMcRow Exception: ".$e->getMessage());
        }
        return false;

    }
    
    
//-----------------------------------------内部方法-----------------------------------------//   
    
    /**
     * 解析数据库语句
     *
     * @param array() $row
     * @return bool
     */
    private function _encodeTableRow(&$row)
    {
        if (! $this->table_status || ! $row){
            return true;
        }
        foreach ($row as $k=>$v){
            if (! isset($this->table_status[$k])){
                return false;
            }
    
            if ($this->table_status[$k] == 'i'){
                $row[$k] = $v+0;
            }
            elseif ($this->table_status[$k] == 's'){
                $row[$k] = $v . '';
            }
            elseif ($this->table_status[$k] == 'x'){
                if (! is_array($v)){
                    return false;
                }
                $row[$k] = json_encode($v);
            }
        }
    
        return true;
    }
    
    /**
     * 解析从数据库中获取的数据
     *     - 需要配置$table_status 变量，该变量定义了
     *
     * @param array() $row
     * @return array()
     */
    private function _decodeTableRow(&$row)
    {
        if (! $this->table_status || ! is_array($row) || ! $row){
            return true;
        }
        foreach ($row as $k=>$v){
            $type = isset($this->table_status[$k]) ? $this->table_status[$k] : '';
            if ($type)
            {
                if ($this->table_status[$k] == 'x'){
                    if (! $v){
                        $row[$k] = array();
                    }
                    else{
                        $row[$k] = json_decode($v, true);
                    }
                }
            }
        }
    
        return true;
    }
}
?>