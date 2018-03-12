<?php

namespace CP\common;

class AccessList extends AbstractModel
{
    protected $_accountKey = null;

    function __construct()
    {
        parent::__construct();

        $this->_accountKey = new AccountSessionKey();
    }

    public function checkAccess($openid)
    {
        $accessList = $this->capsule->table('access_list')->select('openid')->get();
        $accessList = array_column($accessList, 'openid');
        if (!empty($accessList)) {
            return in_array($openid, $accessList);
        }
        return true;
    }

}