<?php

namespace Pronko\LiqPayApi\Plugin\Email\Sender;

use Magento\Sales\Model\Order\Email\Sender;

/**
 * Sends order email to the customer.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderSender
{

    public function aroundSend(
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $subject,
        callable $proceed,
        $order,
        $forceSyncMode = false
    ) {
        if ($order->getPayment()->getMethod() == 'pronko_liqpay' && $order->getState() == 'new') {
            return false;
        } else {
            return $proceed($order,$forceSyncMode);
        }
    }
}