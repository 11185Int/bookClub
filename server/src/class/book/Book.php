<?php

/**
 * Created by PhpStorm.
 * User: Du
 * Date: 2017/2/7
 * Time: 8:43
 */
namespace CP\book;

use CP\common\AbstractModel;

class Book extends AbstractModel {

    public function getList() {
        $res = array(
            'status' => 1,
            'message' => 'success',
        );
        $this->db->sql('SELECT b.id,b.isbn10,b.isbn13,b.title,b.image,IF(sum(IF(s.share_status = 1,1,0)) > 0,1,0) as share_status,IF(SUM(IF(s.lend_status = 1,1,0)) > 0,1,0) as lend_status
                        FROM tb_book b LEFT JOIN tb_book_share s ON b.id = s.book_id GROUP BY b.isbn10');
        $res['data ']['list'] = $this->db->getResult();
        return $res;
    }

}