<?php

namespace CP\user;

use CP\common\AbstractModel;

class User extends AbstractModel
{

    public function getOpenIdByUserId($user_id)
    {
        if (!$user_id) {
            return '';
        }
        $user = $this->capsule->table('user')->where('id', $user_id)->first();
        if (!$user) {
            return '';
        }
        return $user['openid'];
    }

}