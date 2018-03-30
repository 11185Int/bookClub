<?php


namespace CP\common;

use Illuminate\Database\Capsule\Manager;

abstract class AbstractModel
{
    protected $_db_prefix = 'tb_';
    protected $capsule = null;
    protected $app = null;

    public function __construct($app = null)
    {
        if ($app) {
            $this->app = $app;
        }
        $capsule = new Manager();
        require __DIR__ . '/../../../config/database.php';
        $config = isset($DB_CONFIG) ? $DB_CONFIG: [];
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $config['host'],
            'port'      => $config['port'],
            'database'  => $config['dbname'],
            'username'  => $config['user'],
            'password'  => $config['pass'],
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix'    => $this->_db_prefix,
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

    public function replaceRealName($array)
    {
        if (empty($array)) {
            return $array;
        }
        $data = [];
        foreach ($array as $item) {
            if (!empty($item['realname'])) {
                $item['nickname'] = $item['realname'];
            }
            $data[] = $item;
        }
        return $data;
    }

}