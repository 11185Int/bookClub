<?php


namespace CP\common;

abstract class AbstractModel
{
    protected $_db_prefix = 'tb_';
    public $db = null;
    protected $capsule = null;

    public function __construct()
    {
        $capsule = new \Illuminate\Database\Capsule\Manager();
        require __DIR__ . '/../../../config/database.php';
        $config = isset($DB_CONFIG) ? $DB_CONFIG: [];
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $config['host'],
            'port'      => $config['port'],
            'database'  => $config['dbname'],
            'username'  => $config['user'],
            'password'  => $config['pass'],
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => 'tb_',
        ]);
        $capsule->setAsGlobal();
        $capsule->setFetchMode(\PDO::FETCH_ASSOC);
        $this->capsule = $capsule;
    }

    public function getUserIdByOpenid($openid)
    {
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        $user = $user ?: [];
        return isset($user['id']) ? $user['id'] : 0;
    }

}