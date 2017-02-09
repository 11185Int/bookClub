<?php

namespace CP\api;
use EasyWeChat\Foundation\Application;

class Wechat
{

    protected $_wechatApp;
    protected $_app;

    function __construct()
    {
        $options = require __DIR__ . '/../../../config/wechat.php';
        $this->_wechatApp = new Application($options);
        $this->_app = $this->_wechatApp->mini_program;
    }

    /**
     * 根据openid获取单个用户信息
     * @param $openid
     * @return array
     */
    public function getUserInfo($openid)
    {
        $userService = $this->_app->user;
        $user = $userService->get($openid);
        return $user;
    }

    /**
     * jscode换取openid和session_key
     * @param $jscode
     * @return \EasyWeChat\Support\Collection
     */
    public function jscode2session($jscode)
    {
        //mock
        return array('openid'=>'openid'.rand(10000,99999), 'session_key'=>'session_key'.rand(10000,99999));

        $mini = $this->_app;
        $session = $mini->user->getSessionKey($jscode);
        return $session;
    }


}