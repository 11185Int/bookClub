<?php

namespace CP\common;

use Slim\Http\Request;
use Slim\Http\Response;

class Logger extends AbstractModel
{

    public function log(Request $request, Response $response)
    {
        try {
            $client_ip = $this->getClientIp();
            $uri = $request->getUri();
            $openid = '';

            $p1 = $request->getParsedBody() ?: [];
            $p2 = $request->getQueryParams() ?: [];

            $params = array_merge($p1, $p2);

            $body = $response->getBody();
            $logData = [
                'action' => $uri->getPath(),
                'data' => http_build_query($params),// json_encode($request->getQueryParams()),
                'posttime' => date('Y-m-d H:i:s', time()),
                'openid' => $openid ?: '',
                'returndata' => addslashes((string)$body),
                'userip' => $client_ip,
            ];
            $this->save($logData);
        } catch (\Exception $e) {

        }
    }

    public function save($data)
    {
        $this->capsule->table('log')->insert($data);
    }

    /**
     * 获取客户端IP地址
     * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    public function get_client_ip($type = 0,$adv=false) {
        $type       =  $type ? 1 : 0;
        static $ip  =   NULL;
        if ($ip !== NULL) return $ip[$type];
        if($adv){
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr    =   explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos    =   array_search('unknown',$arr);
                if(false !== $pos) unset($arr[$pos]);
                $ip     =   trim($arr[0]);
            }elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip     =   $_SERVER['HTTP_CLIENT_IP'];
            }elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip     =   $_SERVER['REMOTE_ADDR'];
            }
        }elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip     =   $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u",ip2long($ip));
        $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
        return $ip[$type];
    }

    public function getClientIp()
    {
        return $this->get_client_ip(1) ? $this->get_client_ip(0) : '';
    }
}