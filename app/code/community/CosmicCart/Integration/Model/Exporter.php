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

class CosmicCart_Integration_Model_Exporter
{
    private $_configurablesCollection = array();
    private $_simplesCollection = array();
    private $_configurablesIdsCollection = array();

    private $_pageSize = null;

    private $_configurableTypeInstance = null;

    private $_exportData = array();
    private $_processedCount = 0;

    private $_exportStoreId = null;
    private $_brandAttributeCode = null;
    private $_eanAttributeCode = null;

    public static $_exporterCron = false;
    public static $_batchGenerateCron = false;

    private $_store = null;
    private $_brandAttributeType = null;


    public function generateBatch()
    {
        if (!Mage::helper('cosmiccart_integration')->cronAutoexportEnabled()) {
            return $this;
        }

        //Prevent duplicated CRON running same time
        if (self::$_batchGenerateCron == true) {
            Mage::helper('cosmiccart_integration')->log('Duplicated cron job already running', 4, 'exporter');
            return $this;
        }
        Mage::helper('cosmiccart_integration')->log('Generating batch', 4, 'exporter');
        $this->exportCatalog('new');

        self::$_batchGenerateCron = true;
        return $this;
    }

    /**
     * Cronjob proxy
     *
     * @return CosmicCart_Integration_Model_Exporter
     */
    public function cronprocess()
    {
        //Prevent duplicated CRON running same time
        if (self::$_exporterCron == true) {
            return $this;
        }

        $process = Mage::helper('catalog/product_flat')->getProcess();
        $status = $process->getStatus();
        $process->setStatus(Mage_Index_Model_Process::STATUS_RUNNING);

        try {
            $this->exportCatalog();
        } Catch (Exception $e) {
            $process->setStatus($status);
            Mage::helper('cosmiccart_integration')->log($e, 1, 'exporter');
            Mage::throwException($e);
        }

        $process->setStatus($status);

        self::$_exporterCron = true;
        return $this;
    }

    public function getStore()
    {
        if (!$this->_store) {
            $this->_store = Mage::getStoreConfig('cosmiccart/store');
        }
        return $this->_store;
    }

    /**
     * Exports the default store's catalog to a gzipped json file on the Cosmic Cart sftp server.
     *
     * @throws Exception
     */
    public function exportCatalog($target_batch = null)
    {
        $store = $this->getStore();
        $storeObject = Mage::getSingleton('core/store')->load($store);

        $currency = $storeObject->getCurrentCurrencyCode();

        // Load our activated client. This is required or else the export cannot take place.
        $client = Mage::getModel('cosmiccart_integration/client')->findExisting();
        if (!$client) {
            throw new Exception('No client detected');
        }

        // Setup error notifications to ourselves.
        $this->initErrorHandler($store);
        $numWritten = 1;

        if ($target_batch != null) {
            // Create the new batch and start from here.
            Mage::getModel('cosmiccart_integration/batch')->startBatchSession();
            return $this;
        }

        $rows = $this->_getBatchRows();

        if (!$rows OR !is_array($rows) OR count($rows) < 1) {
            return $this;
        }

        foreach ($rows as $row) {
            // Based on the status do different things.
            // 0 - Not yet started (Default)
            // 1 - Getting Row counts
            // 2 - Processing rows
            // 3 - Merging files into one big file & Gzip the files
            // 4 - In process of FTP the file
            // 5 - Completed
            // 6 - Fatal error
            try {

                if ($row['current_stage'] == 0) {
                    $row = $this->_handleStage0($row, $store);
                }

                // Start the process
                if ($row['current_stage'] == 1) {
                    $row = $this->_handleStage1($row);
                }

                if ($row['current_stage'] == 2) {
                    $row = $this->_handleStage2($row, $store, $currency);
                }

                if ($row['current_stage'] == 3) {
                    $row = $this->_handleStage3($row);
                }

                if ($row['current_stage'] == 4) {
                    $row = $this->_handleStage4($row, $client);
                }

            } catch (Exception $error) {
                // If it failed for whatever reason, log it into the comment field and increment num_of_retries of 1
                $data = array();
                $row['num_of_times_retried'] = $row['num_of_times_retried'] + 1;
                $data['num_of_times_retried'] = $row['num_of_times_retried'];
                $data['comment'] = $error->getMessage();
                Mage::helper('cosmiccart_integration')->log($error->getMessage(), 1, 'exporter');

                $where = $this->_getWriteConnection()->quoteInto('batch_id =?', $row['batch_id']);
                $this->_getWriteConnection()->update($this->_getTableName('cosmiccart_integration/batch'), $data, $where);

                if ($row['num_of_times_retried'] >= 3) {
                    // Mark this process fatal error
                    $data['current_stage'] = 6;
                    $where = $this->_getWriteConnection()->quoteInto('batch_id =?', $row['batch_id']);
                    $this->_getWriteConnection()->update($this->_getTableName('cosmiccart_integration/batch'), $data, $where);
                    $row['current_stage'] = 6;
                }
            }
        }

        return $this;
    }

