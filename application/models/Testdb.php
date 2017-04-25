<?php
class TestdbModel extends BaseModel{
    public $_table = "ac_info";
    public $_dbString = "portal";
    
    
    
  
    
    
    public function getRowtest($where)
    {
        return $this->getRow($where);
    }
    
    public function getRowstest($where)
    {
        return $this->getRows($where);
    }
    
    public function inserttest($data)
    {
        return $this->add($data);
    }
    
    public function addGetInsertIdtest($row)
    {
        return $this->addGetInsertId($row);
    }
    
    public function updatetest($row, $where)
    {
        return $this->update($row, $where);
    }
    
    public function deletetest($where)
    {
        return $this->delete($where);
    }
    
    public function getRowsBySqltest($sql, $bindArr)
    {
        return $this->getRowsBySql($sql, '', false, $bindArr);
    }
    
    public function getRowBySqltest($sql, $bindArr)
    {
        return $this->getRowBySql($sql, '', false, $bindArr);
    }
    
    public function updateBySql($sql, $bindArr)
    {
        return $this->update($sql, $bindArr);
    }
    
}
