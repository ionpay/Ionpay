<?php

error_reporting(E_ALL);
ini_set('display_errors', 'On');

include '../sdk/product.php';
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


class ProductApi extends ProductIonpayApi
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
     * проверка наличия продукта в системе по его идентификатору.
     */
    public function checkProduct($productCode)
    {
        if (R::findOne('product', 'code = ? ', [$productCode])) {
            self::sendResponse('ok');
        } else {
            self::sendResponse('Product was not found.', ApiResponse::INVALID_PRODUCT);
        }
    }

    /**
     * процесс оплаты
     */
    public function pay($productCode, $transactionId, $activationType, $identifier = null)
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

        $result  = [];
        $product = R::findOne('product', 'code = ?', [$productCode]);
        if ($product) {
            /**
             * Выбранный вариан зачисление валюты
             */
            switch ($activationType) {
                /**
                 * Активации по коду
                 */
                case self::ACTIVATION_TYPE_SEND_CODE:
                    $productCode = R::dispense('user2code');
                    $productCode->setAttr('product', $product);
                    $productCode->setAttr('hash', generateRandomString(30));

                    R::store($productCode);

                    $result['userCode'] = $productCode->hash;
                    break;
                /**
                 * Активации в момент запроса
                 */
                case self::ACTIVATION_TYPE_ON_REQUEST:
                    $user = R::findOne('user', 'username = ? ', [$identifier]);

                    /**
                     * Таймкод
                     */
                    if ($product->type == 'time') {

                        if (!$prem = R::findOne('user2premium', 'user_id = ? ', [$user->getID()])) {
                            $prem = R::dispense('user2premium');
                            $prem->setAttr('user', $user);
                            $now = time();
                        } else {
                            $now = strtotime($prem->time);
                        }

                        $value = $product->value;
                        $prem->setAttr('time', date('Y-m-d', strtotime("+$value day", $now)));

                        R::store($prem);
                    /**
                     * Продукт
                     */
                    } else if ($product->type == 'item') {
                        $item = R::dispense('user2item');
                        $item->setAttr('user', $user);
                        $item->setAttr('item_id', $product->value);
                        $item->setAttr('time', date('Y-m-d'));

                        R::store($item);
                    }

                    break;
            }
            $result['id'] = R::store($transaction);
            self::sendResponse($result);
        } else {
            self::sendResponse('Product was not found', ApiResponse::INVALID_PRODUCT);
        }
    }
}

//code

$api = new ProductApi('03c1a49e98a2efee4fc961f4e82e1312');

$api->process();

