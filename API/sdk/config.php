<?php

define('SECRET_KEY', 'kfdlmwnbvs35cid2');

abstract class DBConfig
{
    const HOST = 'localhost';
    const PORT = '3306';
    const USER = 'root';
    const PASS = 'root';
    const DB   = 'ionpay';
}

abstract class ApiResponse
{
    const STATUS_OK          = 0;
    const INVALID_HASH       = 1;
    const INVALID_REQUEST    = 2;
    const INVALID_IDENTIFIER = 3;
    const INVALID_PRODUCT    = 4;
    const ERROR_OTHER        = 5;
    const ERROR_TECHNICAL    = 6;
}

class ApiException extends Exception
{
    function __construct($message, $code = ApiResponse::ERROR_TECHNICAL)
    {
        parent::__construct($message, $code);
    }
}