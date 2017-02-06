<?php


namespace CP\common;
use CP\database\MysqlCrud;

abstract class AbstractModel
{
    protected $_db_prefix = 'tb_';
    public $db = null;
    public function __construct()
    {
        $mysql = new MysqlCrud();
        $mysql->connect();
        $this->db = $mysql;
    }

    public function __destruct()
    {
        $this->db->disconnect();
    }

    public function insert($table, $key_values){
        if (!$table || empty($key_values)) {
            return false;
        }

        $this->db->insert($this->getTableName($table), $key_values);

        return $this->db->getLastInsertID();
    }

    public function update($table, $key_values, $where = array()) {

    }

    public function delete($table, $where = array()) {

    }

    public function fetch($table, $where = array()) {

    }

    public function fetchAll($table, $where = array()) {

    }

    protected function getTableName($table) {
        return $this->_db_prefix. $table;
    }

}