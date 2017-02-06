<?php

namespace CP\database;

class Mysql
{
    
    function getConnection() {
        require __DIR__ . '/../../../config/database.php';
        $config = $DB_CONFIG;
        $dbhost = $config['host'];
        $dbuser = $config['user'];
        $dbpass = $config['pass'];
        $dbname = $config['dbname'];
        $dbh = new \PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
        $dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $dbh;
    }

}