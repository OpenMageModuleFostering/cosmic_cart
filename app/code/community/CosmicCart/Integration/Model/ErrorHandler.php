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

class CosmicCart_Integration_Model_ErrorHandler
{

    /**
     * Creates a shutdown handler that will mail any errors to us.
     *
     * @param $store The store id
     */
    public function initErrorHandler($store)
    {
        error_reporting(E_ALL);

        if (!function_exists('handleErrors')) {
            function handleErrors($store)
            {
                $isError = false;
                if ($error = error_get_last()) {
                    switch ($error['type']) {
                        case E_ERROR:
                        case E_CORE_ERROR:
                        case E_COMPILE_ERROR:
                        case E_USER_ERROR:
                            $isError = true;
                            break;
                    }
                }
                if ($isError) {
                    Mage::getModel('cosmiccart_integration/mail')->sendEmail(
                        'cosmiccart_integration_generic_error_tpl',
                        'api-fallback@cosmiccart.com',
                        'Cosmic Cart API Fallback Team',
                        'Generic Error (Fallback Email)',
                        array(
                            'message' => $error['message'],
                            'file' => $error['file'],
                            'line' => $error['line'],
                            'siteUrl' => Mage::getStoreConfig('web/secure/base_url', $store)
                        )
                    );
                }
            }

            register_shutdown_function('handleErrors', $store);
        }
    }

}