    private function _handleStage0($row, $store)
    {
        Mage::helper('cosmiccart_integration')->log('Beginning stage 0', 4, 'exporter');
        // First, let's find all the configurable products that are visible and enabled (PRODUCT)
        $configurables_collection = $this->_getConfigurablesCollection($store);
        $simple_collection = $this->_getSimplesCollection($store);

        // Now find out how much record is returning
        $configurables_collection_count = $configurables_collection->getSize();
        $simple_collection_count = $simple_collection->getSize();

        Mage::helper('cosmiccart_integration')->log("Found $configurables_collection_count configurable products", 4, 'exporter');
        Mage::helper('cosmiccart_integration')->log("Found $simple_collection_count simple products", 4, 'exporter');

        // Update the row count now
        $data = array();
        $data['total_row_count'] = $simple_collection_count;
        $data['config_total_row_count'] = $configurables_collection_count;
        $data['current_stage'] = 1;
        $data['comment'] = 'Scheduled';
        $data['start_time'] = date("Y-m-d H:i:s");

        $where = $this->_getWriteConnection()->quoteInto('batch_id =?', $row['batch_id']);
        $this->_getWriteConnection()->update($this->_getTableName('cosmiccart_integration/batch'), $data, $where);

        $row['total_row_count'] = $simple_collection_count;
        $row['config_total_row_count'] = $configurables_collection_count;
        $row['current_stage'] = 1;

        return $row;
    }

    private function _handleStage1($row)
    {
        Mage::helper('cosmiccart_integration')->log('Beginning stage 1', 4, 'exporter');
        $data = array();
        $data['current_stage'] = 2;
        $data['comment'] = 'Processing rows';
        $where = $this->_getWriteConnection()->quoteInto('batch_id =?', $row['batch_id']);
        $this->_getWriteConnection()->update($this->_getTableName('cosmiccart_integration/batch'), $data, $where);

        $row['current_stage'] = 2;
        return $row;
    }

