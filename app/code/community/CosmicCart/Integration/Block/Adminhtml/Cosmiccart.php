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

class CosmicCart_Integration_Block_Adminhtml_Cosmiccart extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  public function __construct()
  {
    $this->_controller = 'adminhtml_cosmiccart';
    $this->_blockGroup = 'cosmiccart_integration';
    $this->_headerText = Mage::helper('cosmiccart_integration')->__('Cosmiccart Manager');
    $this->_addButtonLabel = Mage::helper('cosmiccart_integration')->__('Add Cosmiccart');
	parent::__construct();
	$this->_removeButton('add');

    $_url = Mage::getModel('adminhtml/url')->getUrl(
      'adminhtml/integration_batch/new',
      null
    );

    $this->addButton(
      'create_prescription',
      array(
          'label'     => Mage::helper('cosmiccart_integration')->__('Start new batch'),
          'onclick'   => 'setLocation(\''.$_url.'\')',
          'class'     => 'add'
      )
    );
  }
}
