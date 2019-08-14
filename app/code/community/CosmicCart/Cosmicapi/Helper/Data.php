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


class CosmicCart_Cosmicapi_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function auth($data)
    {
        if (empty($data['username']) OR empty($data['password'])) {
            $jsonObject['error'] = 'Credentials not supplied';
            $jsonObject['status'] = 401;
            $jsonObject['code'] = 'INVALID_CREDENTIALS';
            return $jsonObject;
        }
        $username = $data['username'];
        $password = $data['password'];
        $isValid = Mage::getModel('api/user')->authenticate($username, $password);
        if ($isValid) {
            $jsonObject['token'] = $this->generateToken();
        } else {
            $jsonObject['error'] = 'Provided Username and Password is invalid';
            $jsonObject['status'] = 401;
            $jsonObject['code'] = 'INVALID_CREDENTIALS';
        }
        return $jsonObject;
    }

    public function generateToken()
    {
        $token = Mage::helper('core')->getRandomString(50);
        $this->saveToken($token);
        return $token;
    }

    public function saveToken($token)
    {
        $newTokenObject = Mage::getModel('cosmicapi/cosmicapi');
        $newTokenObject->setTokenKey($token)->save();
        return;
    }

    private final function isValid($token)
    {
        $collection = Mage::getModel('cosmicapi/cosmicapi')->getCollection();
        $collection->addFieldToFilter('token_key', $token);

        if ($collection->getSize() < 1) {
            return false;
        }

        $tokenObject = $collection->getFirstItem();
        $data = $tokenObject->getData();

        if (!empty($data)) {
            return true;
        } else {
            return false;
        }
    }

    public final function validateData($request)
    {
        $token = $request->getHeader('X-CCAPI-Token');
        if (!isset($token)) {
            $response['error'] = 'No token attached';
            $response['status'] = 401;
            $response['code'] = 'MISSING_TOKEN';
            return $response;
        } else if (!$this->isValid($token)) {
            $response['error'] = 'Invalid token provided';
            $response['status'] = 401;
            $response['code'] = 'INVALID_TOKEN';
            return $response;
        }

        return true;
    }

}