    private function _handleStage2($row, $store, $currency)
    {
        Mage::helper('cosmiccart_integration')->log('Beginning stage 2', 4, 'exporter');
        // Process the export
        $configurables_collection = $this->_getConfigurablesCollection($store);
        $simple_collection = $this->_getSimplesCollection($store);

        Mage::helper('cosmiccart_integration')->log('Found ' . $configurables_collection->getSize() . ' configurable products', 4, 'exporter');
        Mage::helper('cosmiccart_integration')->log('Found ' . $simple_collection->getSize() . ' simple products', 4, 'exporter');

        // Work on configurable first
        try {
            $numWritten = 0;
            if ($row['config_number_of_processed_row'] < $row['config_total_row_count']) {
                $numWritten = $this->exportCollection(
                    $store,
                    $currency,
                    $row['config_total_row_count'],
                    $configurables_collection,
                    $row['config_number_of_processed_row'],
                    $row['batch_id'],
                    true
                );
            }
        } catch (Exception $e) {
            Mage::helper('cosmiccart_integration')->log(__FILE__ . ' ' . __LINE__ . ' ' . " Error: " . $e->getMessage(), 1, 'exporter');
            Mage::log(__FILE__ . ' ' . __LINE__ . ' ' . " Error: " . $e->getMessage() . " - " . time(), null, 'cosmiccart-export.log');
            Mage::getModel('cosmiccart_integration/mail')->sendEmail(
                'cosmiccart_integration_import_exception_tpl',
                'api-fallback@cosmiccart.com',
                'Cosmic Cart API Fallback Team',
                'Catalog Import Exception (Fallback Email)',
                array(
                    'exception' => $e,
                    'pageSize' => $this->_getPageSize(),
                    'siteUrl' => Mage::getStoreConfig('web/secure/base_url', $store)
                )
            );
            throw $e;
        }

        // Work on simple last
        try {
            $numWritten = 0;
            if ($row['number_of_processed_row'] < $row['total_row_count']) {
                $numWritten = $this->exportCollection(
                    $store,
                    $currency,
                    $row['total_row_count'],
                    $simple_collection,
                    $row['number_of_processed_row'],
                    $row['batch_id'],
                    false
                );
            }
        } catch (Exception $e) {
            Mage::log(__FILE__ . ' ' . __LINE__ . ' ' . " Error: " . $e->getMessage() . " - " . time(), null, 'cosmiccart-export.log');
            Mage::helper('cosmiccart_integration')->log(__FILE__ . ' ' . __LINE__ . ' ' . " Error: " . $e->getMessage(), 1, 'exporter');
            Mage::getModel('cosmiccart_integration/mail')->sendEmail(
                'cosmiccart_integration_import_exception_tpl',
                'api-fallback@cosmiccart.com',
                'Cosmic Cart API Fallback Team',
                'Catalog Import Exception (Fallback Email)',
                array(
                    'exception' => $e,
                    'pageSize' => $this->_getPageSize(),
                    'siteUrl' => Mage::getStoreConfig('web/secure/base_url', $store)
                )
            );

            throw $e;
        }

        // Check to see if we had everything?
        $updateSql = sprintf("UPDATE %s SET current_stage = 3, current_stage = 3, comment = 'Gzip contents...'
                              WHERE number_of_processed_row >= total_row_count
                              AND config_total_row_count >= config_number_of_processed_row AND batch_id = %s",
            $this->_getTableName('cosmiccart_integration/batch'), $row['batch_id']);

        $this->_getWriteConnection()->exec($updateSql);

        $row['current_stage'] = 3;

        return $row;
    }

    private function _handleStage3($row)
    {
        Mage::helper('cosmiccart_integration')->log('Beginning stage 3', 4, 'exporter');
        // Merge the files
        // File path of final result
        $exportDir = $this->_getExportDir();
        $exportReadyFile = $exportDir . DS . $row['batch_id'] . '.gz';

        // Compress it with gzip
        $fp = gzopen($exportReadyFile, 'w9');
        gzwrite($fp, "[");

        // Files
        $files = glob($exportDir . DS . $row['batch_id'] . DS . "*.txt");

        $filecounter = 0;
        foreach ($files as $file) {
            gzwrite($fp, file_get_contents($file));
            /* after appending every file we need to append comma (,) */
            $filecounter++;
            if ($filecounter < count($files)) {
                gzwrite($fp, ",");
            }
            @unlink($file);
        }

        gzwrite($fp, "]");
        gzclose($fp);

        $data = array();
        $data['current_stage'] = 4;
        $data['comment'] = 'Uploading data...';
        $data['num_of_times_retried'] = 0;

        $where = $this->_getWriteConnection()->quoteInto('batch_id =?', $row['batch_id']);
        $this->_getWriteConnection()->update($this->_getTableName('cosmiccart_integration/batch'), $data, $where);

        $row['current_stage'] = 4;

        return $row;
    }

    private function _handleStage4($row, $client)
    {
        Mage::helper('cosmiccart_integration')->log('Beginning stage 4', 4, 'exporter');

        $sftpServer = Mage::helper('cosmiccart_integration')->getSftpUrl();

        // Upload FTP
        $connection = $this->open(array(
            'host' => $sftpServer,
            'username' => $this->generateUsername($client->getClientId()),
            'password' => $client->getClientSecret(),
            'timeout' => '7200'
        ), $row['batch_id']);

        // File path of final result
        $sftpTargetFile = 'Magento_Export_' . date('YmjHis') . '.gz';

        $exportDir = $this->_getExportDir();
        $exportReadyFile = $exportDir . DS . $row['batch_id'] . '.gz';

        Mage::helper('cosmiccart_integration')->log("Uploading $exportReadyFile", 4, 'exporter');

        $connection->put($sftpTargetFile, $exportReadyFile, NET_SFTP_LOCAL_FILE);
        $connection->disconnect();

        // Removed temporary folder
        @rmdir($exportDir . DS . $row['batch_id']);

        // Remove temp local file
        @unlink($exportReadyFile);

        $data = array();
        $data['current_stage'] = 5;
        $data['end_time'] = date("Y-m-d H:i:s");
        $data['num_of_times_retried'] = 0;
        $data['comment'] = 'Completed!';

        $where = $this->_getWriteConnection()->quoteInto('batch_id =?', $row['batch_id']);
        $this->_getWriteConnection()->update($this->_getTableName('cosmiccart_integration/batch'), $data, $where);

        $row['current_stage'] = 5;

        return $row;
    }

