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

class CosmicCart_Integration_Adminhtml_Integration_ActivationController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('cosmiccart');
        $block = $this->getLayout()->getBlock('activation');
        $block->setData('activated', Mage::getModel('cosmiccart_integration/accessToken')->exists());
        $this->renderLayout();
    }

    public function postAction()
    {
        if (Mage::getStoreConfig('cosmiccart/options/debug') == 1) {
            $message = $this->__('debug.prefix') . $this->__('activation.success');
            Mage::getSingleton('adminhtml/session')->addSuccess($message);
        } else {
            $post = $this->getRequest()->getPost();

            try {
                if (empty($post)) {
                    Mage::throwException($this->__('Invalid form data.'));
                }

                /* Configure our client. */
                $client = Mage::getModel('cosmiccart_integration/oauth2client')->init($post['clientId'], $post['clientSecret']);

                /* Request an access token */
                $accessToken = $client->getAccessToken($post['username'], $post['password']);
                /*
                 * Register our stores with Cosmic Cart.
                 * For now, let's just return the default store. One store per Seller. Later we will expand this to include
                 * all possible stores, but the Cosmic Cart side will need to be modified to support it.
                 */
                $storeId = $post['store'];
                $stores = array();
                $store = Mage::getModel('core/store')->load($storeId);
                $stores[] = array(
                    'remoteId' => $store->getId(),
                    'locale' => $store->getConfig('general/locale/code'),
                    'active' => ($store->getIsActive() == 1),
                    'name' => $store->getGroup()->getName(),
                    'url' => Mage::getStoreConfig('web/secure/base_url', $store->getId())
                );
                $registerStoresResponse = $client->registerStores($stores, $accessToken);
                if (empty($registerStoresResponse)) {
                    throw new Exception('Could not connect to Cosmic Cart to register store(s).');
                }
                $apiUsername = $registerStoresResponse->apiUsername;
                $apiKey = $registerStoresResponse->apiKey;

                /* Find or create a CosmicCartIntegration API Role and User */
                $role = Mage::getModel('api/roles')->getCollection()
                    ->addFieldToFilter('role_name', 'CosmicCartIntegration')
                    ->addFieldToFilter('role_type', 'G')
                    ->getFirstItem();
                if (!$role->getId()) {
                    /* Create our API Role */
                    $role = Mage::getModel('api/roles')
                        ->setName('CosmicCartIntegration')
                        ->setPid(false)
                        ->setRoleType('G')
                        ->save();
                    /* Add permission to our API Role. */
                    Mage::getModel('api/rules')
                        ->setRoleId($role->getId())
                        ->setResources(array('all'))
                        ->saveRel();
                }
                $user = Mage::getModel('api/user')->getCollection()
                    ->addFieldToFilter('email', 'integration@cosmiccart.com')
                    ->getFirstItem();
                if ($user->getId()) {
                    /* Remove the old user. */
                    $user->delete();
                }
                /* Create our API User. */
                $user = Mage::getModel('api/user')
                    ->setData(array(
                        'username' => $apiUsername,
                        'firstname' => 'Cosmic',
                        'lastname' => 'Cart',
                        'email' => 'integration@cosmiccart.com',
                        'api_key' => $apiKey,
                        'api_key_confirmation' => $apiKey,
                        'is_active' => 1,
                        'user_roles' => '',
                        'assigned_user_role' => '',
                        'role_name' => '',
                        'roles' => array($role->getId())
                    ));
                $user->save()->load($user->getId());
                /* Assign our API Role to our API User. */
                $user->setRoleIds(array($role->getId()))
                    ->setRoleUserId($user->getId())
                    ->saveRelations();

                $client->saveClient();

                Mage::getModel('core/config')->saveConfig('cosmiccart/store', $storeId);

                $message = $this->__('activation.success') . '<ul>';
                $registeredStores = $registerStoresResponse->stores;
                foreach ($registeredStores as $registeredStore) {
                    $message .= '<li>"'.$registeredStore->name.'"</li>';
                }
                $message .= '</ul>';

                Mage::getSingleton('adminhtml/session')->addSuccess($message);
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*');
    }

}
