<?php

namespace UnitPay\Payment\Model;

use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;

/**
 * Class Payment
 * @package UnitPay\Payment\Model
 */
class Payment extends AbstractMethod
{
    /**
     *
     */
    const COINGATE_MAGENTO_VERSION = '1.0.0';

    /**
     *
     */
    const CODE = 'unitpay_payment';

    /**
     * @var string
     */
    protected $_code = 'unitpay_payment';

    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;
    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;
	
	/**
     * @var ProductRepositoryInterface
     */
    protected $productManagement;
	
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var
     */
    protected $domain;
    /**
     * @var
     */
    protected $publicKey;
    /**
     * @var
     */
    protected $secretKey;

    /**
     * @var bool
     */
    public $processSuccess = false;

    /**
     * Payment constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param OrderManagementInterface $orderManagement
	 * @param ProductRepositoryInterface $productManagement
     * @param array $data
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        UrlInterface $urlBuilder,
        StoreManagerInterface $storeManager,
        OrderManagementInterface $orderManagement,
		ProductRepositoryInterface $productManagement,
        array $data = [],
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->orderManagement = $orderManagement;
		$this->productManagement = $productManagement;

        $this->setDomain($this->scopeConfig->getValue('payment/unitpay_payment/domain', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $this->setPublicKey($this->scopeConfig->getValue('payment/unitpay_payment/public_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
        $this->setSecretKey($this->scopeConfig->getValue('payment/unitpay_payment/secret_key', \Magento\Store\Model\ScopeInterface::SCOPE_STORE));
    }

    /**
     * @return bool
     */
    public function isProcessSuccess()
    {
        return $this->processSuccess;
    }

    /**
     * @param bool $processSuccess
     */
    public function setProcessSuccess($processSuccess)
    {
        $this->processSuccess = $processSuccess;
    }


    /**
     * @return mixed
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;
    }

    /**
     * @return mixed
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * @param mixed $publicKey
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;
    }

    /**
     * @return mixed
     */
    public function getSecretKey()
    {
        return $this->secretKey;
    }