    /**
     * Get Configurables collection
     *
     * @param $store
     * @return mixed
     */
    private function _getConfigurablesCollection($store)
    {
        if (!isset($this->_configurablesCollection[$store]) OR $this->_configurablesCollection[$store] == null) {
            $this->_configurablesCollection[$store] = $this->_prepareProductCollection('configurable', $store);
        }

        return $this->_configurablesCollection[$store];
    }

    /**
     * Get Configurables collection without attribute joins
     *
     * @param $store
     * @return mixed
     */
    private function _getConfigurablesIdsCollection($store)
    {
        if (!isset($this->_configurablesIdsCollection[$store]) OR $this->_configurablesIdsCollection[$store] == null) {
            $this->_configurablesIdsCollection[$store] = $this->_prepareProductCollection('configurable_ids', $store);
        }

        return $this->_configurablesIdsCollection[$store];
    }

    /**
     * Get Simple products collection
     *
     * @param $store
     * @return mixed
     */
    private function _getSimplesCollection($store)
    {
        if (!isset($this->_simplesCollection[$store]) OR $this->_simplesCollection[$store] == null) {
            $this->_simplesCollection[$store] = $this->_prepareProductCollection('simple', $store);
        }

        return $this->_simplesCollection[$store];
    }

    /**
     * Get Folder for exported files
     *
     * @return string
     */
    private function _getExportDir()
    {
        return Mage::getBaseDir('tmp') . DS . 'cosmic_cart_export';
    }

    private function _getBatchRows()
    {
        $select = $this->_getReadConnection()
            ->select()
            ->from($this->_getTableName('cosmiccart_integration/batch'), array('*'))
            ->where('current_stage IN (0,1,2,3,4)')
            ->order('current_stage ASC');

        $rows = $this->_getReadConnection()->fetchAll($select);
        return $rows;
    }


    private function _getPageSize()
    {
        if (!$this->_pageSize) {
            $this->_pageSize = Mage::getStoreConfig('cosmiccart/export_opt/max_batch_size');
            if (empty($this->_pageSize)) {
                $this->_pageSize = 3000;
            }
        }
        return $this->_pageSize;
    }


