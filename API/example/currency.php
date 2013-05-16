<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

include '../sdk/currency.php';
include 'lib/rb.php';

function generateRandomString($length = 10)
{
    $characters   = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}


class CurrencyApi extends CurrencyIonpayApi
{
    /**
     * Настройка базы данных
     */
    public function initDB()
    {
        R::setup('mysql:host=localhost; dbname=TestClientDB1', 'root', 'CulPedEcca');
    }

    /**
     * проверка наличия пользователя в системе по его идентификатору.
     */
    public function checkUser($identifier)
    {
        if (R::findOne('user', 'username = ? ', [$identifier])) {
            self::sendResponse('ok');
        } else {
            self::sendResponse('User was not found.', ApiResponse::INVALID_IDENTIFIER);
        }
    }

    /**
     * процесс оплаты
     */
    public function pay($sum, $transactionId, $activationType, $identifier = null)
    {

        /**
         * Проверка на повторый запрос об оплате, осуществляетсья по id транзакции передаваемым с ionpay сервера.
         */
        if ($transaction = R::findOne('transaction', "transaction_id= ? AND status IN ('complete', 'sent')", [$transactionId])) {
            throw new ApiException(sprintf('transaction "%s" already process', $transactionId));
        }

        /**
         * Добавления id транзакции к записи о совершении оплаты.
         */
        $transaction = R::dispense('transaction');
        $transaction->setAttr('transaction_id', $transactionId);

        $result = [];
        /**
         * Выбранный вариан зачисление валюты
         */
        switch ($activationType) {
            /**
             * Активации по коду
             */
            case self::ACTIVATION_TYPE_SEND_CODE:
                $code = R::dispense('user2code');
                $code->setAttr('value', $sum);
                $code->setAttr('hash', generateRandomString(30));

                $transaction->setAttr('status', 'sent');
                $transaction->setAttr('type', 'code');

                R::store($code);

                $result['userCode'] = $code->hash;
                break;
            /**
             * Активации в момент запроса
             */
            case self::ACTIVATION_TYPE_ON_REQUEST:
                $user = R::findOne('user', 'username = ? ', [$identifier]);

                if (!$balance = R::findOne('user2balance', 'user_id = ? ', [$user->getID()])) {
                    $balance = R::dispense('user2balance');
                    $balance->setAttr('user', $user);
                    $balance->setAttr('value', $sum);
                } else {
                    $balance->setAttr('value', $sum + $balance->value);
                }

                $transaction->setAttr('status', 'complete');
                $transaction->setAttr('type', 'request');

                R::store($balance);

                break;
        }

        $result['id'] = R::store($transaction);

        self::sendResponse($result);
    }
}

//code

$api = new CurrencyApi('03c1a49e98a2efee4fc961f4e82e1312');

$api->process();

