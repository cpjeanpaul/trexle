<?php

namespace Trexle\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Payment\Model\MethodInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Trexle\Payment\Helper\Utils as UtilsHelper;

class Trexle implements MethodInterface
{

    const PAYMENT_CODE = 'trexle_payment';
    const CONFIG_PATH_PREFIX = 'payment/trexle_payment/';
    const GATEWAY_URL = 'https://core.trexle.com/api/v1/';

    const REQUEST_TYPE_AUTH_CAPTURE = 'AUTH_CAPTURE';
    const REQUEST_TYPE_AUTH_ONLY = 'AUTH_ONLY';
    const REQUEST_TYPE_CAPTURE_ONLY = 'CAPTURE_ONLY';

    /**
     * @var ScopeConfigInterface
     */
    protected $_config;

    protected $_canCapture = true;

    /**
     * @var InfoInterface
     */
    protected $infoInstance;

    /**
     * @var UtilsHelper
     */
    protected $_utilsHelper;

    /**
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    protected $_httpClientFactory;

    /**
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * @var State
     */
    protected $_appState;

    /**
     * Payment constructor.
     * @param State $appState
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param LoggerInterface $logger
     * @param ZendClientFactory $httpClientFactory
     * @param UtilsHelper $utilsHelper
     */
    public function __construct(
        State $appState,
        ScopeConfigInterface $scopeConfigInterface,
        LoggerInterface $logger,
        ZendClientFactory $httpClientFactory,
        UtilsHelper $utilsHelper
    )
    {
        $this->_config = $scopeConfigInterface;
        $this->_logger = $logger;
        $this->_httpClientFactory = $httpClientFactory;
        $this->_utilsHelper = $utilsHelper;
        $this->_appState = $appState;
    }

    /**
     * @inheritDoc
     */
    public function getCode()
    {
        return self::PAYMENT_CODE;
    }

    /**
     * Note: Intentionally not implemented due to deprecation in favour of UiComponent
     * @inheritDoc
     */
    public function getFormBlockType()
    {
        return "";
    }

    /**
     * @inheritDoc
     */
    public function getTitle()
    {
        return $this->_config->getValue(self::CONFIG_PATH_PREFIX . 'title');
    }

    /**
     * @inheritDoc
     */
    public function setStore($storeId)
    {
        // TODO: Implement setStore() method.
    }

    /**
     * @inheritDoc
     */
    public function getStore()
    {
        // TODO: Implement getStore() method.
        return \Magento\Store\Model\Store::DEFAULT_STORE_ID;
    }

    /**
     * @inheritDoc
     */
    public function canOrder()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canAuthorize()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function canCapture()
    {
        return $this->_canCapture;
    }

