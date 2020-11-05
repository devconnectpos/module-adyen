<?php

namespace SM\Adyen\Repositories;

class AdyenManagement extends \SM\XRetail\Repositories\Contract\ServiceAbstract
{
    /**
     * @var \SM\Adyen\Helper\Data
     */
    protected $adyenPaymentHelper;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    
    public function __construct(
        \Magento\Framework\App\RequestInterface $requestInterface,
        \SM\XRetail\Helper\DataConfig $dataConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \SM\Adyen\Helper\Data $adyenPaymentHelper,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        parent::__construct($requestInterface, $dataConfig, $storeManager);
        $this->adyenPaymentHelper = $adyenPaymentHelper;
        $this->checkoutSession = $checkoutSession;
    }
    
    /**
     * @return mixed
     * @throws \Adyen\AdyenException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function processPayment()
    {
        $response = null;
        $data = $this->getRequestData();
        
        try {
            $client = $this->adyenPaymentHelper->initAdyenClient($data);
            $service = $this->adyenPaymentHelper->createAdyenPosPaymentService($client);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $response['error'] = $e->getMessage();
        }
        
        if ($response['error']) {
            return $response;
        }

        //Construct request
        $transactionType = \Adyen\TransactionType::NORMAL;
        $serviceID = date("dHis");
        $timeStamper = date("Y-m-d") . "T" . date("H:i:s+00:00");
        $poiId = $this->adyenPaymentHelper->getAdyenPaymentData($data)['POIID'];
        $reference = $data->getData('retail_id');
        $amount = $data->getData('amount');
        $currency = $data->getData('currency');
    
        $request = [
            'SaleToPOIRequest' =>
                [
                    'MessageHeader' =>
                        [
                            'MessageType' => 'Request',
                            'MessageClass' => 'Service',
                            'MessageCategory' => 'Payment',
                            'SaleID' => 'ConnectPOS',
                            'POIID' => $poiId,
                            'ProtocolVersion' => '3.0',
                            'ServiceID' => $serviceID
                        ],
                    'PaymentRequest' =>
                        [
                            'SaleData' =>
                                [
                                    'SaleTransactionID' =>
                                        [
                                            'TransactionID' => $reference,
                                            'TimeStamp' => $timeStamper
                                        ],
                                    'SaleToAcquirerData' => 'tenderOption=GetAdditionalData'
                                ],
                            'PaymentTransaction' =>
                                [
                                    'AmountsReq' =>
                                        [
                                            'Currency' => $currency,
                                            'RequestedAmount' => $amount
                                        ]
                                ],
                            "PaymentData" => [
                                "PaymentType" => $transactionType
                            ]
                        ]
                ]
        ];

        $this->addSaleToAcquirerData($request, $data);

        try {
            $response = $service->runTenderSync($request);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }

        return $response;
    }
    
    /**
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function processRefund()
    {
        $response = null;
        $data = $this->getRequestData();
        
        try {
            $client = $this->adyenPaymentHelper->initAdyenClient($data);
            $service = $this->adyenPaymentHelper->createAdyenPosPaymentService($client);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $response['error'] = $e->getMessage();
        }
    
        if ($response['error']) {
            return $response;
        }

        //Construct request

        $serviceID = date("dHis");
        $timeStamper = date("Y-m-d") . "T" . date("H:i:s+00:00");
        $poiId = $this->adyenPaymentHelper->getAdyenPaymentData($data)['POIID'];
        $reference = $data->getData('transaction_id');

        $params = [
            'SaleToPOIRequest' =>
                [
                    'MessageHeader' =>
                        [
                            'MessageType' => 'Request',
                            'MessageClass' => 'Service',
                            'MessageCategory' => 'Reversal',
                            'SaleID' => 'ConnectPOS',
                            'POIID' => $poiId,
                            'ProtocolVersion' => '3.0',
                            'ServiceID' => $serviceID
                        ],
                    "ReversalRequest" => [
                        "OriginalPOITransaction" => [
                            "POITransactionID" => [
                                "TransactionID" => $reference,
                                "TimeStamp" => $timeStamper
                            ]
                        ],
                        "ReversalReason" => "MerchantCancel"
                    ]
                ]
        ];

        try {
            $response = $service->runTenderSync($params);
        } catch (\Adyen\AdyenException $e) {
            $response['error'] = $e->getMessage();
        }
        return $response;
    }
    
    public function addSaleToAcquirerData($request, $data)
    {
        $customer = $data['customer'];
        $customerId = $customer['id'];
        
        $saleToAcquirerData = [];
        
        // If customer exists add it into the request to store request
        if (!empty($customerId)) {
            $shopperEmail = $customer['email'];
            
            $saleToAcquirerData['shopperEmail'] = $shopperEmail;
            $saleToAcquirerData['shopperReference'] = (string)$customerId;
        }
        
        $saleToAcquirerDataBase64 = base64_encode(json_encode($saleToAcquirerData));
        $request['SaleToPOIRequest']['PaymentRequest']['SaleData']['SaleToAcquirerData'] = $saleToAcquirerDataBase64;
        return $request;
    }
}