    /**
     * Serializes a page of products to file.
     */
    private function exportCollection($store, $currency, $collectionCounter, $collection, $number_of_processed_row, $batchId, $isConfigurable)
    {
        Mage::helper('cosmiccart_integration')->log('Beginning exportCollection', 4, 'exporter');

        $stopNow = false;

        $this->_exportStoreId = $store;

        // Prepare the temp export path
        $exportDir = $this->_getExportDir() . DS . $batchId;
        // Set up our output file stream
        $out = new Varien_Io_File();
        $out->setAllowCreateFolders(true);
        $out->open(array('path' => $exportDir));

        do {
            Mage::log(__FILE__ . ' ' . __LINE__ . ' ' . "Batch Started: " . $number_of_processed_row . " - " . time(), null, 'cosmiccart-export.log');
            Mage::helper('cosmiccart_integration')->log("Batch Started: $number_of_processed_row", 4, 'exporter');

            if (Mage::helper('cosmiccart_integration')->checkBatch($batchId) != $batchId) {
                throw new Exception('User cancelled');
                break;
            }

            $product_collection = clone($collection);
            $product_collection->getSelect()->order('e.entity_id ASC')->limit($this->_getPageSize(), $number_of_processed_row);

            if ($product_collection == null) {
                $stopNow = true;
                break;
            } else {
                // Start working on the export
                /*
				 * export file name for configurable products should be different because it overriding values with simple product file name
				 * like 0.txt - configurable product file name & same 0.txt - simple product file name
				 * so first in file configurable products will list but at the time of simple products export it will override previous values
				 * */
                if ($isConfigurable) {
                    $export_file_name = $exportDir . DS . "C-" . $number_of_processed_row . '.txt';
                } else {
                    $export_file_name = $exportDir . DS . $number_of_processed_row . '.txt';
                }

                file_put_contents($export_file_name, '');
                $this->_processedCount = 0;

                $mediaUrl = Mage::getSingleton('catalog/product_media_config')->getBaseMediaUrl();

                $customColumns = array(
                    'store_id' => new Zend_Db_Expr($this->_getReadConnection()->quoteInto('?', $store)),
                    'currency_code' => new Zend_Db_Expr($this->_getReadConnection()->quoteInto('?', $currency)),
                    'img_url' => sprintf("CONCAT('%s', IF(at_image.value_id > 0, at_image.value, at_image_default.value))", $mediaUrl),
                );

                if ($isConfigurable) {
                    $customColumns['raw_item_type'] = new Zend_Db_Expr($this->_getReadConnection()->quoteInto('?', 'PRODUCT'));
                } else {
                    $customColumns['raw_item_type'] = new Zend_Db_Expr($this->_getReadConnection()->quoteInto('?', 'STANDALONE'));
                }

                $product_collection->getSelect()->columns($customColumns);

                $this->_exportData = array();

                $product_collection->walk(array($this, 'exportCollectionCallback'));

                if ($isConfigurable) {
                    //Prepare configurable child collection
                    $childCollection = $this->_getChildrenProductsCollection($store);

                    $customColumns['raw_item_type'] = new Zend_Db_Expr($this->_getReadConnection()->quoteInto('?', 'VARIANT'));
                    $childCollection->getSelect()->columns($customColumns);
                    $childCollection->walk(array($this, 'exportCollectionCallback'));
                }

                file_put_contents($export_file_name, implode(',', $this->_exportData), FILE_APPEND);

                // Update the batch information
                Mage::log(__FILE__ . ' ' . __LINE__ . ' ' . "Batch Ended: " . $number_of_processed_row . " - " . time(), null, 'cosmiccart-export.log');
                if ($isConfigurable) {
                    $sql = 'UPDATE cosmiccart_batch_status SET config_number_of_processed_row = config_number_of_processed_row + ' . $this->_processedCount . ' WHERE batch_id = ' . $batchId;
                    $this->_getWriteConnection()->query($sql);
                    $number_of_processed_row = $this->_getReadConnection()->fetchOne('SELECT config_number_of_processed_row FROM cosmiccart_batch_status WHERE batch_id = ' . $batchId);
                } else {
                    $sql = 'UPDATE cosmiccart_batch_status SET number_of_processed_row = number_of_processed_row + ' . $this->_processedCount . ' WHERE batch_id = ' . $batchId;
                    $this->_getWriteConnection()->query($sql);
                    $number_of_processed_row = $this->_getReadConnection()->fetchOne('SELECT number_of_processed_row FROM cosmiccart_batch_status WHERE batch_id = ' . $batchId);
                }
            }

            if ($number_of_processed_row >= $collectionCounter) {
                $stopNow = true;
            }

        } while (!$stopNow);

        return $number_of_processed_row;
    }

    private function _getConfigurableAttributes($parentSku, $child)
    {
        $data = array();

        $parentId = Mage::getModel('catalog/product')->getResource()->getIdBySku($parentSku);
        $parent = Mage::getModel('catalog/product')->load($parentId);
        if ($parent AND $parent->getId()) {
            $configurableAttributes = $parent->getTypeInstance(true)->getConfigurableAttributesAsArray($parent);

            foreach ($configurableAttributes as $attribute) {
                $label = $attribute['frontend_label'];
                $attributeCode = $attribute['attribute_code'];

                $attr = Mage::getResourceModel('catalog/product')->getAttributeRawValue($child->getId(), $attributeCode, $this->_exportStoreId);

                if ($attr) {
                    $child->setData($attributeCode, $attr);
                    $value = $child->getAttributeText($attributeCode);
                    $optionPricing = null;
                    foreach ($attribute['values'] as $option) {
	                    if ($option['label'] == $value) {
		                    $optionPricing = array(
			                    'is_percent' => $option['is_percent'],
			                    'pricing_value' => $option['pricing_value']
		                    );
	                    }
                    }
                    $data[] = array(
                        'attribute' => $label,
                        'value' => $value,
                        'optionPricing' => $optionPricing,
                        'parentPricing' => array(
	                        'price' => $parent->getPrice(),
	                        'specialPrice' => $parent->getSpecialPrice()
                        )
                    );
                }
            }

            return $data;
        }
    }

