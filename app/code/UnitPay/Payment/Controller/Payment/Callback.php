<?php

namespace UnitPay\Payment\Controller\Payment;

use UnitPay\Payment\Model\Payment as UnitpayPayment;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Sales\Model\Order;

/**
 * Class Callback
 * @package UnitPay\Payment\Controller\Payment
 */
class Callback extends Action
{
    /**
     * @var Order
     */
    protected $order;
    /**
     * @var Payment|UnitpayPayment
     */
    protected $unitpayPayment;

    /**
     * @param Context $context
     * @param Order $order
     * @param Payment|UnitpayPayment $unitpayPayment
     * @internal param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
    public function __construct(Context $context,Order $order, UnitpayPayment $unitpayPayment) {
        parent::__construct($context);

        $this->order = $order;
        $this->unitpayPayment = $unitpayPayment;

        $this->execute();
    }

    /**
     * Default customer account page
     *
     * @return void
     */
    public function execute()
    {
		if(!isset($_GET['params']['account'])) {
			$result = array('error' =>
				array('message' => 'Required params not found')
            );
		} else {
			$order = $this->order->loadByIncrementId($_GET['params']['account']);
			
			$result = $this->unitpayPayment->validateCallback($order);

			if($this->unitpayPayment->isProcessSuccess()) {
				$order->setState(Order::STATE_PROCESSING);
				$order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
				$order->save();
			}
		}

        $this->getResponse()->setBody(json_encode($result));
    }
}
