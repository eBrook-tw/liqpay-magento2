<?php

namespace Pronko\LiqPayRedirect\Controller\Checkout;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Pronko\LiqPayApi\Model\LiqPayCheckPayment;

class Result extends Action
{
    /**
     * @var Order
     */
    protected Order $_order;

    private Logger $logger;

    /**
     * @var LiqPayCheckPayment
     */
    private LiqPayCheckPayment $payCheckPayment;

    private Session $checkoutSession;

    public function __construct(
        Context $context,
        Order $order,
        Logger $logger,
        Session $checkoutSession,
        LiqPayCheckPayment $payCheckPayment
    ) {
        $this->_order = $order;
        $this->logger = $logger;
        $this->payCheckPayment = $payCheckPayment;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }

    public function execute()
    {
        $post = $this->getRequest()->getParams();

        // add log
        $this->logger->debug(['LiqPay result Data' => $post]);
        $orderId = $this->getRequest()->getParam('order_id');

        $order = $this->_order->loadByIncrementId($orderId);

        if ($order && $order->getId()) {
            // check status
            if ($order->getState() == Order::STATE_PROCESSING) {
                $this->_redirect('checkout/onepage/success');
                return;
            }

            // check payment status by api
            if ($this->payCheckPayment->check($orderId)) {
                $this->_redirect('checkout/onepage/success');
                return;
            }
            $this->checkoutSession->clearQuote();
            $this->messageManager->addErrorMessage(__('The order payment failed or you have already paid, please check later.'));
            $this->_redirect('checkout/onepage/failure');
            return;
        }
        $this->messageManager->addErrorMessage(__('The order payment failed or order information error.'));
        $this->_redirect('checkout/cart');
        return;
    }
}