    public function exportCollectionCallback($product)
    {
        Mage::helper('cosmiccart_integration')->log('Exporting ' . $product->getRawItemType() . ' sku ' . $product->getSku(), 4, 'exporter');

        $helper = Mage::helper('core');
        $storeId = $this->_exportStoreId;

        $pricing = $this->_getSimplePrice($product);
        $price = $pricing['price'];
        $specialPrice = $pricing['specialPrice'];

        $brandType = $this->_getBrandAttributeType();
        if (is_null($brandType)) {
            $brandType = 'text';
        }

        if ($brandType == 'select') {
            $brandCode = $this->_getBrandAttributeCode($this->getStore());
            $product->setStoreId($this->getStore())
                ->setData(
                    $brandCode,
                    $product->getProductBrand()
                );
            $brand = $product->getAttributeText($brandCode);

        } elseif ($brandType == 'multiselect') {

            $productBrand = null;
            $brand = $product->getProductBrand();
            if ($brand) {
                $brands = explode(',', $brand);
                if ($brands AND is_array($brands) AND count($brands)) {
                    $productBrand = $brands[0];
                }
            }

            $brandCode = $this->_getBrandAttributeCode($this->getStore());
            $product->setStoreId($this->getStore())
                ->setData(
                    $brandCode,
                    $productBrand
                );
            $brand = $product->getAttributeText($brandCode);
        } else {
            $brand = $product->getProductBrand();
        }

        $brand = $brand ? $brand : null;

        $prodResult = array(
            'product_id' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => $price,
            'special_price' => $specialPrice,
            'special_from_date' => $product->getSpecialFromDate(),
            'special_to_date' => $product->getSpecialToDate(),
            'currency_code' => $product->getCurrencyCode(),
            'parent_sku' => null,
            'store_id' => $storeId,
            'description' => $product->getDescription(),
            'raw_item_type' => $product->getRawItemType(),
            'categories' => $product->getCategoryIds(),
            'available' => $product->isSalable(),
            'brand' => $brand,
            'ean' => $product->getProductEan(),
            'images' => array(
                array(
                    'url' => $product->getImgUrl(),
                    'position' => "0"
                )
            ),
            'attributes' => null
        );

        if ($product->getRawItemType() == 'VARIANT') {

            $parentIds = $this->_getConfigurableTypeInstance()->getParentIdsByChild($product->getId());
            $query = sprintf("SELECT sku FROM %s WHERE entity_id IN('%s')", $this->_getTableName('catalog/product'), implode("','", $parentIds));
            $result = $this->_getReadConnection()->fetchCol($query);

            foreach ($result as $parentSku) {

                $result = $prodResult;
                $result['parent_sku'] = $parentSku;

                $attributes = $this->_getConfigurableAttributes($parentSku, $product);
                $result['attributes'] = $attributes;

                $pricing = $this->_getVariantPrice($product, $attributes);
                $result['price'] = $pricing['price'];
                $result['special_price'] = $pricing['price'];

                //Handle product export here for each product
                $this->_exportData[] = $helper->jsonEncode($result);
            }

            return $this;
        }

        //Handle product export here for regular products

        //Processed count increment only for parent products!!!
        $this->_processedCount++;
        $this->_exportData[] = $helper->jsonEncode($prodResult);

        return $this;
    }

    /**
     * Returns an array with the variant pricing depending on what pricing mode is set
     *
     * @param  $product
     * @param  $attributes
     * @return array $pricing
    */
    private function _getVariantPrice($product, $attributes)
    {
        $helper = Mage::helper('cosmiccart_integration');

        if ($helper->getConfigurableProductPricingMode() == $helper->getConfigurableProductPricingOption()) {
            return $this->_getConfigurableProductPricing($product, $attributes);
        }

        return $this->_getSimplePrice($product);
    }

