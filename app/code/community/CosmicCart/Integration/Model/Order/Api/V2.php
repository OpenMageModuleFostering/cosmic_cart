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

class CosmicCart_Integration_Model_Order_Api_V2 extends Mage_Sales_Model_Order_Api_V2
{
    public function create($orderData, $store)
    {
        $this->initErrorHandler($store);
        $response = null;
        try {

            $orderDataItems = isset($orderData['items']) ? $orderData['items'] : null;
            if (!$orderDataItems) {
                Mage::throwException('Order data items are missing!');
            }

            $ccOrderId = isset($orderData['order_id']) ? $orderData['order_id'] : null;
            $quoteInfo = $this->_createQuoteInfo($store, $orderDataItems, $ccOrderId);

            $response = $this->_createOrder($store, $orderData, $quoteInfo);

        } catch (Mage_Core_Exception $e) {
            throw $e;
        }
        return $response;
    }

    private function _createQuoteInfo($store, $orderItemsData, $cosmicCartOrderId = null)
    {
        /* Determine stock status for each requested item and make adjustments if necessary. */
        $itemsToAdd = array();
        $itemsToFail = array();
        $quote = null;

        foreach ($orderItemsData as $item) {

            $itemQty = isset($item['qty']) ? $item['qty'] : 0;

            /* Check the stock level of each item and sort appropriately. */
            $item['original_qty'] = $itemQty;

            $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $item['sku']);
            if ($product AND $product->getId() AND $product->isSaleable()) {

                $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
                if ($stockItem AND $stockItem->getId()) {

                    $inStock = $stockItem->getIsInStock();
                    $qtyInStock = $stockItem->getQty();

                    $isBackorderable = $stockItem->getBackorders() != Mage_CatalogInventory_Model_Stock::BACKORDERS_NO;

                    if ($inStock AND ($isBackorderable || $qtyInStock >= $item['qty'])) {
                        /* If we have plenty of stock or the item is backorderable, proceed as normal. */
                        $itemsToAdd[] = $item;
                    } else if ($inStock AND $qtyInStock > 0) {
                        /* If we only have a few we can allocate from our requested quantity, do that, and put the rest
                        in a failed item. */
                        $item['qty'] = $qtyInStock;
                        $itemsToAdd[] = $item;

                        $itemToFail = array();
                        $itemToFail['sku'] = $item['sku'];
                        $itemToFail['original_qty'] = $item['original_qty'];
                        $itemToFail['qty'] = $item['original_qty'] - $qtyInStock;
                        $itemToFail['qty_allocated'] = $itemToFail['original_qty'] - $itemToFail['qty'];
                        $itemsToFail[] = $itemToFail;
                    } else {
                        $item['qty_allocated'] = 0;
                        $itemsToFail[] = $item;
                    }
                } else {
                    $item['qty_allocated'] = 0;
                    $itemsToFail[] = $item;
                }
            } else {
                $item['qty_allocated'] = 0;
                $itemsToFail[] = $item;
            }
        }
        if (count($itemsToAdd) > 0) {
            /* Create the quote */
            $cartApi = Mage::getSingleton('checkout/cart_api_v2');
            $quoteId = $cartApi->create($store);
            /* Add the items to the quote */
            $cartProductApi = Mage::getSingleton('checkout/cart_product_api_v2');
            $cartProductApi->add($quoteId, $itemsToAdd, $store);

            /* The default api does not allow us to set custom pricing. So let's do that ourselves. */
            $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
            $quote->setCosmicCartOrderId($cosmicCartOrderId);

            $quoteItems = $quote->getItemsCollection();
            foreach ($quoteItems as &$quoteItem) {
                foreach ($itemsToAdd as $item) {
                    if ($item['sku'] == $quoteItem->getSku()) {
                        $quoteItem->setCosmicCartOrderItemId($item['order_item_id']);
                        $quoteItem->setOriginalCustomPrice($item['price']);
                        break;
                    }
                }
            }
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $quote->save();
        }
        $quoteInfo = array(
            'quote' => $quote,
            'itemsToAdd' => $itemsToAdd,
            'itemsToFail' => $itemsToFail
        );
        return $quoteInfo;
    }

    private function _createOrder($store, $orderData, $quoteInfo)
    {
        $itemStatuses = array();
        $quote = isset($quoteInfo['quote']) ? $quoteInfo['quote'] : null;
        $itemsToAdd = $quoteInfo['itemsToAdd'];
        $itemsToFail = $quoteInfo['itemsToFail'];
        $orderId = null;
        $orderIncrementId = null;

        $customer = isset($orderData['customer']) ? $orderData['customer'] : null;
        $customerAddresses = isset($orderData['customer_addresses']) ? $orderData['customer_addresses'] : null;
        $customerEmail = isset($orderData['customer']['email']) ? $orderData['customer']['email'] : null;

        $ccOrderId = isset($orderData['order_id']) ? $orderData['order_id'] : null;

        if ($customerAddresses AND is_array($customerAddresses) AND count($customerAddresses)) {
            foreach ($customerAddresses as $key => $val) {
                if (is_array($val)) {
                    $customerAddresses[$key] = (object)$val;
                }
            }
        }

        /* Break our results into those added and those backordered. */
        $itemsFailed = array();

        /* Can't add any items if none are available. */
        if ($quote AND count($itemsToAdd) > 0) {
            $quoteId = $quote->getId();
            /* Set the customer */
            $cartCustomerApi = Mage::getSingleton('checkout/cart_customer_api_v2');
            $cartCustomerApi->set($quoteId, (object)$customer, $store);
            $cartCustomerApi->setAddresses($quoteId, $customerAddresses, $store);

            /*
                Set the shipping method.

                What happens here is that Cosmic Cart has set a ShippingOption on each OrderItem in the Order.
                However, we must currently use the same ShippingOption for all OrderItems. #businessrule
            */
            $shippingOption = $itemsToAdd[0]['shipping_option'];

            $cartShippingApi = Mage::getSingleton('checkout/cart_shipping_api_v2');
            $cartShippingApi->setShippingMethod($quoteId, $shippingOption, $store);

            /* Set our custom payment method */
            $cartPaymentApi = Mage::getSingleton("checkout/cart_payment_api");

            $methodCode = Mage::helper('cosmiccart_integration')->getCosmicCartPaymentMethod();
            $methodCode = empty($methodCode) ? 'cosmiccart' : $methodCode;

            $cartPaymentApi->setPaymentMethod($quoteId, array('method' => $methodCode, '0' => null), $store);

            /* Due to interesting Magento API goings-on around guest customers, we have to set the billing email */
            $quote = Mage::getModel('sales/quote')->loadByIdWithoutStore($quoteId);
            $quote->getBillingAddress()->setEmail($customerEmail);
            $quote->getBillingAddress()->save();

            /* Convert our cart to an order */
            $cartApi = Mage::getSingleton("checkout/cart_api_v2");

            $orderId = $cartApi->createOrder($quoteId, $store);
            $order = $this->_initOrder($orderId);

            $order->setCustomerEmail($customerEmail);
            $order->getBillingAddress()->setEmail($customerEmail);

            $order->getShippingAddress()->setEmail($customerEmail);
            $order->setCosmicCartOrderId($ccOrderId);

            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'Funds have been authorized via Cosmic Cart. Ship to settle.');
            $order->save();

            /* Invoice (pay for) our order. */
            $salesOrderInvoiceApi = Mage::getSingleton('sales/order_invoice_api_v2');
            $invoiceId = $salesOrderInvoiceApi->create($orderId, array(), 'Payment authorized. Awaiting settlement via Cosmic Cart when items ship.');

            $invoice = Mage::getModel('sales/order_invoice')->loadByIncrementId($invoiceId);
            $invoice->pay();

            Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder())
                ->save();

            /* Gotta reload the order to get a version with all the updates performed by the invoicing. */
            $order = $this->_initOrder($orderId);
            $orderIncrementId = $order->getIncrementId();

            foreach ($order->getItemsCollection() as $item) {
                $itemStatus = array();
                $itemStatus['qty_failed'] = 0; // Default
                $itemStatus['sku'] = $item->getSku();

                foreach ($itemsToAdd as $itemRequested) {

                    if ($itemRequested['sku'] == $itemStatus['sku']) {
                        $orderItemId = isset($itemRequested['order_item_id']) ? $itemRequested['order_item_id'] : null;

                        $item->setCosmicCartOrderItemId($orderItemId);
                        $item->save();

                        $originalQty = isset($itemRequested['original_qty']) ? $itemRequested['original_qty'] : 0;
                        $itemStatus['qty_requested'] = $originalQty;
                        break;
                    }

                }

                $itemStatus['tax'] = $item->getTaxAmount();
                $itemStatus['qty_backordered'] = $item->getQtyBackordered();

                foreach ($itemsToFail as $itemToFail) {
                    if ($itemToFail['sku'] == $itemStatus['sku']) {
                        $itemStatus['qty_failed'] = $itemToFail['qty'];
                        $itemsFailed[] = $itemStatus;
                        break;
                    }
                }
                $itemStatus['qty_allocated'] = $itemStatus['qty_requested'] - $itemStatus['qty_backordered'] - $itemStatus['qty_failed'];
                $itemStatuses[] = $itemStatus;
            }
        }

        $itemStatuses = array_merge($itemStatuses, $this->_getStatusesForFailedItems($itemsToFail, $itemsFailed));
        return array(
            'order_id' => $orderId,
            'customer_order_number' => $orderIncrementId,
            'items' => $itemStatuses
        );
    }

    protected function _getStatusesForFailedItems($itemsToFail = array(), $itemsAlreadyFailed = array())
    {
        $itemStatuses = array();
        /* Completely failed items won't be returned by the other API calls, but we still need to return a status for them. */
        foreach ($itemsToFail as $itemToFail) {
            $yetToFail = true;
            foreach ($itemsAlreadyFailed as $itemAlreadyFailed) {
                if ($itemAlreadyFailed['sku'] == $itemToFail['sku']) {
                    $yetToFail = false;
                    break;
                }
            }
            if ($yetToFail) {
                $itemStatus = array();
                $itemStatus['sku'] = $itemToFail['sku'];
                $itemStatus['qty_requested'] = $itemToFail['original_qty'];
                $itemStatus['qty_allocated'] = $itemToFail['qty_allocated'];
                $itemStatus['qty_backordered'] = 0;
                $itemStatus['qty_failed'] = $itemToFail['qty'];
                $itemStatus['tax'] = 0;
                $itemStatuses[] = $itemStatus;
            }
        }
        return $itemStatuses;
    }

    public function getSalesTax($addressData, $orderItemsData, $store)
    {
        $this->initErrorHandler($store);
        $salesTax = 0.0;
        $shippingTax = 0.0;

        $quoteInfo = $this->_createTemporaryQuote($store, $addressData, $orderItemsData);
        $quote = isset($quoteInfo['quote']) ? $quoteInfo['quote'] : null;

        if (!empty($quote) AND $quoteId = $quote->getId()) {
            $shippingAddress = $quote->getShippingAddress();
            $salesTax = $shippingAddress->getTaxAmount();
            if (!isset($salesTax)) {
                $salesTax = 0.0;
            }
            $shippingTax = $shippingAddress->getShippingTaxAmount();
            if (!isset($shippingTax)) {
                $shippingTax = 0.0;
            }
            $quote->getShippingAddress()->delete();
            $quote->delete();
        }
        $response = array();
        $response['sales_tax'] = $salesTax;
        $response['shipping_tax'] = $shippingTax;
        $response['failed_items'] = $this->_getStatusesForFailedItems($quoteInfo['itemsToFail']);
        return $response;
    }

    public function getShippingMethodsList($addressData, $orderItemsData, $store)
    {
        $this->initErrorHandler($store);
        $response = array();

        $quoteInfo = $this->_createTemporaryQuote($store, $addressData, $orderItemsData);
        $quote = isset($quoteInfo['quote']) ? $quoteInfo['quote'] : null;

        if (!empty($quote) AND $quoteId = $quote->getId()) {
            $cartShippingApi = Mage::getSingleton('checkout/cart_shipping_api_v2');
            $response['result'] = $cartShippingApi->getShippingMethodsList($quoteId, $store);
            $response['result'] = $this->_filterInvalidShippingMethods($response['result']);
            // Clean up anything we saved to the db.
            $quote->delete();
        } else {
            $response['result'] = array();
        }
        $response['failed_items'] = $this->_getStatusesForFailedItems($quoteInfo['itemsToFail']);
        return $response;
    }

    private function _filterInvalidShippingMethods($result) {
        $filteredResult = array();

        foreach ($result as $method) {
            if (strpos($method['code'], 'error') === false) {
                $filteredResult[] = $method;
            }
        }

        return $filteredResult;
    }

    private function _createTemporaryQuote($store, $addressData, $orderItemsData)
    {
        $quoteInfo = $this->_createQuoteInfo($store, $orderItemsData);
        $quote = isset($quoteInfo['quote']) ? $quoteInfo['quote'] : null;
        if (!empty($quote)) {
            $quote->getBillingAddress();

            // Create shipping address model
            $shippingAddress = Mage::getModel('sales/quote_address');
            $shippingAddress->addData($addressData);

            $shippingAddress->setAddressType('shipping');

            $shippingAddress->setCollectShippingRates(true);
            $quote->setShippingAddress($shippingAddress)->setCollectShippingRates(true);
            $quote->getShippingAddress()->save();

            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $quote->save();
        }
        $quoteInfo['quote'] = $quote;
        return $quoteInfo;
    }

    private function initErrorHandler($store)
    {
        Mage::getSingleton('cosmiccart_integration/errorHandler')->initErrorHandler($store);
    }

}
