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


class CosmicCart_Integration_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CRON_EXPORT_ENABLED = 'cosmiccart/configurable_cron/enable';
    const COSMICCART_PAYMENT_METHOD_CODE = 'cosmiccart/options/payment_method';


    const COSMICCART_API_ENVIRONMENT_PATH = 'cosmiccart/options/environment';

    const COSMICCART_API_STAGING_URL_PATH = 'cosmiccart/options/staging_api_url';
    const COSMICCART_API_STAGING_SFTP_PATH = 'cosmiccart/options/staging_api_sftp';

    const COSMICCART_API_PRODUCTION_URL_PATH = 'cosmiccart/options/production_api_url';
    const COSMICCART_API_PRODUCTION_SFTP_PATH = 'cosmiccart/options/production_api_sftp';

    const COSMICCART_API_LOCAL_URL_PATH = 'cosmiccart/options/local_api_url';
    const COSMICCART_API_LOCAL_SFTP_PATH = 'cosmiccart/options/local_api_sftp';

    public function checkBatch($batch_id)
    {
        $batch = Mage::getModel('cosmiccart_integration/batch')->load($batch_id);
        //This will return batch Id or null
        return $batch->getId();
    }

    public function cronAutoexportEnabled()
    {
        return Mage::getStoreConfigFlag(self::CRON_EXPORT_ENABLED);
    }

    public function getCosmicCartPaymentMethod()
    {
        return Mage::getStoreConfig(self::COSMICCART_PAYMENT_METHOD_CODE);
    }

    public function getApiUrl()
    {
        $env = $this->getEnvironment();
        if ($env == CosmicCart_Integration_Model_System_Config_Source_Environment::COSMICCART_ENV_PRODUCTION) {
            return Mage::getStoreConfig(self::COSMICCART_API_PRODUCTION_URL_PATH);
        } else if ($env == CosmicCart_Integration_Model_System_Config_Source_Environment::COSMICCART_ENV_LOCAL) {
            return Mage::getStoreConfig(self::COSMICCART_API_LOCAL_URL_PATH);
        }
        //Return Staging as default
        return Mage::getStoreConfig(self::COSMICCART_API_STAGING_URL_PATH);
    }

    public function getSftpUrl()
    {
        $env = $this->getEnvironment();
        if ($env == CosmicCart_Integration_Model_System_Config_Source_Environment::COSMICCART_ENV_PRODUCTION) {
            return Mage::getStoreConfig(self::COSMICCART_API_PRODUCTION_SFTP_PATH);
        } else if ($env == CosmicCart_Integration_Model_System_Config_Source_Environment::COSMICCART_ENV_LOCAL) {
            return Mage::getStoreConfig(self::COSMICCART_API_LOCAL_SFTP_PATH);
        }
        //Return Staging as default
        return Mage::getStoreConfig(self::COSMICCART_API_STAGING_SFTP_PATH);
    }

    public function getEnvironment()
    {
        $env = Mage::getStoreConfig(self::COSMICCART_API_ENVIRONMENT_PATH);
        if (empty($env)) {
            //Set Staging as default if not set
            $env = CosmicCart_Integration_Model_System_Config_Source_Environment::COSMICCART_ENV_STAGING;
        }

        return $env;
    }


}