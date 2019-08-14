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


class CosmicCart_Integration_Model_Batch extends Mage_Core_Model_Abstract
{
    public function _construct()
    {
        parent::_construct();
        $this->_init('cosmiccart_integration/batch');
    }

    public function startBatchSession()
    {
        $data = array();
        $data['start_time'] = '';
        $data['end_time'] = '';
        $data['total_row_count'] = 0;
        $data['config_total_row_count'] = 0;
        $data['number_of_processed_row'] = 0;
        $data['config_number_of_processed_row'] = 0;
        $data['current_stage'] = 0;
        $data['comment'] = 'Initializing...';
        $data['num_of_times_retried'] = 0;

        $this->setData($data)
            ->save();

        return $this;
    }
}
