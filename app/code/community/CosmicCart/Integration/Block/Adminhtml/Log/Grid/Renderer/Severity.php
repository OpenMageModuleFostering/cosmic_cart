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
class CosmicCart_Integration_Block_Adminhtml_Log_Grid_Renderer_Severity
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @param   Varien_Object $row
     * @return  string
     */
     public function render(Varien_Object $row)
     {
        return Mage::helper('cosmiccart_integration')->getLoggingOptionsArray()[$row->getSeverity()];
     }
}
