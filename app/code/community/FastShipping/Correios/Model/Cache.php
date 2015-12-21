<?php

class FastShipping_Correios_Model_Cache extends Varien_Object
{
    /**
     * Code property
     *
     * @var string
     */
    protected $_code = 'fastshipping_correios';

    /**
     * Core cache instance
     *
     * @var Zend_Cache_Core
     */
    private $_cache = null;

    /**
     * Retrieve cache instance.
     *
     * @return Zend_Cache_Core
     */
    private function getCache()
    {
        if ($this->_cache == null) {
            $this->_cache = Mage::app()->getCache();
        }
        return $this->_cache;
    }

    /**
     * Retrieve the cache content.
     *
     * @return string
     */
    public function load()
    {
        $data = $this->loadById();
        if (!$data) {
            $data = $this->loadByTags();
        }
        return $data;
    }

    /**
     * Retrieve the cache content by key.
     *
     * @return string
     */
    public function loadById()
    {
        $id   = $this->_getId();
        $data = $this->getCache()->load($id);
        if ($data) {
            Mage::log("{$this->_code} [cache]: mode={$this->getConfigData('cache_mode')} status=hit");
        }
        return $data;
    }

    /**
     * Validate the response data from Correios.
     * This method will choose between Request Cache or Save in Cache
     * 
     * Step 1:
     *     Invalid responses must call the Cache load.
     *     Cache loading is requested by throwing adapter exception.
     *     
     * Step 2:
     *     To save valid responses, it must contain no errors.
     *     Errors are detected by pattern_nocache and returns false.
     *
     * @param string $data XML Content
     * 
     * @throws Zend_Http_Client_Adapter_Exception
     *
     * @return boolean
     */
    protected function _isValidCache($data)
    {
        // Step 1
        try {
            $response = Zend_Http_Response::fromString($data);
            $content = $response->getBody();
        } catch (Zend_Http_Exception $e) {
            throw new Zend_Http_Client_Adapter_Exception($e->getMessage());
        }
        
        if (empty($content)) {
            throw new Zend_Http_Client_Adapter_Exception();
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if (!$xml || !isset($xml->cServico)) {
            throw new Zend_Http_Client_Adapter_Exception();
        }
        
        // Step 2
        $pattern = $this->getConfigData('pattern_nocache');
        if ($pattern != '' && preg_match($pattern, $content, $matches)) {
            return false;
        }
        return true;
    }

    /**
     * Save Correios content, tags and expiration period.
     *
     * @param string $data XML Content
     *
     * @return boolean|PedroTeixeira_Correios_Model_Cache
     */
    public function save($data)
    {
        if ($this->_isValidCache($data)) {
            $id = $this->_getId();
            $tags = $this->getCacheTags();
            if ($this->getCache()->save($data, $id, $tags)) {
                Mage::log("{$this->_code} [cache]: mode={$this->getConfigData('cache_mode')} status=write key={$id}");
            }
        }
        return $this;
    }

    /**
     * Retrieve information from carrier configuration
     *
     * @param string $field Field
     *
     * @return mixed
     */
    public function getConfigData($field)
    {
        if (empty($this->_code)) {
            return false;
        }
        $path = 'carriers/' . $this->_code . '/' . $field;
        return Mage::getStoreConfig($path);
    }
}
