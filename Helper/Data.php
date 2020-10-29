<?php

namespace SM\Adyen\Helper;

class Data
{
    
    /**
     * @var \SM\Payment\Model\ResourceModel\RetailPayment\CollectionFactory
     */
    protected $paymentCollectionFactory;
    
    /**
     * Data constructor.
     * @param \SM\Payment\Model\ResourceModel\RetailPayment\CollectionFactory $paymentCollectionFactory
     */
    public function __construct(\SM\Payment\Model\ResourceModel\RetailPayment\CollectionFactory $paymentCollectionFactory)
    {
        $this->paymentCollectionFactory = $paymentCollectionFactory;
    }
    
    /**
     * @return bool
     */
    public function checkAdyenSdkInstalled()
    {
        return class_exists('\Adyen\Client');
    }
    
    /**
     * @return false|\Magento\Framework\DataObject
     */
    public function getAdyenPaymentData($data)
    {
        $registerId = isset($data['register_id']) ? $data['register_id'] : 0;
        $collection = $this->paymentCollectionFactory->create()
            ->addFieldToFilter('type', ['eq' => \SM\Payment\Model\RetailPayment::ADYEN]);
        
        if ($collection->getSize() == 0) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Payment not found!'));
        }
    
        $adyenPayment = $collection->getFirstItem();
        $paymentData = json_decode($adyenPayment->getData('payment_data'), true);
        
        if (isset($paymentData[$registerId])) {
            return $paymentData[$registerId];
        } elseif (isset($paymentData[0])) {
            return $paymentData[0];
        }
    
        return $paymentData;
    }
    
    /**
     * Initializes and returns Adyen Client and sets the required parameters of it
     *
     * @param $data
     * @return \Adyen\Client
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function initAdyenClient($data)
    {
        
        if (!$this->checkAdyenSdkInstalled()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Adyen PHP API Library is not installed. Please run "compose require adyen/php-api-library" to install.')
            );
        }
        
        if (empty($apiKey)) {
            $apiKey = $this->getAdyenPaymentData($data)['api_key'];
        }
        
        $client = $this->createAdyenClient();
        $client->setApplicationName("ConnectPOS");
        $client->setXApiKey($apiKey);

        if ($this->getAdyenPaymentData($data)
            && $this->getAdyenPaymentData($data)['environment'] === \Adyen\Environment::TEST) {
            $client->setEnvironment(\Adyen\Environment::TEST);
        } else {
            $client->setEnvironment(\Adyen\Environment::LIVE, $this->getAdyenPaymentData($data)['live_url_prefix']);
        }
        
        return $client;
    }
    
    /**
     * @param \Adyen\Client $client
     * @return \Adyen\Service\PosPayment
     * @throws \Adyen\AdyenException
     */
    public function createAdyenPosPaymentService($client)
    {
        return new \Adyen\Service\PosPayment($client);
    }
    
    /**
     * @return \Adyen\Client
     * @throws \Adyen\AdyenException
     */
    private function createAdyenClient()
    {
        return new \Adyen\Client();
    }
}
