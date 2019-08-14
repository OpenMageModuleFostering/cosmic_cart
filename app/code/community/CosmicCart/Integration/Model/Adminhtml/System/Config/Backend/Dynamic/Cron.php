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

class CosmicCart_Integration_Model_Adminhtml_System_Config_Backend_Dynamic_Cron extends Mage_Core_Model_Config_Data
{
    const CRON_STRING_PATH = 'crontab/jobs/integration_cosmiccart_generate_batch/schedule/cron_expr';

    protected function isEnabled()
    {
        $enabled =  $this->getData('groups/configurable_cron/fields/enable/value');
        $flag = strtolower($enabled);
        if (!empty($flag) && 'false' !== $flag) {
            return true;
        } else {
            return false;
        }
    }

    protected function _afterSave()
    {
        if (!$this->isEnabled()) {
            $config = Mage::getModel('core/config_data')->load(self::CRON_STRING_PATH, 'path');
            if ($config->getId()) {
                $config->delete();
            }
            return $this;
        }

        $time = $this->getData('groups/configurable_cron/fields/time/value');

        $cronExprArray = array(
            intval($time[1]),                                   # Minute
            intval($time[0]),                                   # Hour
            '*',                                                # Day of the Month
            '*',                                                # Month of the Year
            '*',                                                # Day of the Week
        );
        $cronExprString = join(' ', $cronExprArray);
        try {
            Mage::getModel('core/config_data')
                ->load(self::CRON_STRING_PATH, 'path')
                ->setValue($cronExprString)
                ->setPath(self::CRON_STRING_PATH)
                ->save();
        } catch (Exception $e) {
            throw new Exception(Mage::helper('cron')->__('Unable to save the cron expression.'));

        }

        return $this;
    }
}