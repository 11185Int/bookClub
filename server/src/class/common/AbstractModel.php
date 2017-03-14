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
        mysql_query('SET NAMES "utf8"');


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

    public function update($table, $key_values, $where) {
        if (!$table || empty($key_values)) {
            return false;
        }

        return $this->db->update($this->getTableName($table), $key_values, $where);
    }

    public function delete($table, $where = array()) {

    }

    public function fetch($table, $where = array(), $order = null) {
        if (!$table) {
            return false;
        }

        $this->db->getResult();
        $this->db->select($this->getTableName($table), '*', null, $where, $order, '1');
        $result = $this->db->getResult();

        return empty($result) ? null : reset($result);
    }

    public function fetchAll($table, $where = array()) {

    }

    protected function getTableName($table) {
        return $this->_db_prefix. $table;
    }

    public function getUserIdByOpenid($openid)
    {
        $user = $this->fetch('user', "openid = '{$openid}'");
        return isset($user['id']) ? $user['id'] : 0;
    }

}