<?php

class UserModel extends BaseModel{

	public $_table = "user";
	public $_dbString = "portal";

	public $table_status = array(
	    'id' => 'i',
	    'name' => 's',
	    'title' => 's',
	);
	
	/**
	 * 添加数据
	 * @param array $user
	 */
	public function addUser(array $user)
	{
	    $res = 0;
	    
	    $row['name'] = intval($user['name']);
	    $row['title'] = intval($user['title']);
	    if (!$row['name'] || !$row['title'])
	    {
	        $this->setErrorNo(ERROR_PARAM);
	        return $res;
	    }

	    $ret = $this->add($row);
	    if (!$ret)
	    {
	        return $res;
	    }
	    
	    return 1;
	}
	
	/**
	 * 更新数据
	 * @param array $row
	 */
	public function updateUser(array $row, $uid)
	{
	    $res = false;
	     
	    if(! $row || count($row) <= 1)
	    {
	        $this->setErrorNo(ERROR_PARAM);
	        return $res;
	    }
	
	    $res = $this->update($row, $uid);
	    if(!$res)
	    {
	        $this->setErrorNo(ERROR_SYS);
	        return $res;
	    }
	
	    return $res;
	}
    
	/**
	 * 删除数据
	 * @param unknown $id
	 */
	public function deleteUser($id)
	{
	    $id = $id + 0;
	    if($id <= 0)
	        return false;
	
	    $ret = $this->delete($id);
	    if(! $ret)
	        return false;

	    return $ret;
	}
	
	/**
	 * 获取全部数据
	 */
	public function getAllUsers()
	{
	    $sql  = "select * from " . $this->_table . " where id = ? order by id desc";
	     
	    return $this->getRows($sql, array('id' => 102));
	}
	
	/**
	 * 获取单条数据 
	*/
	public function getUserByUid($id)
	{
	    $ret = array();
	     
	    $id = intval($id);
	    if(! $id)
	    {
	        $this->setErrorNo(ERROR_PARAM);
	        return $ret;
	    }
	
	    $sql  = "select * from " . $this->_table . " where id = ?";
	    return $this->getRow($sql, array('id' => $id));
	}
	
	/**
	 * 获取多条数据
	 * @param unknown $uidAry
	 */
	public function getUserByUidAry($uidAry)
	{
	    $res  = array();
	    if (!$uidAry || !is_array($uidAry) || count($uidAry) == 0)
	    {
	        $this->setErrorNo(ERROR_PARAM);
	        return $res;
	    }

	    $uidAry = join(',', $uidAry);

	    $sql = "SELECT * FROM {$this->_table} WHERE id IN ($uidAry)";

	    $result = $this->getRows($sql);
	    return $result;
	}
}