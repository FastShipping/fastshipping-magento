<?php

class FastShipping_Correios_Model_Source_WeightType
{
    /**
     * Constants for weight
     */
    const WEIGHT_GR = 'gr';
    const WEIGHT_KG = 'kg';

    /**
     * Get options for weight
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => self::WEIGHT_GR, 'label' => Mage::helper('adminhtml')->__('Gramas')),
            array('value' => self::WEIGHT_KG, 'label' => Mage::helper('adminhtml')->__('Kilos')),
        );
    }
}
