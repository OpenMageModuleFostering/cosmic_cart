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


class CosmicCart_Cosmicapi_Model_Cosmicapi extends Mage_Core_Model_Abstract
{

    public function _construct()
    {
        parent::_construct();
        $this->_init('cosmicapi/cosmicapi');
    }

    public function invalidateTokens()
    {
        $tokenCollection = $this->getResourceCollection();

        if ($tokenCollection->getSize() > 0) {
            foreach ($tokenCollection as $token) {
                if ($token AND $token->getId()) {
                    $token->delete();
                }
            }
        }

    }

}
