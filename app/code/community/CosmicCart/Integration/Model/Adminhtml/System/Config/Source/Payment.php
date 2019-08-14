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


class CosmicCart_Integration_Model_Adminhtml_System_Config_Source_Payment
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return $this->_getPaymentMethods();
    }

    private function _getPaymentMethods()
    {
        $payments = Mage::getSingleton('payment/config')->getActiveMethods();
        $methods = array(array('value' => '', 'label' => Mage::helper('adminhtml')->__('--Please Select--')));

        foreach ($payments as $paymentCode => $paymentModel) {
            //Let's skip methods that can refund online
            try {
                if ($paymentModel->canRefund()) {
                    continue;
                }
            } catch (Exception $e) {
                Mage::log($e->getMessage());
                continue;
            }

            if ($paymentModel instanceof Mage_Payment_Model_Method_Cc) {
                continue;
            }

            if ($paymentCode == 'free') {
                continue;
            }

            $paymentTitle = Mage::getStoreConfig('payment/' . $paymentCode . '/title');
            $methods[$paymentCode] = array(
                'label' => $paymentTitle,
                'value' => $paymentCode,
            );
        }

        return $methods;
    }

}