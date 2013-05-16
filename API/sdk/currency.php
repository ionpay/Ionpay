<?php

include 'config.php';

abstract class CurrencyIonpayApi
{
    const ACTIVATION_TYPE_SEND_CODE  = 1;
    const ACTIVATION_TYPE_ON_REQUEST = 2;

    const ACTION_CHECK_SERVER   = 'checkServer';
    const ACTION_CHECK_USER     = 'checkUser';
    const ACTION_PAY_ON_REQUEST = 'payRequest';
    const ACTION_PAY_SEND_CODE  = 'payCode';

    const REQUEST_VARIABLE_SUM            = 'sum';
    const REQUEST_VARIABLE_TRANSACTION_ID = 'transaction_id';
    const REQUEST_VARIABLE_ACTION         = 'action';
    const REQUEST_VARIABLE_IDENTIFIER     = 'identifier';
    const REQUEST_VARIABLE_HASH           = 'hash';

    public function __construct()
    {
        set_exception_handler(array($this, 'errorHandler'));
    }

    public function checkServer()
    {
        self::sendResponse('ok');
    }

    abstract public function initDB();

    abstract public function checkUser($identifier);

    abstract public function pay($sum, $transactionId, $activationType, $identifier = null);

    public function process()
    {
        $this->initDB();
        $this->checkHash();

        list($action) = $this->checkParams([self::REQUEST_VARIABLE_ACTION]);
        switch ($action) {
            case self::ACTION_CHECK_SERVER:
                $this->checkServer();
                break;
            case self::ACTION_CHECK_USER:
                list($identifier) = $this->checkParams([self::REQUEST_VARIABLE_IDENTIFIER]);
                $this->checkUser($identifier);
                break;
            case self::ACTION_PAY_ON_REQUEST:
                list($sum, $transactionId, $identifier) = $this->checkParams([self::REQUEST_VARIABLE_SUM, self::REQUEST_VARIABLE_TRANSACTION_ID, self::REQUEST_VARIABLE_IDENTIFIER]);
                $this->pay($sum, $transactionId, self::ACTIVATION_TYPE_ON_REQUEST, $identifier);
                break;
            case self::ACTION_PAY_SEND_CODE:
                list($sum, $transactionId) = $this->checkParams([self::REQUEST_VARIABLE_SUM, self::REQUEST_VARIABLE_TRANSACTION_ID]);
                $this->pay($sum, $transactionId, self::ACTIVATION_TYPE_SEND_CODE);
                break;
            default:
                throw new ApiException(sprintf('undefined action "%s".', $action), ApiResponse::INVALID_REQUEST);
        }
    }

    protected function checkParams(array $params, $post = true)
    {
        $errors = [];
        $result = [];

        $request = $post ? $_POST : $_GET;
        foreach ($params as $param) {
            if (isset($request[$param])) {
                $result[] = $request[$param];
            } else {
                $errors[] = $param;
            }
        }

        if (count($errors)) {
            throw new ApiException(sprintf('params "%s" are required.', implode('",', $errors)), ApiResponse::INVALID_REQUEST);
        }

        return $result;
    }

    protected function checkHash()
    {
        list($hash) = $this->checkParams([self::REQUEST_VARIABLE_HASH], false);

        if ($hash != sha1(implode('', $_POST) . SECRET_KEY)) {
            throw new ApiException('invalid signature.', ApiResponse::INVALID_HASH);
        }
    }

    public static function sendResponse($result, $status = ApiResponse::STATUS_OK)
    {
        header('Content-type: application/json; charset=utf-8');
        echo  html_entity_decode(json_encode(['result' => $result, 'status' => $status]), ENT_COMPAT, 'UTF-8');
        exit;
    }

    public static function errorHandler(Exception $e)
    {
        self::sendResponse($e->getMessage(), $e->getCode());
    }
}