<?php
/*
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Cosmic Cart license, a commercial license.
 *
 * @category   CosmicCart
 * @package    Integration
 * @copyright  Copyright (c) 2015 Cosmic Cart, Inc.
 * @license    CosmicCart Software License https://cosmiccart.com/help/license/software
 */

$installer = $this;
$installer->startSetup();
$cosmicCartOrderIdAttr = array(
    'type' => 'varchar',
    'label' => "Cosmic Cart Id",
    'required' => false
);
$installer->addAttribute('quote', 'cosmic_cart_order_id', $cosmicCartOrderIdAttr);
$installer->addAttribute('order', 'cosmic_cart_order_id', $cosmicCartOrderIdAttr);

$installer->addAttribute('order_item', 'cosmic_cart_order_item_id', $cosmicCartOrderIdAttr);
$installer->addAttribute('quote_item', 'cosmic_cart_order_item_id', $cosmicCartOrderIdAttr);

$table = $installer->getConnection()
    ->newTable($installer->getTable('cosmiccart_integration_access_token'))
    ->addColumn('access_token', Varien_Db_Ddl_Table::TYPE_CHAR, 36, array(
        'nullable' => false,
        'primary' => true
    ))
    ->addColumn('token_type', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable' => false
    ))
    ->addColumn('refresh_token', Varien_Db_Ddl_Table::TYPE_CHAR, 36, array(
        'nullable' => false
    ))
    ->addColumn('expires', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false,
        'unsigned' => true
    ))
    ->addColumn('scope', Varien_Db_Ddl_Table::TYPE_VARCHAR, 32, array(
        'nullable' => false
    ));
$installer->getConnection()->createTable($table);

$table = $installer->getConnection()
    ->newTable($installer->getTable('cosmiccart_integration_client'))
    ->addColumn('client_id', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
        'nullable' => false,
        'primary' => true
    ))
    ->addColumn('client_secret', Varien_Db_Ddl_Table::TYPE_VARCHAR, 64, array(
        'nullable' => false
    ));
$installer->getConnection()->createTable($table);

Mage::app()->cleanCache();

$installer->endSetup();
