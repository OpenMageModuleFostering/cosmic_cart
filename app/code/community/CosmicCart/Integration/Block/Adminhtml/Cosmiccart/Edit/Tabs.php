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


class CosmicCart_Integration_Block_Adminhtml_Cosmiccart_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{

  public function __construct()
  {
      parent::__construct();
      $this->setId('cosmiccart_tabs');
      $this->setDestElementId('edit_form');
      $this->setTitle(Mage::helper('cosmiccart_integration')->__('Cosmiccart Information'));
  }

  protected function _beforeToHtml()
  {
      $this->addTab('form_section', array(
          'label'     => Mage::helper('cosmiccart_integration')->__('Cosmiccart Information'),
          'title'     => Mage::helper('cosmiccart_integration')->__('Cosmiccart Information'),
          'content'   => $this->getLayout()->createBlock('cosmiccart/adminhtml_cosmiccart_edit_tab_form')->toHtml(),
      ));
     
      return parent::_beforeToHtml();
  }
}
