<?php

/**
 * LiqPay Extension for Magento 2
 *
 * @author     Volodymyr Konstanchuk http://konstanchuk.com
 * @copyright  Copyright (c) 2017 The authors
 * @license    http://www.opensource.org/licenses/mit-license.html  MIT License
 */

namespace Pronko\LiqPayRedirect\Controller\Checkout;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\LayoutFactory;
use Magento\Payment\Model\Method\Logger;
use Pronko\LiqPayGateway\Gateway\Config;
use Pronko\LiqPayRedirect\Model\LiqPayServer;

class Form extends Action
{
    protected CheckoutSession $_checkoutSession;

    protected LayoutFactory $_layoutFactory;

    private Logger $logger;

    private Config $config;
    /**
     * @var LiqPayServer
     */
    private LiqPayServer $liqPayServer;


    /**
     * Form constructor.
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param Logger $logger
     * @param Config $config
     * @param LayoutFactory $layoutFactory
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        Logger $logger,
        Config $config,
        LiqPayServer $liqPayServer,
        LayoutFactory $layoutFactory
    ) {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_layoutFactory = $layoutFactory;
        $this->logger = $logger;
        $this->config = $config;
        $this->liqPayServer = $liqPayServer;
    }

    /**
     * Dispatch request
     *
     * @return \Magento\Framework\Controller\ResultInterface|ResponseInterface
     * @throws \Magento\Framework\Exception\NotFoundException
     */
    public function execute()
    {
        try {
            if (!$this->config->isEnabled()) {
                throw new \Exception(__('Payment is not allow.'));
            }
            $order = $this->getCheckoutSession()->getLastRealOrder();
            if (!($order && $order->getId())) {
                throw new \Exception(__('Order not found'));
            }
            if ($this->liqPayServer->checkOrderIsLiqPayPayment($order)) {
                // set quote is not active
                if ($this->getCheckoutSession()->getQuote()->getId() == $order->getQuoteId() && $this->getCheckoutSession()->getQuote()->getIsActive()) {
                    $this->getCheckoutSession()->getQuote()->setIsActive(false)->save();
                }

                $formBlock = $this->_view->getLayout()->createBlock('Pronko\LiqPayRedirect\Block\SubmitForm');
                $formBlock->setOrder($order);
                $data = [
                    'status' => 'success',
                    'content' => $formBlock->getLiqpayForm(),
                ];
            } else {
                throw new \Exception('Order payment method is not a LiqPay payment method');
            }
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong, please try again later'));
            $this->logger->debug(['errorMsg' => $e->getMessage()]);
            $this->getCheckoutSession()->restoreQuote();
            $data = [
                'status' => 'error',
                'redirect' => $this->_url->getUrl('checkout/cart'),
            ];
        }
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData($data);
        return $result;
    }

    /**
     * Return checkout session object
     *
     * @return CheckoutSession
     */
    protected function getCheckoutSession()
    {
        return $this->_checkoutSession;
    }
}