    /**
     * Returns an array with the Configurable Plus Option Pricing variant pricing
     *
     * @param  $product
     * @param  $attributes
     * @return array $pricing
    */
    private function _getConfigurableProductPricing($product, $attributes)
    {
        $price = $attributes[0]['parentPricing']['price'];
        $specialPrice = $attributes[0]['parentPricing']['specialPrice'];

        foreach ($attributes as $attribute) {
            if ($attribute['optionPricing']['is_percent'] == 1) {
                $price += ($price * $attribute['optionPricing']['pricing_value']);
                $specialPrice += ($specialPrice * $attribute['optionPricing']['pricing_value']);
            } else {
                $price += $attribute['optionPricing']['pricing_value'];
                $specialPrice += $attribute['optionPricing']['pricing_value'];
            }
        }

        return array(
          'price' => $this->_formatPrice($price),
          'specialPrice' => $this->_formatPrice($specialPrice)
        );
    }

    /**
     * Returns an array with the regular price and special price
     *
     * @param  $product
     * @return array $pricing
    */
    private function _getSimplePrice($product)
    {
        $price = $product->getPrice();
        $specialPrice = $product->getSpecialPrice();

        return array(
          'price' => $this->_formatPrice($price),
          'specialPrice' => $this->_formatPrice($specialPrice)
        );
    }

    /**
     * Returns a number value with two decimals and no thousands separator
     *
     * @param  $price
     * @return $formatted_price
    */
    private function _formatPrice($price) {
        return $price ? number_format($price, 2, ".", "") : null;
    }

    private function _getConfigurableTypeInstance()
    {
        if (!$this->_configurableTypeInstance) {
            $this->_configurableTypeInstance = Mage::getSingleton('catalog/product_type_configurable');
        }

        return $this->_configurableTypeInstance;
    }

    /**
     * Opens an SFTP connection. This was pulled out of Varien_Io_Sftp because that class had a bug in how it called
     * Net_SFTP::put(), ƒorcing the expectation of a string of data instead of a file name.
     *
     * @param array $args
     * @return Net_SFTP|null
     * @throws Exception
     */
    private function open(array $args = array(), $batchId)
    {
        $connection = null;
        if (!isset($args['timeout'])) {
            $args['timeout'] = Varien_Io_Sftp::REMOTE_TIMEOUT;
        }
        if (strpos($args['host'], ':') !== false) {
            list($host, $port) = explode(':', $args['host'], 2);
        } else {
            $host = $args['host'];
            $port = Varien_Io_Sftp::SSH2_PORT;
        }
        $connection = new Net_SFTP($host, $port, $args['timeout']);
        if (!$connection->login($args['username'], $args['password'])) {
            // If ftp is not connecting then remove temporary folder
            $baseDir = Mage::getBaseDir();
            @rmdir($baseDir . DS . 'var' . DS . 'cosmic_cart_export' . DS . $batchId);
            throw new Exception(sprintf(__("Unable to open SFTP connection as %s@%s", $args['username'], $args['host'])));
        }
        return $connection;
    }

    /**
     * Turns the given clientId into a username for SFTP. Strips hyphens and truncates to 12 chars.
     *
     * @param $clientId
     * @return String
     */
    private function generateUsername($clientId)
    {
        return 'mag' . substr(str_replace('-', '', $clientId), 0, 12);
    }

    private function initErrorHandler($store)
    {
        Mage::getSingleton('cosmiccart_integration/errorHandler')->initErrorHandler($store);
    }

    private function _getResourceSingleton()
    {
        return Mage::getSingleton('core/resource');
    }

    private function _getTableName($name)
    {
        return $this->_getResourceSingleton()->getTableName($name);
    }

    private function _getConnection($type)
    {
        return $this->_getResourceSingleton()->getConnection($type);
    }

    private function _getReadConnection()
    {
        return $this->_getConnection('core_read');
    }

    private function _getWriteConnection()
    {
        return $this->_getConnection('core_write');
    }

