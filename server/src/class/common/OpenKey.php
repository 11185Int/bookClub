<?php

namespace CP\common;

class OpenKey extends AbstractModel
{
    const TABLE = 'open_key';
    const TYPE_USER_ID = 'user_id';
    const TYPE_GROUP_ID = 'group_id';

    private $_openKeyCache = [];

    public function getRealId($open_key)
    {
        $first = $this->capsule->table(self::TABLE)->where('open_key', $open_key)->first();
        if ($first) {
            return $first['real_id'];
        }
//        return $open_key;
        return 0;
    }

    public function getOpenKey($real_id, $type = self::TYPE_USER_ID)
    {
        if (!$real_id) {
            return '';
        }
        if (!empty($this->_openKeyCache[$type][$real_id])) {
            return $this->_openKeyCache[$type][$real_id];
        }
        $first = $this->capsule->table(self::TABLE)->where('real_id', $real_id)->where('type', $type)->first();
        if ($first) {
            //todo validate expired time
            $open_key = $first['open_key'];
        } else {
            $open_key = $this->createOpenKey($real_id, $type);
        }
        $this->_openKeyCache[$type][$real_id] = $open_key;
        return $open_key;
    }

    public function prepareOpenKeyCache($real_id_list, $type = self::TYPE_USER_ID)
    {
        $openKeys = $this->capsule->table(self::TABLE)->where('type', $type)->whereIn('real_id', $real_id_list)->get();
        $exist_real_id_list = [];
        foreach ($openKeys as $item) {
            $exist_real_id_list[] = $item['real_id'];
            $this->_openKeyCache[$type][$item['real_id']] = $item['open_key'];
        }
        $no_exist_real_id_list = array_diff($real_id_list, $exist_real_id_list);
        if (count($no_exist_real_id_list) > 0) {
            foreach ($no_exist_real_id_list as $real_id) {
                $open_key = $this->createOpenKey($real_id, $type);
                $this->_openKeyCache[$type][$real_id] = $open_key;
            }
        }
        return $this;
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
