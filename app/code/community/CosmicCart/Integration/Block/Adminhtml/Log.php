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

class CosmicCart_Integration_Block_Adminhtml_Log extends Mage_Adminhtml_Block_Widget_Grid_Container
{
	public function __construct()
	{
		$this->_controller = 'adminhtml_log';
		$this->_blockGroup = 'cosmiccart_integration';
		parent::__construct();
		$this->_headerText = Mage::helper('cosmiccart_integration')->__('Cosmiccart Logs');
		$this->_removeButton('add');
	}
}
