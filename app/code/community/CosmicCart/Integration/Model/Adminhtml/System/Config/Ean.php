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

class CosmicCart_Integration_Model_Adminhtml_System_Config_Ean extends Mage_Core_Model_Config_Data
{
	public function save()
	{
		$ean = $this->getValue();

		// Using the SKU as the attribute causes a SQL error
		if (strtolower($ean) == "sku") {
			Mage::throwException("SKU cannot be used as the EAN/UPC attribute.");
		}

		// Check to see if the attribute they entered is a valid attribute
		$attr = Mage::getResourceModel('catalog/eav_attribute')
				->loadByCode('catalog_product',$ean);

		if (!$attr->getId() && ($ean != "")) {
			Mage::throwException($ean . ' is not a valid attribute.');
		}

		return parent::save();
	}
}
