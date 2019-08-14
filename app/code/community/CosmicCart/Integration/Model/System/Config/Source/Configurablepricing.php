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


class CosmicCart_Integration_Model_System_Config_Source_Configurablepricing
{
    const COSMICCART_PRICING_CONFIGURABLE_OPTION = 1;
    const COSMICCART_PRICING_SIMPLE = 2;


    public function toOptionArray()
    {
        return array(
            array('value' => self::COSMICCART_PRICING_CONFIGURABLE_OPTION, 'label' => Mage::helper('cosmiccart_integration')->__('Configurable Plus Option Pricing')),
            array('value' => self::COSMICCART_PRICING_SIMPLE, 'label' => Mage::helper('cosmiccart_integration')->__('Simple Product Pricing'))
        );
    }

}
