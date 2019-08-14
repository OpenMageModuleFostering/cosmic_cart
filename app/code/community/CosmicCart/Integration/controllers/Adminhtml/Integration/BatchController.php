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

class CosmicCart_Integration_Adminhtml_Integration_BatchController extends Mage_Adminhtml_Controller_Action
{
    // default action
    public function indexAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('cosmiccart');
        $this->renderLayout();
    }

    public function newAction()
    {
        $response = $this->getResponse();
        $model = Mage::getSingleton('cosmiccart_integration/exporter');

        //check if batch is already running
        $batchCollection = Mage::getResourceModel('cosmiccart_integration/batch_collection');
        $batchCollection->addFieldToFilter('current_stage', array('nin' => array('5', '6')));

        try {

            $hasRunning = $batchCollection->getSize();
            if ($hasRunning) {
                Mage::throwException('Can not start new batch while one is still running. Please try again later!');
                return;
            }

            $model->exportCatalog('new');
            $message = Mage::helper('cosmiccart_integration')->__('A new batch has been scheduled successfully and will begin within 5 minutes.');
            Mage::getSingleton('adminhtml/session')->addSuccess($message);
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $_url = Mage::getModel('adminhtml/url')->getUrl(
            'adminhtml/integration_batch',
            null
        );

        $response->setRedirect($_url);
        return;
    }

    /**
     * Mass Delete Actions function
     *
     * @return  void
     */
    public function massDeleteAction()
    {
        $cosmiccartIds = $this->getRequest()->getParam('cosmiccart', null);
        if (is_null($cosmiccartIds) OR !is_array($cosmiccartIds) OR count($cosmiccartIds) < 1) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Please select batch(s)'));
        } else {
            try {
                foreach ($cosmiccartIds as $cosmiccartId) {
                    $_batch = Mage::getModel('cosmiccart_integration/batch')->load($cosmiccartId);
                    if ($_batch AND $_batch->getId()) {
                        $_batch->delete();
                    }
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('adminhtml')->__(
                        'Total of %d record(s) were successfully deleted', count($cosmiccartIds)
                    )
                );
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }
}
