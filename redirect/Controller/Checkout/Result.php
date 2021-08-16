<?php

namespace Pronko\LiqPayRedirect\Controller\Checkout;

use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface as HttpPostActionInterface;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DB\Transaction;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Repository;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Pronko\LiqPayGateway\Gateway\Config;
use Pronko\LiqPayRedirect\Model\LiqPayServer;

class Result extends Action implements CsrfAwareActionInterface, HttpPostActionInterface
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
        Context $context,
        Order $order,
        OrderRepositoryInterface $orderRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        BuilderInterface $transactionBuilder,
        Repository $paymentRepository,
        Order\Payment\Transaction\Repository $transactionRepository,
        Logger $logger,
        Config $config,
        RequestInterface $request,
        LiqPayServer $liqPayServer
    )
    {
        $this->_order = $order;
        $this->_orderRepository = $orderRepository;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->_request = $request;
        $this->logger = $logger;
        $this->config = $config;
        $this->liqPayServer = $liqPayServer;
        $this->_transactionBuilder = $transactionBuilder;
        $this->_paymentRepository = $paymentRepository;
        $this->_transactionRepository = $transactionRepository;
        parent::__construct($context);
    }


    public function execute()
    {
        $post = $this->getRequest()->getParams();

        // add log
        $this->logger->debug(['LiqPay result Data' => $post]);

        $this->_redirect('checkout/onepage/success');
        return;
    }


    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
