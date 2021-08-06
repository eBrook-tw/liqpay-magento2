<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace Pronko\LiqPayApi\Model;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Transaction;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Pronko\LiqPayApi\Api\LiqPayCallbackInterface;
use Pronko\LiqPayGateway\Gateway\Config;
use Pronko\LiqPayRedirect\Model\LiqPayServer;

class LiqPayCallback implements LiqPayCallbackInterface
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

    public function callback()
    {
        $post = $this->_request->getParams();

        // add log
        $this->logger->debug(['LiqPay Callback data' => $post]);

        if (!(isset($post['data']) && isset($post['signature']))) {
            $this->logger->debug([__('In the response from LiqPay server there are no POST parameters "data" and "signature"')]);
            return null;
        }

        $data = $post['data'];
        $receivedSignature = $post['signature'];
        $decodedData = $this->liqPayServer->getDecodedData($data);
        $orderId = $decodedData['order_id'] ?? null;
        $receivedPublicKey = $decodedData['public_key'] ?? null;
        $status = $decodedData['status'] ?? null;
        $amount = $decodedData['amount'] ?? null;
        $currency = $decodedData['currency'] ?? null;
        $transactionId = $decodedData['transaction_id'] ?? null;
        $senderPhone = $decodedData['sender_phone'] ?? null;

        try {
            $order = $this->getOrderById($orderId);
            if (!($order && $order->getId() && $this->liqPayServer->checkOrderIsLiqPayPayment($order))) {
                return null;
            }

            // ALWAYS CHECK signature field from Liqpay server!!!!
            // DON'T delete this block, be careful of fraud!!!
            if (!$this->liqPayServer->securityOrderCheck($data, $receivedPublicKey, $receivedSignature)) {
                $order->addStatusHistoryComment(__('LiqPay security check failed!'));
                $this->_orderRepository->save($order);
                return null;
            }

            $historyMessage = [];
            $state = null;
            switch ($status) {
                case LiqPayServer::STATUS_SANDBOX:
                case LiqPayServer::STATUS_WAIT_COMPENSATION:
                case LiqPayServer::STATUS_SUCCESS:
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
                            if ($status == LiqPayServer::STATUS_SANDBOX) {
                                $historyMessage[] = __('Invoice #%1 created (sandbox).', $invoice->getIncrementId());
                            } else {
                                $historyMessage[] = __('Invoice #%1 created.', $invoice->getIncrementId());
                            }
                            $state = Order::STATE_PROCESSING;
                        } else {
                            $historyMessage[] = __('Error during creation of invoice.');
                        }
                    } else {
                        $state = Order::STATE_PROCESSING;
                        $historyMessage[] = __('Liqpay payment success.');
                    }

                    if ($senderPhone) {
                        $historyMessage[] = __('Sender phone: %1.', $senderPhone);
                    }
                    if ($amount) {
                        $historyMessage[] = __('Amount: %1.', $amount);
                    }
                    if ($currency) {
                        $historyMessage[] = __('Currency: %1.', $currency);
                    }
                    break;
                case LiqPayServer::STATUS_ERROR:
                case LiqPayServer::STATUS_FAILURE:
                    $state = Order::STATE_CANCELED;
                    $historyMessage[] = __('Liqpay error.');
                    break;
                case LiqPayServer::STATUS_WAIT_SECURE:
                    $state = Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for verification from the Liqpay side.');
                    break;
                case LiqPayServer::STATUS_WAIT_ACCEPT:
                    $state = Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for accepting from the buyer side.');
                    break;
                case LiqPayServer::STATUS_WAIT_CARD:
                    $state = Order::STATE_PROCESSING;
                    $historyMessage[] = __('Waiting for setting refund card number into your Liqpay shop.');
                    break;
                default:
                    $historyMessage[] = __('Unexpected status from LiqPay server: %1', $status);
                    break;
            }
            if ($transactionId) {
                $historyMessage[] = __('LiqPay transaction id %1.', $transactionId);
            }
            if (count($historyMessage)) {
                $order->addStatusHistoryComment(implode(' ', $historyMessage))
                    ->setIsCustomerNotified(true);
            }
            if ($state) {
                $order->setState($state);
                $order->setStatus($state);
                $order->save();
            }
            $this->_orderRepository->save($order);
        } catch (\Exception $e) {
            $this->logger->debug(['liqpay callback error msg' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * @param $orderId
     * @return Order
     */
    protected function getOrderById($orderId): Order
    {
        $orderPrefix = $this->config->getOrderPrefix();
        $orderSuffix = $this->config->getOrderSuffix();
        if (!empty($orderPrefix)) {
            if (strlen($orderPrefix) < strlen($orderId) && substr($orderId, 0, strlen($orderPrefix)) == $orderPrefix) {
                $orderId = substr($orderId, strlen($orderPrefix));
            }
        }
        if (!empty($orderSuffix)) {
            if (strlen($orderSuffix) < strlen($orderId) && substr($orderId, -strlen($orderSuffix)) == $orderSuffix) {
                $orderId = substr($orderId, 0, strlen($orderId) - strlen($orderSuffix));
            }
        }

        return $this->_order->loadByIncrementId($orderId);
    }
}
