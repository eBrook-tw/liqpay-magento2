<?php

namespace Pronko\LiqPayApi\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Transaction;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Pronko\LiqPayApi\Api\LiqPayCheckPaymentInterface;
use Pronko\LiqPayGateway\Gateway\Config;
use Pronko\LiqPayRedirect\Model\LiqPayServer;

class LiqPayCheckPayment implements LiqPayCheckPaymentInterface
{
    /**
     * @var Order
     */
    protected $_order;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $_transaction;

    /**
     * @var RequestInterface
     */
    protected $_request;

    private Config $config;

    private Logger $logger;
    /**
     * @var LiqPayServer
     */
    private LiqPayServer $liqPayServer;

    public function __construct(
        Order $order,
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        Logger $logger,
        Config $config,
        RequestInterface $request,
        LiqPayServer $liqPayServer
    ) {
        $this->_order = $order;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_request = $request;
        $this->logger = $logger;
        $this->config = $config;
        $this->liqPayServer = $liqPayServer;
    }

    public function check($orderId)
    {
        $result = $this->liqPayServer->checkOrderPaymentStatus($orderId);

        if ($result && isset($result['status']) && $result['status'] == 'success') {
            $orderId = $result['order_id'] ?? '';
            $order = $this->getOrderById($orderId);

            try {
                if (!($order && $order->getId() && $this->liqPayServer->checkOrderIsLiqPayPayment($order))) {
                    throw new \Exception('Order is not exist!');
                }
                if ($order->getState() == Order::STATE_NEW && $order->getStatus() == 'pending') {
                    $order->addStatusHistoryComment(__('Liqpay payment success.'))
                        ->setIsCustomerNotified(true);
                    $state = Order::STATE_PROCESSING;
                    $order->setState($state);
                    $order->setStatus($state);
                    $order->save();
                }
                return true;
            } catch (\Exception $e) {
                throw $e;
            }
        }
        return false;
    }

    /**
     * @param $orderId
     * @return Order
     */
    protected function getOrderById($orderId): Order
    {
        $orderId = $this->liqPayServer->getOrderId($orderId);
        return $this->_order->loadByIncrementId($orderId);
    }
}