    /**
     * @param mixed $secretKey
     */
    public function setSecretKey($secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * @return string
     */
    public function endpoint() {
        return 'https://' . $this->getDomain() . '/pay/' . $this->getPublicKey();
    }

    /**
     * @param $order_id
     * @param $currency
     * @param $desc
     * @param $sum
     * @return string
     */
    public function generateSignature($order_id, $currency, $desc, $sum) {
        return hash('sha256', join('{up}', array(
            $order_id,
            $currency,
            $desc,
            $sum ,
            $this->getSecretKey()
        )));
    }

    /**
     * @param $method
     * @param array $params
     * @return string
     */
    public function getSignature($method, array $params)
    {
        ksort($params);
        unset($params['sign']);
        unset($params['signature']);
        array_push($params, $this->getSecretKey());
        array_unshift($params, $method);

        return hash('sha256', join('{up}', $params));
    }

    /**
     * @param $params
     * @param $method
     * @return bool
     */
    public function verifySignature($params, $method)
    {
        return $params['signature'] == $this->getSignature($method, $params);
    }

    /**
     * @param $items
     * @return string
     */
    public function cashItems($items) {
        return base64_encode(json_encode($items));
    }

    /**
     * @param $rate
     * @return string
     */
    function getTaxRates($rate){
        switch (intval($rate)){
            case 10:
                $vat = 'vat10';
                break;
            case 20:
                $vat = 'vat20';
                break;
            case 0:
                $vat = 'vat0';
                break;
            default:
                $vat = 'none';
        }

        return $vat;
    }

    /**
     * @param $value
     * @return string
     */
    public function priceFormat($value) {
        return number_format($value, 2, '.', '');
    }

    /**
     * @param $value
     * @return string
     */
    public function phoneFormat($value) {
        return  preg_replace('/\D/', '', $value);
    }

    /**
     * @param Order $order
     * @return array
     */
    public function getRedirectUrl(Order $order)
    {
        $items = [];
        $description = "Оплата заказа № " . $order->getIncrementId();
        $sum = $this->priceFormat($order->getGrandTotal());

        foreach ($order->getAllItems() as $item) {
			$product = $this->productManagement->getById($item->getData("product_id"));
			
            $items[] = [
                "name" => $item->getName(),
                "count" => $item->getQtyOrdered(),
                "price" => $this->priceFormat($item->getPrice()),
                "type" => "commodity",
                "currency" => $order->getOrderCurrencyCode(),
                "nds" => $product->getTaxClassId() > 0 ? $this->getTaxRates($item->getData("tax_percent")) : "none",
            ];
        }

        $deliveryPrice = $order->getShippingAmount();

        if($deliveryPrice > 0) {
            $items[] = array(
                'name' => "Доставка",
                'price' => $this->priceFormat($deliveryPrice),
                'count'   => 1,
                'type' => 'service',
                'currency' => $order->getOrderCurrencyCode(),
            );
        }

        $cashItems = $this->cashItems($items);

        $signature = $this->generateSignature($order->getIncrementId(), $order->getOrderCurrencyCode(), $description, $sum);

        $params = [
            'account' => $order->getIncrementId(),
            'desc' => $description,
            'sum' => $sum,
            'signature' => $signature,
            'currency' => $order->getOrderCurrencyCode(),
            'cashItems' => $cashItems,
            'customerEmail' => $order->getCustomerEmail(),
            'customerPhone' => $this->phoneFormat($order->getShippingAddress()->getTelephone()),
        ];

        return [
            'status' => true,
            'payment_url' => $this->endpoint() . "?" . http_build_query($params)
        ];
    }


    /**
     * @param Order $order
     * @return array
     */
    public function validateCallback(Order $order)
    {
        $method = '';
        $params = [];
        $result = [];

        try {
            if (!$order || !$order->getIncrementId()) {
				$result = array('error' =>
					array('message' => 'Order not found')
				);
            } else {
				if ((isset($_GET['params'])) && (isset($_GET['method'])) && (isset($_GET['params']['signature']))){
					$params = $_GET['params'];
					$method = $_GET['method'];
					$signature = $params['signature'];

					if (empty($signature)){
						$status_sign = false;
					}else{
						$status_sign = $this->verifySignature($params, $method);
					}

				}else{
					$status_sign = false;
				}

				if ($status_sign){
					if(in_array($method, array('check', 'pay', 'error'))) {
						$this->currentMethod = $method;

						$result = $this->findErrors($params, $this->priceFormat($order->getGrandTotal()), $order->getOrderCurrencyCode());
					} else {
						$result = array('error' =>
							array('message' => 'Method not exists')
						);
					}
				}else{
					$result = array('error' =>
							array('message' => 'Signature verify error')
					);
				}
			}

        } catch (\Exception $e) {
            $this->_logger->error($e);
        }

        return $result;
    }

    /**
     * @param $params
     * @param $sum
     * @param $currency
     * @return array
     */
    public function findErrors($params, $sum, $currency) {
        $this->setPublicKey(false);

        $order_id = $params['account'];

        if (is_null($order_id)){
            $result = array('error' =>
                array('message' => 'Order id is required')
            );
        } elseif ((float) $this->priceFormat($sum) != (float) $this->priceFormat($params['orderSum'])) {
            $result = array('error' =>
                array('message' => 'Price not equals ' . $sum . ' != ' . $params['orderSum'])
            );
        }elseif ($currency != $params['orderCurrency']) {
            $result = array('error' =>
                array('message' => 'Currency not equals ' . $currency . ' != ' . $params['orderCurrency'])
            );
        }
        else{
            $this->setProcessSuccess(true);

            $result = array('result' =>
                array('message' => 'Success')
            );
        }

        return $result;
    }
}
