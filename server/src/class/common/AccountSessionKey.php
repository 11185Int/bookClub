<?php

namespace CP\common;

use CP\api\Wechat;

class AccountSessionKey extends AbstractModel
{

    protected $_keyValue = array();

    /**
     * generate 3rd session key
     * @param $code string 微信服务器返回的那个code
     * @return string 返回我服务器生成的3rd session key
     */
    public function generateKey($code) {

        $fetch = $this->_fetchFromWechat($code);
        if (!$fetch) {
            return false;
        }

        list($openid, $session_key) = $fetch;
        $key = $this->_createKey($openid, $session_key);
        $this->_saveKV($key, $openid, $session_key);
        return $key;
    }

    /**
     * @param $key string 3rd session key
     * @return string 账户对应openid
     */
    public function getOpenIdByKey($key) {
        $kv = $this->_getKV($key);
        return isset($kv['openid']) ? $kv['openid'] : null;
    }

    /**
     * @param $key string 3rd session key
     * @return string 微信服务器的session_key
     */
    public function getSessionKeyByKey($key) {
        $kv = $this->_getKV($key);
        return isset($kv['session_key']) ? $kv['session_key'] : null;
    }

    protected function _fetchFromWechat($code) {
        $wechat = new Wechat();
        $session = $wechat->jscode2session($code);
        if (isset($session['errcode'])) {
            return false;
        }
        return array($session['openid'], $session['session_key']);
    }

    protected function _createKey($openid, $session_key) {
        $arr = array($openid, $session_key, time());
        return md5(implode($arr));
    }

    protected function _saveKV($key, $openid, $session_key)
    {
        $kv = array(
            'key' => $key,
            'openid' => $openid,
            'session_key' => $session_key,
            'expired' => time() + 3600,
        );

        $res = $this->insert('session', $kv);
        return $res;
    }

    protected function _getKV($key) {

        $kv = array();
        do {
            if (!$key) {
                throw new \Exception('缺少KEY参数', 20001);
            }
            $where = "`key` = '$key'";
            $this->db->select($this->getTableName('session'), '*', null, $where);
            $result = $this->db->getResult();
            $kv = reset($result);
            if (empty($kv)) {
                break;
            }
            $expired = $kv['expired'];
            if ($expired < time()) {
                $this->db->delete($this->getTableName('session'), $where);
                break;
            }
        } while (0);

        if (empty($kv)) {
            throw new \Exception('KEY错误或者已过期', 20000);
        }

        return $kv;
    }


}