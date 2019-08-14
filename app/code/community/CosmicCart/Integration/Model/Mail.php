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
 * Date: 4/18/14
 * Time: 6:30 PM
 */
class CosmicCart_Integration_Model_Mail extends Mage_Core_Model_Email_Template
{
    /**
     * Sends email.
     *
     * @param $templateId
     * @param $email
     * @param $name
     * @param $subject
     * @param array $params
     */
    public function sendEmail($templateId, $email, $name, $subject, $params = array())
    {
        $storeEmail = Mage::getStoreConfig('trans_email/ident_support/email');
        $sender = array('name' => 'Cosmic Cart Magento Module', 'email' => $storeEmail);
        $this->setDesignConfig(array('area' => 'frontend', 'store' => $this->getDesignConfig()->getStore()))
            ->setTemplateSubject($subject)
            ->sendTransactional(
                $templateId,
                $sender,
                $email,
                $name,
                $params
            );
    }
}