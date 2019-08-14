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


/**
 * Client model
 *
 * @method string getClientId()
 * @method CosmicCart_Integration_Model_Client setClientId(string $value)
 * @method string getClientSecret()
 * @method CosmicCart_Integration_Model_Client setClientSecret(string $value)
 */
class CosmicCart_Integration_Model_Client extends Mage_Core_Model_Abstract
{
    protected $_resourceCollectionName = 'cosmiccart_integration/client_collection';

    protected function _construct()
    {
        $this->_init('cosmiccart_integration/client');
    }

    public function exists()
    {
        $existing = $this->findExisting();
        return !empty($existing);
    }

    public function findExisting()
    {
        $client = null;

        $collection = $this->getCollection();
        if ($collection->getSize() > 0) {
            $client = $collection->getFirstItem();
        }

        return $client;
    }

    public function deleteExisting()
    {
        foreach ($this->getCollection() as $client) {
            $client->delete();
        }
    }

}
