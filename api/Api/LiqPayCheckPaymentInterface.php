<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace Pronko\LiqPayApi\Api;


interface LiqPayCheckPaymentInterface
{
    /**
     *
     * @api
     *
     * @param mixed $orderId
     * @return string
     */
    public function check($orderId);
}