    /**
     * @inheritDoc
     */
    public function canCapturePartial()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canCaptureOnce()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function canRefund()
    {
        // TODO: Implement canRefund() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canRefundPartialPerInvoice()
    {
        // TODO: Implement canRefundPartialPerInvoice() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canVoid()
    {
        // TODO: Implement canVoid() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canUseInternal()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function canUseCheckout()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function canEdit()
    {
        // TODO: Implement canEdit() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canFetchTransactionInfo()
    {
        // TODO: Implement canFetchTransactionInfo() method.
        return true;
    }

    /**
     * @inheritDoc
     */
    public function fetchTransactionInfo(InfoInterface $payment, $transactionId)
    {
        // TODO: Implement fetchTransactionInfo() method.
        return [];
    }

    /**
     * @inheritDoc
     */
    public function isGateway()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function isOffline()
    {
        if ($this->_appState->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            return true;
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    public function isInitializeNeeded()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function canUseForCountry($country)
    {
        // TODO: Implement canUseForCountry() method.
        return true;
    }

    /**
     * @inheritDoc
     */
    public function canUseForCurrency($currencyCode)
    {
        //TODO: Implement required rules
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getInfoBlockType()
    {
        return "Magento\\Payment\\Block\\ConfigurableInfo";
    }

    /**
     * @inheritdoc
     */
    public function getInfoInstance()
    {
        return $this->infoInstance;
    }

    /**
     * @inheritdoc
     */
    public function setInfoInstance(InfoInterface $info)
    {
        $this->infoInstance = $info;
    }

    /**
     * @inheritDoc
     */
    public function validate()
    {
        // TODO: Implement validate()
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function order(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        return $this;
    }

    /**
     * @param $payment InfoInterface
     * @param $order \Magento\Sales\Model\Order
     * @param $amount float
     * @param $transactionType string
     * @return \Magento\Framework\HTTP\ZendClient
     */
    public function getClient($payment, $order, $amount, $transactionType = self::REQUEST_TYPE_AUTH_CAPTURE)
    {

        $client = $this->_httpClientFactory->create();
        $endpoint = $this->getPaymentUrl() . 'charges';
        $method = \Zend_Http_Client::POST;

        if ($transactionType === self::REQUEST_TYPE_CAPTURE_ONLY) {
            $endpoint .= '/' . $payment->getCcTransId() . '/capture';
            $method = \Zend_Http_Client::PUT;
        }

        $authKey = $this->getConfigData('secret_key', $order->getStoreId());

        if (empty($authKey)){
            throw new LocalizedException(__("No authorization key was set."));
        }

        $client->setAuth($authKey);

        $client->setConfig(['maxredirects' => 0, 'timeout' => 120]);
        $client->setUri($endpoint);
        $client->setMethod($method);

        /**
         * A capture-only request requires amount value as the only parameter.
         * Note: the charge token is part of the URL.
         */
        if ($transactionType === self::REQUEST_TYPE_CAPTURE_ONLY) {
            $data = ['amount' => $this->_utilsHelper->getRequestAmount($order->getBaseCurrencyCode(), $amount)];
        } else {
            $capture = $transactionType === self::REQUEST_TYPE_AUTH_CAPTURE;

            $descPrefix = $this->getConfigData('description_prefix', $order->getStoreId());
            if (is_null($descPrefix)) {
                $descPrefix = '';
            } else {
                $descPrefix = $descPrefix . ' ';
            }

            $postData = file_get_contents("php://input");
            $postData = (array)json_decode($postData);
            foreach ($postData as $key => $value) {
                if ($key == 'paymentMethod') {
                    $paymentDetails = (array)$value;
                    foreach ($paymentDetails as $key1 => $paymentValue) {
                        if ($key1 == 'additional_data') {
                            $paymentCardDetails = (array)$paymentValue;
                        }
                    }
                }
            }

            $address1 = $order->getBillingAddress()->getStreet()[0];
            $address2 = (!isset($order->getBillingAddress()->getStreet()[1]) || $order->getBillingAddress()->getStreet()[1]) ? ' ' : $order->getBillingAddress()->getStreet()[1];

            $data = [
                "amount" => $this->_utilsHelper->getRequestAmount($order->getBaseCurrencyCode(), $amount),
                "currency" => $order->getBaseCurrencyCode(),
                "description" => $descPrefix . 'Order: #' . $order->getRealOrderId(),
                "email" => $order->getCustomerEmail(),
                "ip_address" => $order->getRemoteIp(),
                "card[number]" => $paymentCardDetails['cc_number'],
                "card[expiry_month]" => $paymentCardDetails['cc_exp_month'],
                "card[expiry_year]" => $paymentCardDetails['cc_exp_year'],
                "card[cvc]" => $paymentCardDetails['cc_cid'],
                "card[name]" => trim($order->getBillingAddress()->getFirstname() . " " . $order->getBillingAddress()->getLastname()),
                "card[address_line1]" => $address1,
                "card[address_line2]" => $address2,
                "card[address_city]" => $order->getBillingAddress()->getCity(),
                "card[address_postcode]" => $order->getBillingAddress()->getPostcode(),
                "card[address_state]" => $order->getBillingAddress()->getRegion(),
                "card[address_country]" => $this->_utilsHelper->getCountryName($order->getBillingAddress()->getCountryId()),
                "capture" => $capture
            ];
        }

        foreach ($data as $reqParam => $reqValue) {
            $client->setParameterPost($reqParam, $reqValue);
        }

        return $client;
    }

    /**
     * @inheritDoc
     */
    public function authorize(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($amount <= 0) {
            $this->_logger->debug('Expected amount for transaction is zero or below');
            throw new LocalizedException(__("Invalid payment amount."));
        }

        /**
         * @var $order \Magento\Sales\Model\Order
         */

        $order = $payment->getOrder();
        $client = $this->getClient($payment, $order, $amount, self::REQUEST_TYPE_AUTH_ONLY);

        $response = null;
        try {
            $response = $client->request();
            $this->_handleResponse($response, $payment);
        } catch (\Exception $e) {
            $this->_logger->debug("Payment Error: " . $e->getMessage());
            throw new LocalizedException(__($e->getMessage()));
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        if ($amount <= 0) {
            $this->_logger->debug('Expected amount for transaction is zero or below');
            throw new LocalizedException(__("Invalid payment amount."));
        }

        /**
         * @var $order \Magento\Sales\Model\Order
         */
        $order = $payment->getOrder();

        $transactionType = self::REQUEST_TYPE_AUTH_CAPTURE;

        if ($payment->getCcTransId()) {
            $transactionType = self::REQUEST_TYPE_CAPTURE_ONLY;
        }
        $client = $this->getClient($payment, $order, $amount, $transactionType);

        $response = null;
        try {
            $response = $client->request();
            $this->_handleResponse($response, $payment);
        } catch (\Exception $e) {
            $this->_logger->debug("Payment Error: " . $e->getMessage());
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * @param $response \Zend_Http_Response
     * @param $payment \Magento\Payment\Model\InfoInterface
     * @throws LocalizedException
     */
    protected function _handleResponse($response, $payment)
    {
        /**
         * @var $result \Trexle\Payment\Model\Result
         */
        $result = new Result($response);
        $error = $result->getError();

        if ($result->isSuccess()) {
            $payment->setCcTransId($result->getToken());
            $payment->setTransactionId($result->getToken());
            $payment->setCcType($result->getCCType());
        } elseif ($error) {
            throw new LocalizedException(__($result->getErrorDescription()));
        }
    }

    /**
     * @param $order \Magento\Sales\Model\Order
     * @param $payment \Magento\Payment\Model\InfoInterface
     * @param $amount float
     * @param $capture boolean
     * @return array
     */
    protected function _buildAuthRequest($order, $payment, $amount, $capture = true)
    {

    }

    /**
     * @inheritDoc
     */
    public function refund(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        // TODO: Implement void() method.
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel(\Magento\Payment\Model\InfoInterface $payment)
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function void(\Magento\Payment\Model\InfoInterface $payment)
    {
        // TODO: Implement void() method.
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function canReviewPayment()
    {
        // TODO: Implement canReviewPayment() method.
        return true;
    }

    /**
     * @inheritDoc
     */
    public function acceptPayment(InfoInterface $payment)
    {
        // TODO: Implement acceptPayment() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function denyPayment(InfoInterface $payment)
    {
        // TODO: Implement denyPayment() method.
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getConfigData($field, $storeId = null)
    {
        $configKey = self::CONFIG_PATH_PREFIX . $field;
        $configValue = '';
        if ($storeId) {
            $configValue = $this->_config->getValue($configKey, ScopeInterface::SCOPE_STORES, $storeId);
        }
        $configValue = $this->_config->getValue($configKey);

        if ($field == 'secret_key') {
            return $this->_utilsHelper->decrypt($configValue);
        }

        return $configValue;
    }

    /**
     * @inheritDoc
     */
    public function assignData(DataObject $data)
    {
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }

        $info = $this->getInfoInstance();
        if ($this->isOffline()) {
            $info->setAdditionalInformation('reference_number', $additionalData->getReferenceNumber());
        } else {
            $info->setAdditionalInformation('card_token', $additionalData->getCardToken());
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function isAvailable(CartInterface $quote = null)
    {
        return $this->isActive();
    }

    /**
     * @inheritDoc
     */
    public function isActive($storeId = null)
    {
        return $this->getConfigData('active');
    }

    /**
     * @inheritDoc
     */
    public function initialize($paymentAction, $stateObject)
    {
        // TODO: Implement initialize() method.
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getConfigPaymentAction()
    {
        return $this->getConfigData('payment_action');
    }

    /**
     * Get the URL for sending payment API requests based on whether test mode is configured.
     * @param int $storeId
     * @return string
     */
    public function getPaymentUrl($storeId = 0)
    {
        return self::GATEWAY_URL;
    }

}