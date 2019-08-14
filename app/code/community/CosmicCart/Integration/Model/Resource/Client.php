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
 * Date: 3/19/14
 * Time: 9:50 AM
 * To change this template use File | Settings | File Templates.
 */
class CosmicCart_Integration_Model_Resource_Client extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'cosmiccart_client';
    /**
     * Event object
     *
     * @var string
     */
    protected $_eventObject = 'resource';
    /**
     * Is grid
     *
     * @var boolean
     */
    protected $_grid = false;
    /**
     * Use increment id
     *
     * @var boolean
     */
    protected $_useIncrementId = false;
    /**
     * Primery key auto increment flag
     *
     * @var bool
     */
    protected $_isPkAutoIncrement = false;

    public function _construct()
    {
        $this->_init('cosmiccart_integration/client', 'client_id');
    }
}