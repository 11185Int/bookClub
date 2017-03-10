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
        $content = file_get_contents($url);
        $book = json_decode($content, true);
        if (isset($book['code'])) {
            return [];
        }
        return $book;
    }

}