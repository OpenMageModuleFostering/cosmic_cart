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


class CosmicCart_Integration_Block_Adminhtml_Cosmiccart_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();
                 
        $this->_objectId = 'id';
        $this->_blockGroup = 'cosmiccart';
        $this->_controller = 'adminhtml_cosmiccart';
        
        $this->_updateButton('save', 'label', Mage::helper('cosmiccart_integration')->__('Save Cosmiccart'));
        $this->_updateButton('delete', 'label', Mage::helper('cosmiccart_integration')->__('Delete Cosmiccart'));
		
        $this->_addButton('saveandcontinue', array(
            'label'     => Mage::helper('adminhtml')->__('Save And Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ), -100);

        $this->_formScripts[] = "
            function toggleEditor() {
                if (tinyMCE.getInstanceById('cosmiccart_content') == null) {
                    tinyMCE.execCommand('mceAddControl', false, 'cosmiccart_content');
                } else {
                    tinyMCE.execCommand('mceRemoveControl', false, 'cosmiccart_content');
                }
            }

            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }
        ";
    }

    public function getHeaderText()
    {
        if( Mage::registry('cosmiccart_data') && Mage::registry('cosmiccart_data')->getId() ) {
            return Mage::helper('cosmiccart_integration')->__("Edit Cosmiccart '%s'", $this->htmlEscape(Mage::registry('cosmiccart_data')->getTitle()));
        } else {
            return Mage::helper('cosmiccart_integration')->__('Add Cosmiccart');
        }
    }
}
