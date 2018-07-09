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
        $userService = $this->_wechatApp->user;
        $user = $userService->get($openid);
        return $user->toArray();
    }

    /**
     * jscode换取openid和session_key
     * @param $jscode
     * @return \EasyWeChat\Support\Collection
     */
    public function jscode2session($jscode)
    {
        //mock
        //return array('openid' => 'openid' . rand(10000, 99999), 'session_key' => 'session_key' . rand(10000, 99999));

        $mini = $this->_app;
        $session = $mini->sns->getSessionKey($jscode);
        return $session;
    }

    public function getWxCodeFileName($openid)
    {
        $time = time();
        $fileNameReal = "qrcode-{$openid}-". date('Y-m-dH:i:s',$time);
        $hashName = md5($fileNameReal).'.png';
        return $hashName;
    }

    /**
     * @param $openid
     * @param $scene
     * @param $page
     * @param $width
     * @param $auto_color
     * @param $line_color
     * @return string
     */
    public function getWxCode($openid, $config, $scene, $page, $width, $auto_color, $line_color)
    {
        $directory = __DIR__. '/../../../public/resources/qrcode/';
        $domain = $config['domain'];
        $filename = $this->getWxCodeFileName($openid);
        $qrcode_url = $domain . 'resources/qrcode/'. $filename;
        if (file_exists($directory.$filename)) {
            return $qrcode_url;
        }
        $mini = $this->_app;
        $qrcode = $mini->qrcode->appCodeUnlimit($scene, $page?:null, $width?:null, $auto_color?:null, $line_color?:null);
        file_put_contents($directory.$filename, $qrcode);
        return $qrcode_url;
    }


}