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

$installer->getConnection()
    ->addColumn(
        $installer->getTable('sales/shipment'),
        'cosmiccart_processed',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_SMALLINT,
            'nullable' => false,
            'default' => '0',
            'comment' => 'Cosmiccart processed'
        )
    );

$installer->endSetup();