<?php

namespace Pronko\LiqPayApi\Model;

use Magento\Framework\DB\Transaction;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Repository;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
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
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $_transaction;

    private Config $config;

    private Logger $logger;
    /**
     * @var LiqPayServer
     */
    private LiqPayServer $liqPayServer;
    /**
     * @var BuilderInterface
     */
    protected BuilderInterface $_transactionBuilder;
    /**
     * @var Repository
     */
    protected Repository $_paymentRepository;
    /**
     * @var Order\Payment\Transaction\Repository
     */
    protected Order\Payment\Transaction\Repository $_transactionRepository;

    public function __construct(
        Order $order,
        InvoiceService $invoiceService,
        Transaction $transaction,
        BuilderInterface $transactionBuilder,
        Repository $paymentRepository,
        Order\Payment\Transaction\Repository $transactionRepository,
        Logger $logger,
        Config $config,
        LiqPayServer $liqPayServer
    )
    {
        $this->_order = $order;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->logger = $logger;
        $this->config = $config;
        $this->liqPayServer = $liqPayServer;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_paymentRepository = $paymentRepository;
        $this->_transactionRepository = $transactionRepository;
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
                    if ($this->config->isCreateInvoice($order->getStoreId())) {
                        if ($order->canInvoice()) {
                            $invoice = $this->_invoiceService->prepareInvoice($order);
                            $invoice->register()->pay();
                            $transactionSave = $this->_transaction->addObject(
                                $invoice
                            )->addObject(
                                $invoice->getOrder()
                            );
                            $transactionSave->save();
                        }
                    }
                    // Transaction Id
                    $payment = $order->getPayment();
                    $this->_createTransaction($order, $result, $payment);

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

    /**
     * @param null|Order $order
     * @param array $paymentData
     * @param null $payment
     */
    protected function _createTransaction($order = null, $paymentData = [], $payment = null)
    {
        $transactionId = $paymentData['transaction_id'] ?? '';
        if (!$transactionId) {
            return;
        }

        try {
            $payment->setLastTransId($transactionId);
            $payment->setTransactionId($transactionId);
            $payment->setAdditionalInformation($paymentData);

            //get the object of builder class
            $trans = $this->_transactionBuilder;
            $transaction = $trans->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($transactionId)
                ->setFailSafe(true)
                ->build(Order\Payment\Transaction::TYPE_CAPTURE);

            $payment->setParentTransactionId(null);
            $this->_paymentRepository->save($payment);

            $transaction = $this->_transactionRepository->save($transaction);

            return $transaction->getTransactionId();
        } catch (\Exception $e) {
            //log errors here
            $this->logger->debug(["Transaction Exception: There was a problem with creating the transaction. " . $e->getMessage()]);
        }
    }
}
