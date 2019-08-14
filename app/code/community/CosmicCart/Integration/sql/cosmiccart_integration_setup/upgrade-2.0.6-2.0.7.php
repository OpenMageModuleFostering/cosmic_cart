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

$table = $installer->getConnection()
    ->newTable($installer->getTable('cosmiccart_log'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true
    ))
    ->addColumn('message', Varien_Db_Ddl_Table::TYPE_TEXT, '64k', array(),'Message')
    ->addColumn('severity', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(),'Severity')
    ->addColumn('type', Varien_Db_Ddl_Table::TYPE_TEXT, 255, array(),'Type')
    ->addColumn('created_at', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(),'Created At');
$installer->getConnection()->createTable($table);

$installer->endSetup();
