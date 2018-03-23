<?php

namespace CP\common;

class Account extends AbstractModel
{
    protected $_accountKey = null;

    function __construct()
    {
        parent::__construct();

        $this->_accountKey = new AccountSessionKey();
    }

    public function login($params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );

        $code = isset($params['code']) ? $params['code'] : '';

        if (empty($code)) {
            $res['status'] = 10000;
            $res['message'] = '参数不全';
            return $res;
        }

        list($key, $openid, $session_key) = $this->_accountKey->generateKey($code);

        if (!$key) {
            $res['status'] = 99999;
            $res['message'] = '参数错误';
            return $res;
        }

        $data = [
            'openid' => $openid,
            'nickname' => isset($params['nickname']) ? $params['nickname'] : '',
            'sex' => isset($params['sex']) ? $params['sex'] : '',
            'city' => isset($params['city']) ? $params['city'] : '',
            'country' => isset($params['country']) ? $params['country'] : '',
            'province' => isset($params['province']) ? $params['province'] : '',
            'headimgurl' => isset($params['headimgurl']) ? $params['headimgurl'] : '',
        ];
        $this->_accountKey->updateUserInfo($openid, $data);

        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        $res['data']['realname'] = empty($user['realname']) ? '' : $user['realname'];

        $group_amount = $this->capsule->table('user_group')->where('openid', $openid)->count();
        $res['data']['group_amount'] = $group_amount;

        $res['data']['key'] = $key;
        return $res;
    }

    /**
     * 获取用户详情
     * @param $params
     * @return array
     */
    public function getDetail($params)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
            'data' => array(),
        );

        $key = isset($params['key']) ? $params['key'] : '';
        $openid = $this->_accountKey->getOpenIdByKey($key);

        $detail = $this->capsule->table('user')->where('openid', $openid)->first();
        $res['data'] = $detail ?: [];

        unset($res['data']['openid']);

        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        $res['data']['realname'] = empty($user['realname']) ? '' : $user['realname'];

        $group_amount = $this->capsule->table('user_group')->where('openid', $openid)->count();
        $res['data']['group_amount'] = $group_amount;

        $res['data']['group_id'] = $this->_accountKey->getCurrentGroupIdByKey($key);

        return $res;
    }

    public function rename($openid, $realname)
    {
        $res = array(
            'status' => 0,
            'message' => 'success',
        );

        if (!$realname) {
            $res['status'] = 99999;
            $res['message'] = '参数错误';
            return $res;
        }
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        if ($user['realname']) {
            $res['status'] = 99999;
            $res['message'] = '只能设置一次真实姓名';
            return $res;
        }
        $this->capsule->table('user')->where('openid', $openid)->update(['realname' => $realname]);
        return $res;
    }

    public function isRealNameEmpty($openid)
    {
        $user = $this->capsule->table('user')->where('openid', $openid)->first();
        return empty($user['realname']);
    }

}