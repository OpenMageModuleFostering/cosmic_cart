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
    ->newTable($installer->getTable('cosmiccart_batch_status'))
    ->addColumn('batch_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true
    ))
    ->addColumn('start_time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        ), 'Start Time')
    ->addColumn('end_time', Varien_Db_Ddl_Table::TYPE_TIMESTAMP, null, array(
        ), 'End Time')
    ->addColumn('total_row_count', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false
     ))
    ->addColumn('config_total_row_count', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false
     ))
     ->addColumn('number_of_processed_row', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false
     ))
     ->addColumn('config_number_of_processed_row', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false
     ))
     ->addColumn('current_stage', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false,
        'default'  => '0'
     ))
     ->addColumn('comment', Varien_Db_Ddl_Table::TYPE_TEXT, 32, array(
        'nullable' => false
     ))
     ->addColumn('num_of_times_retried', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable' => false
     ));
$installer->getConnection()->createTable($table);

$installer->endSetup();