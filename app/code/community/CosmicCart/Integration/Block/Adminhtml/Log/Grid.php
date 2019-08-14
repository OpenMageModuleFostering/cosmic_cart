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


class CosmicCart_Integration_Block_Adminhtml_Log_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('cosmiccartGrid');
        $this->setDefaultSort('batch_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('cosmiccart_integration/log')->getCollection();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn(
            'message',
            array(
                'header'    => Mage::helper('cosmiccart_integration')->__('Message'),
                'align'     => 'left',
                'index'     => 'message',
            )
        );

        $this->addColumn(
            'severity',
            array(
                'header'    => Mage::helper('cosmiccart_integration')->__('Severity'),
                'align'     => 'left',
                'width'     => '200px',
                'renderer'  => 'cosmiccart_integration/adminhtml_log_grid_renderer_severity',
                'type'    => 'options',
                'index' => 'severity',
                'options' => Mage::helper('cosmiccart_integration')->getLoggingOptionsArray()
            )
        );

        $this->addColumn(
            'type',
            array(
                'header'    => Mage::helper('cosmiccart_integration')->__('Type'),
                'align'     => 'left',
                'width'     => '200px',
                'index'     => 'type',
            )
        );

        $this->addColumn(
            'created_at',
            array(
                'header' => Mage::helper('cosmiccart_integration')->__('Date'),
                'index'  => 'created_at',
                'width'  => '160px',
                'type'   => 'datetime',
            )
        );

        return parent::_prepareColumns();
    }

}
