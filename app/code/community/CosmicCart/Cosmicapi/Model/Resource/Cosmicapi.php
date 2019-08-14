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


class CosmicCart_Cosmicapi_Model_Resource_Cosmicapi extends Mage_Core_Model_Resource_Db_Abstract {

    public function _construct() {
        $this->_init('cosmicapi/cosmicapi', 'token_id');
    }

}
