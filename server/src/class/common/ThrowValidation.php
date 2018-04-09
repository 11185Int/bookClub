<?php

namespace CP\common;

use Slim\Http\Request;
use Slim\Http\Response;

class ThrowValidation
{

    public function __invoke($request, $response, $next)
    {
        $errors = $request->getAttribute('errors');
        if (!empty($errors)) {
            throw new \Exception($this->_printErrors($errors), 99999);
        }

        return $next($request, $response);
    }

    /**
     * @param $errors
     * @return string
     */
    protected function _printErrors($errors)
    {
        foreach ($errors as $p => $str) {
            return $p. " ". reset($str);
        }
        return "";
    }


}
