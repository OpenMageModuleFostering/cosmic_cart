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
    ->newTable($installer->getTable('cosmicapi/cosmicapi'))
    ->addColumn('token_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        ), 'Id')
    ->addColumn('token_key', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
        'nullable'  => false,
        ), 'Token Key');
$installer->getConnection()->createTable($table);
$installer->endSetup();
