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

        $key = $this->_accountKey->generateKey($code);

        if (!$key) {
            $res['status'] = 99999;
            $res['message'] = '参数错误';
        }

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

        $detail = $this->fetch('user', "openid = '{$openid}'");
        $res['data'] = $detail ?: [];

        return $res;
    }
}