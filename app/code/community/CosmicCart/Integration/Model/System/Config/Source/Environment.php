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


class CosmicCart_Integration_Model_System_Config_Source_Environment
{
    const COSMICCART_ENV_STAGING = 1;
    const COSMICCART_ENV_PRODUCTION = 2;
    const COSMICCART_ENV_LOCAL = 3;

    public function toOptionArray()
    {
        return array(
            array('value' => self::COSMICCART_ENV_STAGING, 'label' => Mage::helper('cosmiccart_integration')->__('Staging')),
            array('value' => self::COSMICCART_ENV_PRODUCTION, 'label' => Mage::helper('cosmiccart_integration')->__('Production')),
            array('value' => self::COSMICCART_ENV_LOCAL, 'label' => Mage::helper('cosmiccart_integration')->__('Local (Dev only)')),
        );
    }

}