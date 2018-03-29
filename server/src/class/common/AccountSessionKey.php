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
        return array($key, $openid, $session_key);
    }

    public function getUserInfo($openid) {
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        return $user ?: [];
    }

    public function updateUserInfo($openid, $data) {
        $userinfo = $this->getUserInfo($openid);
        if (empty($userinfo)) {
            $this->capsule->table('user')->insert($data);
        } else {
            $this->capsule->table('user')->where('openid', $openid)->update($data);
        }
    }

    /**
     * @param $key string 3rd session key
     * @return string 账户对应openid
     */
    public function getOpenIdByKey($key) {
        $kv = $this->_getKV($key);
        return isset($kv['openid']) ? $kv['openid'] : null;
    }

    public function getCurrentGroupIdByKey($key) {
        $openid = $this->getOpenIdByKey($key);
        if (!$openid) {
            return 0;
        }
        $user_group = $this->capsule->table('user_group')->where('openid', $openid)->where('is_current', 1)->first();
        return isset($user_group['group_id']) ? $user_group['group_id'] : 0;
    }

    public function getCurrentGroupNameByKey($key)
    {
        $group_id = $this->getCurrentGroupIdByKey($key);
        if (!$group_id) {
            return '';
        }
        $group = $this->capsule->table('group')->find($group_id);
        return $group['group_name'];
    }

    /**
     * @param $key string 3rd session key
     * @return string 微信服务器的session_key
     */
    public function getSessionKeyByKey($key) {
        $kv = $this->_getKV($key);
        return isset($kv['session_key']) ? $kv['session_key'] : null;
    }

    protected function _fetchUserInfoFromWechat($openid) {

        $wechat = new Wechat();
        $userinfo = $wechat->getUserInfo($openid);

        return [
            'openid' => $userinfo['openid'],
            'nickname' => $userinfo['nickname'],
            'sex' => $userinfo['sex'],
            'city' => $userinfo['city'],
            'country' => $userinfo['country'],
            'province' => $userinfo['province'],
            'headimgurl' => $userinfo['headimgurl'],
        ];
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
            'expired' => time() + 7 * 24 * 3600,
            'enable' => 1,
        );
        //将旧的kv设置失效
        $this->capsule->table('session')->where('openid', $openid)->update(['enable' => 0]);

        $res = $this->capsule->table('session')->insert($kv);
        return $res;
    }

    protected function _getKV($key) {

        $kv = array();
        do {
            if (!$key) {
                throw new \Exception('缺少KEY参数', 20001);
            }
            $kv = $this->capsule->table('session')->where('key', $key)->first();
            $kv = $kv ?: [];
            if (empty($kv)) {
                break;
            }
            $expired = $kv['expired'];
            $enable = $kv['enable'];
            if ($expired < time() || !$enable) {
                //do not delete session when its expired
                //$this->db->delete($this->getTableName('session'), $where);
                break;
            }
        } while (0);

        if (empty($kv)) {
            throw new \Exception('KEY错误或者已过期', 20000);
        }

        return $kv;
    }


}