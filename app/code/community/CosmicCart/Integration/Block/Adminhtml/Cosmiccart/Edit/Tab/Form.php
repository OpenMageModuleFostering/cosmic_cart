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


class CosmicCart_Integration_Block_Adminhtml_Cosmiccart_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{
  protected function _prepareForm()
  {
      $form = new Varien_Data_Form();
      $this->setForm($form);
      $fieldset = $form->addFieldset('cosmiccart_form', array('legend'=>Mage::helper('cosmiccart_integration')->__('Item information')));
     
      $fieldset->addField('name', 'text', array(
          'label'     => Mage::helper('cosmiccart_integration')->__('Title'),
          'class'     => '',
          'required'  => true,
          'name'      => 'name',
      ));

      $fieldset->addField('cosmiccart_date', 'date', array(
          'label'     => Mage::helper('cosmiccart_integration')->__('Select Date'),
          'class'     => '',
          'required'  => true,
          'format'    => 'yyyy-MM-dd',
          'name'      => 'cosmiccart_date',
          'image'     =>    $this->getSkinUrl('images/grid-cal.gif')
      ));

      if ( Mage::getSingleton('adminhtml/session')->getCosmiccartData() )
      {
          $form->setValues(Mage::getSingleton('adminhtml/session')->getCosmiccartData());
          Mage::getSingleton('adminhtml/session')->setCosmiccartData(null);
      } elseif ( Mage::registry('cosmiccart_data') ) {
          $form->setValues(Mage::registry('cosmiccart_data')->getData());
      }
      return parent::_prepareForm();
  }
}