    private function _prepareProductCollection($type, $store)
    {
        $isVisible = array('in' => Mage::getSingleton('catalog/product_visibility')->getVisibleInSiteIds());
        Mage::app()->setCurrentStore($store);
        $collection = Mage::getModel('catalog/product')
            ->getCollection()->addStoreFilter($store);

        switch ($type) {
            case 'configurable': {
                $collection
                    ->addAttributeToFilter('type_id', 'configurable')
                    ->addAttributeToFilter('status', 1)
                    ->addAttributeToFilter('visibility', $isVisible);
            }
                break;
            case 'configurable_ids': {
                $collection
                    ->addAttributeToFilter('type_id', 'configurable')
                    ->addAttributeToFilter('status', 1)
                    ->addAttributeToFilter('visibility', $isVisible);
                $collection->getSelect()->reset(Zend_Db_Select::COLUMNS);
                $collection->getSelect()->columns('entity_id');
                //We don't want here to join other fields
                return $collection;
            }
                break;
            case 'simple': {
                $collection
                    ->addAttributeToFilter('type_id', 'simple')
                    ->addAttributeToFilter('status', 1)
                    ->addAttributeToFilter('visibility', $isVisible)
                    ->joinTable('catalog/product_relation', 'child_id=entity_id', array(
                        'parent_id' => 'parent_id'
                    ), null, 'left')
                    ->addAttributeToFilter(array(
                        array(
                            'attribute' => 'parent_id',
                            'null' => null
                        )
                    ));
            }
                break;
        }

        $collection = $this->_joinCollectionAttributes($collection, $store);
        return $collection;
    }

    private function _joinCollectionAttributes($collection, $store)
    {
        $collection->joinAttribute(
            'name',
            'catalog_product/name',
            'entity_id',
            null,
            'left',
            $store
        );

        $collection->joinAttribute(
            'price',
            'catalog_product/price',
            'entity_id',
            null,
            'left',
            $store
        );

        $collection->joinAttribute(
            'special_price',
            'catalog_product/special_price',
            'entity_id',
            null,
            'left',
            $store
        );

        $collection->joinAttribute(
            'special_from_date',
            'catalog_product/special_from_date',
            'entity_id',
            null,
            'left',
            $store
        );

        $collection->joinAttribute(
            'special_to_date',
            'catalog_product/special_to_date',
            'entity_id',
            null,
            'left',
            $store
        );

        $collection->joinAttribute(
            'image',
            'catalog_product/image',
            'entity_id',
            null,
            'left',
            $store
        );

        $collection->joinAttribute(
            'description',
            'catalog_product/description',
            'entity_id',
            null,
            'left',
            $store
        );

        if ($code = $this->_getBrandAttributeCode($store)) {
            $collection->joinAttribute(
                'product_brand',
                sprintf('catalog_product/%s', $code),
                'entity_id',
                null,
                'left',
                $store
            );
        }

        if ($code = $this->_getEanAttributeCode($store)) {
            $collection->joinAttribute(
                'product_ean',
                sprintf('catalog_product/%s', $code),
                'entity_id',
                null,
                'left',
                $store
            );
        }

        return $collection;
    }

    private function _getChildrenProductsCollection($store)
    {
        $parentIdsSelect = 0;

        $configurableIdsCollection = $this->_getConfigurablesIdsCollection($store);
        if ($configurableIdsCollection) {
            $parentIdsSelect = $configurableIdsCollection->getSelect();
        }

        $childrenIdsSelect = sprintf("(SELECT DISTINCT main_table.product_id FROM %s as main_table
                                      WHERE main_table.parent_id IN(%s))",
            $this->_getTableName('catalog/product_super_link'),
            $parentIdsSelect
        );

        $childCollection = Mage::getModel('catalog/product')->getCollection();
        $childCollection = $this->_joinCollectionAttributes($childCollection, $store);

        $childCollection->getSelect()->joinInner(array('tbl1' => new Zend_Db_Expr($childrenIdsSelect)), 'tbl1.product_id = e.entity_id', array(), null);
        $childCollection->addAttributeToFilter('status', 1);

        return $childCollection;
    }

    private function _getBrandAttributeCode($store = null)
    {
        if (is_null($this->_brandAttributeCode)) {
            $this->_brandAttributeCode = Mage::getStoreConfig('cosmiccart/export_opt/brand_attr_code', $store);
        }
        return $this->_brandAttributeCode;
    }

    private function _getEanAttributeCode($store = null)
    {
        if (is_null($this->_eanAttributeCode)) {
            $this->_eanAttributeCode = Mage::getStoreConfig('cosmiccart/export_opt/ean_attr_code', $store);
        }
        return $this->_eanAttributeCode;
    }

    private function _getBrandAttributeType()
    {
        if (!$this->_brandAttributeType) {
            $code = $this->_getBrandAttributeCode($this->getStore());
            $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', $code);
            if ($attribute) {
                $this->_brandAttributeType = $attribute->getFrontendInput();
            }
        }

        return $this->_brandAttributeType;
    }
}
