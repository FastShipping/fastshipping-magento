<?php

/** @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();

/* @var $installer Mage_Catalog_Model_Resource_Eav_Mysql4_Setup */
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

// Add volume to prduct attribute set
$codigo = 'token_fastshipping';
$config = array(
    'position' => 1,
    'required' => 0,
    'label'    => 'Token FastShipping',
    'type'     => 'int',
    'input'    => 'text',
    'apply_to' => 'simple,bundle,grouped,configurable',
    'note'     => 'Token de acesso para Fast Shipping'
);

$setup->addAttribute('admin_user', $codigo, $config);

$installer->endSetup();
