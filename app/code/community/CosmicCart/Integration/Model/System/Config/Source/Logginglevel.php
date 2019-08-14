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


class CosmicCart_Integration_Model_System_Config_Source_Logginglevel
{
    const COSMICCART_LOGGING_ERROR = 1;
    const COSMICCART_LOGGING_WARN = 2;
    const COSMICCART_LOGGING_INFO = 3;
    const COSMICCART_LOGGING_VERBOSE = 4;


    public function toOptionArray()
    {
        return array(
            array('value' => self::COSMICCART_LOGGING_ERROR, 'label' => Mage::helper('cosmiccart_integration')->__('Error')),
            array('value' => self::COSMICCART_LOGGING_WARN, 'label' => Mage::helper('cosmiccart_integration')->__('Warn')),
            array('value' => self::COSMICCART_LOGGING_INFO, 'label' => Mage::helper('cosmiccart_integration')->__('Info')),
            array('value' => self::COSMICCART_LOGGING_VERBOSE, 'label' => Mage::helper('cosmiccart_integration')->__('Verbose'))
        );
    }

}
