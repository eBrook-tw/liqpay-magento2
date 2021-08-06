<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace Pronko\LiqPayRedirect\Block;

use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Pronko\LiqPayApi\Api\Data\PaymentActionInterface;
use Pronko\LiqPayGateway\Gateway\Config;
use Pronko\LiqPayRedirect\Model\LiqPayServer;
use Pronko\LiqPaySdk\Api\VersionInterface;

class SubmitForm extends Template
{
    protected $_order = null;

    /**
     * @var LiqPayServer
     */
    private LiqPayServer $liqPayServer;

    private Config $config;

    public function __construct(
        Template\Context $context,
        LiqPayServer $liqPayServer,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->liqPayServer = $liqPayServer;
        $this->config = $config;
    }

    /**
     * @return Order
     * @throws \Exception
     */
    public function getOrder()
    {
        if ($this->_order === null) {
            throw new \Exception('Order is not set');
        }
        return $this->_order;
    }

    public function setOrder(Order $order)
    {
        $this->_order = $order;
    }

    /**
     * @return false
     */
    protected function _loadCache()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getLiqpayForm()
    {
        return $this->_toHtml();
    }

    protected function _toHtml()
    {
        $order = $this->getOrder();

        $html = $this->liqPayServer->cnbForm([
            'action' => PaymentActionInterface::PAY,
            'version' => VersionInterface::VERSION,
            'amount' => $order->getGrandTotal(),
            'currency' => $order->getOrderCurrencyCode(),
            'description' => $this->liqPayServer->getLiqPayDescription($order),
            'order_id' => $this->config->getOrderPrefix() . $order->getIncrementId() . $this->config->getOrderSuffix(),
        ]);
        return $html;
    }
}
