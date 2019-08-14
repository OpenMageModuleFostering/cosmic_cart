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


class CosmicCart_Cosmicapi_RestController extends Mage_Core_Controller_Front_Action
{

    public function authAction()
    {
        $isPost = $this->getRequest()->isPost();
        if (!$isPost) {
            $this->getResponse()->setHeader('HTTP/1.1', '403 Forbidden');
            return;
        }

        try {
            $params = $this->_getAuthParams();
        } Catch (Exception $e) {
            $this->sendInvalidCredentialsResponse($e->getMessage());
            return;
        }

        $response = Mage::helper('cosmicapi')->auth($params);
        $this->sendResponse($response);
    }

    public function getCatalogInventoryStockItemAction()
    {
        if ($this->validateRequest()) {
            try {
                $params = $this->_getStockItemParams();
                $productIds = $params['product_ids'];
                $products = Mage::getSingleton("cataloginventory/stock_item_api_v2")->items($productIds);
                if (count($products) == 0) {
                    $this->sendResponse(array(
                        'error' => 'The requested productIds were not found.',
                        'status' => 404,
                        'code' => 'PRODUCTS_NOT_FOUND'
                    ));
                } else {
                    $this->sendResponse($products);
                }
            } catch (Exception $e) {
                $this->sendUnexpectedError($e);
            }
        }
    }

    public function getShippingMethodsListAction()
    {
        if ($this->validateRequest()) {
            try {
                $params = $this->_getShippingMethodsListParams();

                $addressData = $params['address_data'];
                $orderItemsData = $params['order_items_data'];
                $store = $params['store'];

                $response = Mage::getModel('cosmiccart_integration/order_api_v2')
                    ->getShippingMethodsList($addressData, $orderItemsData, $store);
                $this->sendResponse($response);
            } catch (Exception $e) {
                $this->sendUnexpectedError($e);
            }
        }
    }

    public function getSalesTaxAction()
    {
        if ($this->validateRequest()) {
            try {
                $params = $this->_getSalesTaxParams();

                $addressData = $params['address_data'];
                $orderItemsData = $params['order_items_data'];
                $store = $params['store'];

                $response = Mage::getModel('cosmiccart_integration/order_api_v2')
                    ->getSalesTax($addressData, $orderItemsData, $store);
                $this->sendResponse($response);
            } catch (Exception $e) {
                $this->sendUnexpectedError($e);
            }
        }
    }

    public function orderCreateAction()
    {
        if ($this->validateRequest()) {
            try {
                $params = $this->_getOrderCreateParams();
                $orderData = $params['order_data'];
                $store = $params['store'];
                $response = Mage::getModel('cosmiccart_integration/order_api_v2')->create($orderData, $store);
                $this->sendResponse($response);
            } catch (Exception $e) {
                $this->sendUnexpectedError($e);
            }
        }
    }

    private function _getAuthParams()
    {
        $params = $this->_getPostParams('Credentials not supplied');
        if (!isset($params['username']) OR !isset($params['password'])) {
            Mage::throwException('Credentials not supplied properly');
        }

        return $params;
    }

    private function _getStockItemParams()
    {
        $params = $this->_getPostParams('Product Ids not supplied');
        if (!isset($params['product_ids'])) {
            Mage::throwException('Product Ids not supplied properly');
        }

        return $params;
    }

    private function _getSalesTaxParams()
    {
        return $this->_getShippingMethodsListParams();
    }

    private function _getOrderCreateParams()
    {
        $params = $this->_getPostParams('Order data is not supplied');
        if (!isset($params['order_data'])) {
            Mage::throwException('Order data not supplied properly');
        }

        if (!isset($params['store'])) {
            Mage::throwException('Store not supplied properly');
        }

        return $params;
    }

    private function _getShippingMethodsListParams()
    {
        $params = $this->_getPostParams('Address data not supplied');
        if (!isset($params['address_data'])) {
            Mage::throwException('Address data not supplied properly');
        }

        if (!isset($params['order_items_data'])) {
            Mage::throwException('Order items data not supplied properly');
        }

        if (!isset($params['store'])) {
            Mage::throwException('Store not supplied properly');
        }
        return $params;
    }

    private function _getPostParams($errMessage)
    {
        $postBody = $this->getRequest()->getRawBody();
        if (empty($postBody)) {
            Mage::throwException($errMessage);
        }

        $params = json_decode($postBody, true);
        if (empty($params) OR !is_array($params)) {
            Mage::throwException($errMessage);
        }

        return $params;
    }

    private function sendInvalidCredentialsResponse($message)
    {
        return $this->sendResponse(array(
            'error' => $message,
            'status' => 401,
            'code' => 'INVALID_CREDENTIALS'
        ));
    }

    private function sendResponse($response)
    {
        $this->getResponse()->clearHeaders()->setHeader('Content-Type', 'application/json', true);
        if (!is_object($response) && isset($response['status'])) {
            $this->getResponse()->setHeader('HTTP/1.0', $response['status'], true);
            unset($response['status']);
        }
        $this->getResponse()->setBody(json_encode($response));
        return $this;
    }

    private final function validateRequest()
    {
        $result = Mage::helper('cosmicapi')->validateData($this->getRequest());
        if (true !== $result AND isset($result['status'])) {
            $this->sendResponse($result);
            return false;
        }
        return true;
    }

    private function sendUnexpectedError($e)
    {
        $response['error'] = 'An unexpected error occurred.';
        $response['reason'] = $e->getMessage();
        $response['status'] = 400;
        $response['code'] = 'UNKNOWN';
        $this->sendResponse($response);
    }
}
