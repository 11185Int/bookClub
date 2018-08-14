<?php

namespace CP\common;

use Slim\Http\Request;

class Poster extends AbstractModel
{
    /**
     * @param $request Request
     */
    public function addPosterHistory($request)
    {
        $openKey = new OpenKey();
        $account = new AccountSessionKey();
        $openid = $account->getOpenIdByKey($request->getParam('key'));
        $groupId = $openKey->getRealId($request->getParam('group_id')) ?: 0;
        $user_id = $account->getUserIdByOpenid($openid);
        $now = time();
        $insert = [
            'user_id' => $user_id,
            'openid' => $openid,
            'group_id' => $groupId,
            'short' => $request->getParam('short') ? 1 : 0,
            'request_date' => date('Y-m-d H:i:s', $now),
            'request_time' => $now,
        ];
        $this->capsule->table('poster_history')->insert($insert);
        return;
    }

}