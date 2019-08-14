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


class CosmicCart_Integration_Model_Observer
{
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session');
    }

    public function onSalesOrderShipmentSaveAfter(Varien_Event_Observer $observer)
    {
        if (Mage::getStoreConfigFlag('cosmiccart/options/debug') == false) {
            /* We are observing all shipments, but we are really interested in only those resulting from a Cosmic Cart purchase. */
            $shipment = $observer->getEvent()->getShipment();
            if (!$shipment) {
                return $observer;
            }

            if ($shipment->getCosmiccartProcessed()) {
                return $observer;
            }

            $resource = Mage::getSingleton('core/resource');

            $id = $shipment->getId();
            $query = sprintf("UPDATE %s SET cosmiccart_processed = 1 WHERE entity_id = %s", $resource->getTableName('sales/shipment'), $id);
            Mage::getSingleton('core/resource')->getConnection('core_write')->exec($query);

            $order = $shipment->getOrder();
            if (!$order) {
                return $observer;
            }

            $cosmicOrderId = $order->getCosmicCartOrderId();
            if (!empty($cosmicOrderId)) {
                $payment = $order->getPayment();
                if (!empty($payment)) {
                    /* We must have a Payment and that Payment must have been with our custom "cosmiccart" method */
                    $methodInstance = $payment->getMethodInstance();
                    if ($methodInstance->canRefund() == false) {
                        $package = $this->shipmentToPackage($shipment);
                        $client = Mage::getSingleton('cosmiccart_integration/oauth2client')->init();
                        try {
                            $client->shipAndSettle($package);
                        } catch (Exception $e) {
                            $this->_getSession()->addError($e->getMessage());

                            $subOrderId = $package['subOrder']['id'];
                            $shipDate = $package['shipDate'];
                            $json = json_encode($package, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
                            Mage::getModel('cosmiccart_integration/mail')->sendEmail(
                                'cosmiccart_integration_package_shipped_tpl', 'api-fallback@cosmiccart.com', 'Cosmic Cart API Fallback Team', 'Package Shipped (Fallback Email)', array(
                                    'exception' => $e,
                                    'subOrderId' => $subOrderId,
                                    'shipDate' => $shipDate,
                                    'json' => $json
                                )
                            );
                            /*code added by vishal as per the kit suggesstions*/
                            $shipment->delete();
                            $items = $order->getAllVisibleItems();
                            foreach ($items as $item) {
                                $item->setQtyShipped(0);
                                $item->save();
                            }
                            $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'Undo Shipment');
                            $order->save();
                            /*code added by vishal as per the kit suggesstions ends*/
                            throw new Exception('Could not settle card for Cosmic Cart order id ' . $subOrderId . '.');
                        }
                    }
                }
            }
        }
        return $observer;
    }

    public function onSalesOrderCreditmemoRefund(Varien_Event_Observer $observer)
    {
        Mage::log('Observed credit memo!', null, 'cosmiccart.log', true);
        if (Mage::getStoreConfigFlag('cosmiccart/options/debug') == false) {
            /* We are observing all creditmemos, but we are really interested in only those resulting from a Cosmic Cart purchase. */
            $creditMemo = $observer->getEvent()->getCreditmemo();
            if (!$creditMemo) {
                return $observer;
            }

            $order = $creditMemo->getOrder();
            if (!$order) {
                return $observer;
            }

            $cosmicOrderId = $order->getCosmicCartOrderId();

            Mage::log('Refunding CC order:', null, 'cosmiccart.log', true);

            if (!empty($cosmicOrderId)) {
                $payment = $order->getPayment();
                if (!empty($payment)) {
                    /* We must have a Payment and that Payment must have been with our custom "cosmiccart" method */
                    $methodInstance = $payment->getMethodInstance();
                    if ($methodInstance->canRefund() == false) {
                        $refundRequest = $this->getCcRequestObject($creditMemo);
                        $client = Mage::getSingleton('cosmiccart_integration/oauth2client')->init();
                        try {
                            $client->refund($cosmicOrderId, $refundRequest);
                        } catch (Exception $e) {
                            $this->_getSession()->addError($e->getMessage());
                            Mage::log('OOPS! ' . $e->getMessage(), null, 'cosmiccart.log', true);

                            $json = json_encode($refundRequest, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
                            Mage::log($json, null, 'cosmiccart.log', true);

                            Mage::getModel('cosmiccart_integration/mail')->sendEmail(
                                'cosmiccart_integration_refund_exception_tpl', 'api-fallback@cosmiccart.com',
                                'Cosmic Cart API Fallback Team', 'Refund Exception (Fallback Email)',
                                array(
                                    'exception' => $e,
                                    'subOrderId' => $cosmicOrderId,
                                    'json' => $json
                                )
                            );
                            throw new Exception('Could not issue refund for Cosmic Cart order id ' . $cosmicOrderId . '.');
                        }
                    }
                }
            }
        }
        return $this;
    }

    private function shipmentToPackage(Mage_Sales_Model_Order_Shipment $shipment)
    {
        $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $shipment->getCreatedAt());
        $package = array(
            'subOrder' => array('id' => (int)$shipment->getOrder()->getCosmicCartOrderId()),
            'packageItems' => array(),
            'shipDate' => $dateTime->format('c'),
            'trackings' => array()
        );
        foreach ($shipment->getTracksCollection() as $track) {
            $package['trackings'][] = $track->getNumber();
        }
        $packageItemNumber = 0;
        foreach ($shipment->getItemsCollection() as $item) {
            $orderItem = Mage::getModel('sales/order_item')->load($item->getOrderItemId());
            for ($i = 0; $i < $item->getQty(); ++$i) {
                $packageItem = array(
                    'orderItem' => array('id' => (int)$orderItem->getCosmicCartOrderItemId()),
                    'number' => $packageItemNumber
                );
                $package['packageItems'][] = $packageItem;
                $packageItemNumber++;
            }
        }
        return $package;
    }

    private function getCcRequestObject($creditmemo)
    {
        $items = array();
        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = Mage::getModel('sales/order_item')->load($item->getOrderItemId());
            array_push($items, array(
                'id' => $orderItem->getCosmicCartOrderItemId(),
                'quantity' => $item->getQty()
            ));
        }
        return array(
            'productAmount' => $creditmemo->getSubtotal(),
            'shippingAmount' => $creditmemo->getBaseShippingAmount(),
            'taxAmount' => $creditmemo->getBaseTaxAmount(),
            'shippingTaxAmount' => $creditmemo->getBaseShippingTaxAmount(),
            'adjustmentAmount' => $creditmemo->getBaseAdjustmentPositive(),
            'feeAmount' => $creditmemo->getBaseAdjustmentNegative(),
            'items' => $items
        );
    }


}