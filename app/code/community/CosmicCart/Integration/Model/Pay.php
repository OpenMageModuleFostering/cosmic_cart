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
 * Created by IntelliJ IDEA.
 * User: mcsenter
 * Date: 9/7/13
 * Time: 9:24 PM
 * To change this template use File | Settings | File Templates.
 */
class CosmicCart_Integration_Model_Pay extends Mage_Payment_Model_Method_Abstract
{
    protected $_code = 'cosmiccart';

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway               = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = false;

    /**
     * Can capture funds online?
     */
    protected $_canCapture              = false;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial       = false;

    /**
     * Can refund online?
     */
    protected $_canRefund               = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid                 = false;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = false;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = false;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = false;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

    public function canUseForCountry($country) {
        return 'US' === $country;
    }

    public function canUseForCurrency($currencyCode) {
        return 'USD' === $currencyCode;
    }
}