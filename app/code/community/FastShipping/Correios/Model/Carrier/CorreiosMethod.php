<?php

class FastShipping_Correios_Model_Carrier_CorreiosMethod
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    /**
     * _code property
     *
     * @var string
     */
    protected $_code = 'fastshipping_correios';
    protected $_isFixed = true;

    /**
     * _result property
     *
     * @var Mage_Shipping_Model_Rate_Result|Mage_Shipping_Model_Tracking_Result
     */
    protected $_result = null;

    /**
     * ZIP code vars
     */
    protected $_fromZip = null;
    protected $_toZip = null;

    /**
     * Free method request
     */
    protected $_freeMethodRequest = false;
    protected $_freeMethodRequestResult = null;

    protected $_products = array();

    protected $_token = "b3a3ca59438f695561eab489a0a514ff23d775b8ab485994a62916c94e8f73725c8d8b7168153e2e";

    /**
     * Collect Rates
     *
     * @param Mage_Shipping_Model_Rate_Request $request Mage request
     *
     * @return bool|Mage_Shipping_Model_Rate_Result|Mage_Shipping_Model_Tracking_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        // Do initial check
        if ($this->_inicialCheck($request) === false) {
            return false;
        }

        if ($this->_getQuotes()->getError()) {
            return $this->_result;
        }

        // Use descont codes
        $this->_updateFreeMethodQuote($request);

        return $this->_result;
    }

    /**
     * Get shipping quote
     *
     * @return Mage_Shipping_Model_Rate_Result|Mage_Shipping_Model_Tracking_Result
     */
    protected function _getQuotes()
    {
        $softErrors     = explode(',', $this->getConfigData('soft_errors'));
        $correiosReturn = $this->_getCorreiosReturn();

        if ($correiosReturn !== false) {
            $existReturn    = false;

            foreach ($correiosReturn as $servicos) {
                $stringPrice   = (string) $servicos->price;
                $stringPrice   = str_replace('.', '', $stringPrice);
                $stringPrice   = str_replace(',', '.', $stringPrice);
                $shippingPrice = floatval($stringPrice);
                $shippingPrice *= pow(2, $this->_splitUp);
                $shippingDelivery = (int) $servicos->estimate;

                if ($shippingPrice <= 0) {
                    continue;
                }

                $this->_appendShippingReturn((string) $servicos->method, $shippingPrice, $shippingDelivery);
                if ($this->getConfigFlag('show_soft_errors') && !isset($isWarnAppended)) {
                    $isWarnAppended = $this->_appendShippingWarning($servicos);
                }
                $existReturn = true;
            }
        } else {
            return $this->_result;
        }

        if ($this->_freeMethodRequest === true) {
            return $this->_freeMethodRequestResult;
        } else {
            return $this->_result;
        }
    }

    /**
     * Make initial checks and iniciate module variables
     *
     * @param Mage_Shipping_Model_Rate_Request $request Mage request
     *
     * @return bool
     */
    protected function _inicialCheck(Mage_Shipping_Model_Rate_Request $request)
    {
        $this->_prepareProductsToSendFastShipping();

        $this->_fromZip = Mage::getStoreConfig('shipping/origin/postcode', $this->getStore());
        $this->_toZip   = $request->getDestPostcode();

        // Fix ZIP code
        $this->_fromZip = str_replace(array('-', '.'), '', trim($this->_fromZip));
        $this->_toZip   = str_replace(array('-', '.'), '', trim($this->_toZip));

        $this->_result       = Mage::getModel('shipping/rate_result');
        $this->_packageValue = $request->getBaseCurrency()->convert(
            $request->getPackageValue(),
            $request->getPackageCurrency()
        );

        $this->_packageWeight    = number_format($request->getPackageWeight(), 2, '.', '');
        $this->_freeMethodWeight = number_format($request->getFreeMethodWeight(), 2, '.', '');
    }

    /**
     * Get Correios return
     *
     * @return bool|SimpleXMLElement[]
     *
     * @throws Exception
     */
    protected function _getCorreiosReturn()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://fastshipping.ciawn.com.br/v1/shipping?token=" . $this->_token);   
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $this->_prepareAndGetDataShipping());

        $response = curl_exec($curl);
        curl_close($curl);

        return json_decode($response);
    }

    /**
     * Apend shipping value to return
     *
     * @param string $shippingMethod   Method of shipping
     * @param int    $shippingPrice    Price
     * @param int    $correiosDelivery Delivery date
     *
     * @return void
     */
    protected function _appendShippingReturn($shippingMethod, $shippingPrice = 0, $correiosDelivery = 0)
    {

        $method = Mage::getModel('shipping/rate_result_method');
        $method->setCarrier($this->_code);
        $method->setCarrierTitle($this->getConfigData('title'));
        $method->setMethod($shippingMethod);

        $shippingCost  = $shippingPrice;

        $method->setMethodTitle($shippingMethod);

        $method->setPrice($shippingPrice);
        $method->setCost($shippingCost);

        if ($this->_freeMethodRequest === true) {
            $this->_freeMethodRequestResult->append($method);
        } else {
            $this->_result->append($method);
        }
    }

    /**
     * Get Tracking Info
     *
     * @param mixed $tracking Tracking
     *
     * @return mixed
     */
    public function getTrackingInfo($tracking)
    {
        $result = $this->getTracking($tracking);
        if ($result instanceof Mage_Shipping_Model_Tracking_Result) {
            if ($trackings = $result->getAllTrackings()) {
                return $trackings[0];
            }
        } elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return false;
    }

    /**
     * Get Tracking
     *
     * @param array $trackings Trackings
     *
     * @return Mage_Shipping_Model_Tracking_Result
     */
    public function getTracking($trackings)
    {
        $this->_result = Mage::getModel('shipping/tracking_result');
        foreach ((array) $trackings as $code) {
            $this->_getTracking($code);
        }

        return $this->_result;
    }

    /**
     * Protected Get Tracking, opens the request to Correios
     *
     * @param string $code Code
     *
     * @return bool
     */
    protected function _getTracking($code)
    {
        $error = Mage::getModel('shipping/tracking_result_error');
        $error->setTracking($code);
        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setErrorMessage($this->getConfigData('urlerror'));

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "https://fastshipping.ciawn.com.br/v1/tracking/". $code ."?token=". $this->_token);   
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($response)->result;
        $progress = array();

        foreach ($response as $key => $item) {
            $datetime = explode(' ', $item->data);
            $locale   = new Zend_Locale('pt_BR');
            $date     = '';
            $date     = new Zend_Date($datetime[0], 'dd/MM/YYYY', $locale);

            $track = array(
                'deliverydate'     => $date->toString('YYYY-MM-dd'),
                'deliverytime'     => $datetime[1] . ':00',
                'deliverylocation' => $item->local,
                'status'           => $item->acao,
                'activity'         => $item->detalhes
            );

            $progress[] = $track;   
        }

        if (!empty($progress)) {
            $track                   = $progress[0];
            $track['progressdetail'] = $progress;

            $tracking = Mage::getModel('shipping/tracking_result_status');
            $tracking->setTracking($code);
            $tracking->setCarrier($this->_code);
            $tracking->setCarrierTitle($this->getConfigData('title'));
            $tracking->addData($track);

            $this->_result->append($tracking);
            return true;
        } else {
            $this->_result->append($error);
            return false;
        }
    }

    /**
     * Returns the allowed carrier methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return;
    }

    /**
     * Define ZIP Code as required
     *
     * @param string $countryId Country ID
     *
     * @return bool
     */
    public function isZipCodeRequired($countryId = null)
    {
        return true;
    }

    /**
     * Generate Volume weight
     *
     * @return bool
     */
    protected function _prepareProductsToSendFastShipping()
    {
        $items = Mage::getModel('checkout/cart')->getQuote()->getAllVisibleItems();

        if (count($items) == 0) {
            $items = Mage::getSingleton('adminhtml/session_quote')->getQuote()->getAllVisibleItems();
        }

        foreach ($items as $item) {
            $_product = $item->getProduct();
         
            $this->_products[] = [
                "weight" => (float) $_product->getWeight(),
                "height" => (float) $_product->getHeight(),
                "length" => (float) $_product->getLength(),
                "width" => (float) $_product->getWidth(),
                "unit_price" => (float) $_product->getPrice(),
                "quantity" => $item->getQty(),
                "sku" => $_product->getSku(),
                "id" => $_product->getId()
            ];
        }
        
        return true;
    }

    public function _prepareAndGetDataShipping()
    {
        $response = [
            'destination_postal_code'=> (string) $this->_toZip,
            'destination_country'    => "BR",
            'destination_city'       => "SP",
            'origin_postal_code'     => (string) $this->_fromZip,
            'origin_country'         => "",
            'origin_state'           => "",
            'origin_city'            => "",
            'products'               => $this->_products,
        ];

        return json_encode($response);
    }
}
