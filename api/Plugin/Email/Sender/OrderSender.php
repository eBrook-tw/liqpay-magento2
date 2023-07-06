<?php

namespace Pronko\LiqPayApi\Plugin\Email\Sender;

class OrderSender
{
    public function beforeSend(
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $subject,
        $order,
        $forceSyncMode = false
    ) {
        if ($order->getPayment()->getMethod() == 'pronko_liqpay' && $order->getState() == 'new') {
            return false;
        }

        return $subject;
    }
}
