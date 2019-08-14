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


class CosmicCart_Integration_Block_Adminhtml_Cosmiccart_Grid extends Mage_Adminhtml_Block_Widget_Grid
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
      $collection = Mage::getModel('cosmiccart_integration/batch')->getCollection();
      $this->setCollection($collection);
      return parent::_prepareCollection();
  }

  protected function _prepareColumns()
  {
      $this->addColumn('batch_id', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('ID'),
          'align'     =>'right',
          'width'     => '50px',
          'index'     => 'batch_id',
      ));

      $this->addColumn('start_time', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('Start Time'),
          'align'     =>'left',
          'index'     => 'start_time',
      ));

      $this->addColumn('end_time', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('End Time'),
          'align'     =>'left',
          'index'     => 'end_time',
      ));
      $this->addColumn('total_row_count', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('Simple Product Row Count'),
          'align'     =>'left',
          'index'     => 'total_row_count',
      ));
      $this->addColumn('number_of_processed_row', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('Simple Product Processed Row'),
          'align'     =>'left',
          'index'     => 'number_of_processed_row',
      ));
      $this->addColumn('config_total_row_count', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('Config Product Row Count'),
          'align'     =>'left',
          'index'     => 'config_total_row_count',
      ));
      $this->addColumn('config_number_of_processed_row', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('Config Product Processed Row'),
          'align'     =>'left',
          'index'     => 'config_number_of_processed_row',
      ));
      $this->addColumn('current_stage', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('Current Stage'),
          'align'     =>'left',
          'index'     => 'current_stage',
      ));
      $this->addColumn('comment', array(
          'header'    => Mage::helper('cosmiccart_integration')->__('Comment'),
          'align'     =>'left',
          'index'     => 'comment',
      ));

        return parent::_prepareColumns();
  }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('cosmiccart_id');
        $this->getMassactionBlock()->setFormFieldName('cosmiccart');

        $this->getMassactionBlock()->addItem('delete', array(
             'label'    => Mage::helper('cosmiccart_integration')->__('Delete'),
             'url'      => $this->getUrl('*/*/massDelete'),
             'confirm'  => Mage::helper('cosmiccart_integration')->__('Are you sure?')
        ));

        return $this;
    }

  public function getRowUrl($row)
  {
  }

}
