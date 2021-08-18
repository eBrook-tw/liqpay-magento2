<?php

namespace Pronko\LiqPayApi\Cron;

use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Pronko\LiqPayApi\Api\Data\PaymentMethodCodeInterface;
use Pronko\LiqPayApi\Model\LiqPayCheckPayment;

class CheckPaymentStatus
{

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $_orderCollectionFactory;

    private Logger $logger;

    /**
     * @var LiqPayCheckPayment
     */
    private LiqPayCheckPayment $payCheckPayment;

    public function __construct(
        CollectionFactory $orderCollectionFactory,
        Logger $logger,
        LiqPayCheckPayment $payCheckPayment
    ) {
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->payCheckPayment = $payCheckPayment;
    }

    /**
     * Cronjob Description
     *
     * @return void
     */
    public function execute(): void
    {
        $date = new \DateTime();
        $date->modify('-30 min');
        $gtTime = date('Y-m-d H:i:s', $date->getTimestamp());
        $collection = $this->_orderCollectionFactory->create()
            ->addFieldToFilter('state', Order::STATE_NEW)
            ->addFieldToFilter('status', 'pending')
            ->addFieldToFilter('created_at', ['gteq' => $gtTime]);

        $collection->getSelect()
            ->joinLeft(
                'sales_order_payment as sop',
                'main_table.entity_id=sop.parent_id',
                null
            )
            ->where(sprintf("sop.method ='%s'", PaymentMethodCodeInterface::CODE));

        foreach ($collection as $order) {
            try {
                $this->payCheckPayment->check($order->getIncrementId());
            } catch (\Exception $e) {
                $this->logger->debug([$e->getMessage()]);
            }
        }
    }
}
