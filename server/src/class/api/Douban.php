<?php

namespace CP\api;

class Douban
{

    public function getBook($isbn)
    {
        if (!$isbn) {
            return [];
        }
        $url = 'https://api.douban.com/v2/book/isbn/'. $isbn;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $content = curl_exec($ch);
        curl_close ($ch);
        $book = json_decode($content, true);
        if (isset($book['code'])) {
            return [];
        }
        return $book;
    }

}