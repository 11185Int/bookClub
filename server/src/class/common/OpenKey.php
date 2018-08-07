<?php

namespace CP\common;

class OpenKey extends AbstractModel
{
    const TABLE = 'open_key';
    const TYPE_USER_ID = 'user_id';
    const TYPE_GROUP_ID = 'group_id';

    public function getRealId($open_key)
    {
        $first = $this->capsule->table(self::TABLE)->where('open_key', $open_key)->first();
        if ($first) {
            return $first['real_id'];
        }
        return $open_key;
    }

    public function getOpenKey($real_id, $type = self::TYPE_USER_ID)
    {
        $first = $this->capsule->table(self::TABLE)->where('real_id', $real_id)->where('type', $type)->first();
        if ($first) {
            //todo validate expired time
            return $first['open_key'];
        } else {
            return $this->createOpenKey($real_id, $type);
        }
    }

    public function getOpenKeyList($real_id_list, $type = self::TYPE_USER_ID)
    {

        return [];
    }

    public function createOpenKey($real_id, $type = self::TYPE_USER_ID, $expired = 0)
    {
        $open_key = $this->generateOpenKey($real_id, $type);
        $insert = [
            'real_id' => $real_id,
            'type' => $type,
            'open_key' => $open_key,
            'expired' => $expired,
        ];
        $res = $this->capsule->table(self::TABLE)->insert($insert);
        if ($res) {
            return $open_key;
        }
        return '';
    }

    public function isRealIdExist($real_id, $type)
    {
        $exist = $this->capsule->table(self::TABLE)->where('real_id', $real_id)->where('type', $type)->exists();
        return $exist;
    }

    public function generateOpenKey($real_id, $type = self::TYPE_USER_ID)
    {
        $random = rand(10000,99999);
        $str = "open-key-{$real_id}-{$type}-{$random}";
        $key = md5($str);
        return $key;
    }
}
