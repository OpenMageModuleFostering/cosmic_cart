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


class CosmicCart_Integration_Model_Oauth2client extends Varien_Object
{
    public $baseApiUrl = '';
    public $client_id = '';
    public $client_secret = '';
    public $grant_type = 'password';
    public $accessTokenUri = 'oauth/token';

    public function init($client_id = null, $client_secret = null)
    {
        $this->baseApiUrl = Mage::helper('cosmiccart_integration')->getApiUrl();
        if (empty($client_id)) {
            $client = $this->loadClient();
            $client_id = $client->getClientId();
            $client_secret = $client->getClientSecret();
        }
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;

        return $this;
    }

    public function getAccessToken($username, $password)
    {
        $params = array(
            'username' => $username,
            'password' => $password,
            'grant_type' => $this->grant_type
        );
        $accessTokenResponse = $this->get($this->accessTokenUri, $params, $this->createAuthHeader());
        return $this->storeAccessTokenFromResponse($accessTokenResponse);
    }

    public function get($api, $params, $header = null)
    {
		$url = $this->getApiUrl($api, $params);
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => array($header)
        ));
        $response = curl_exec($ch);
		curl_close($ch);
        return json_decode($response);
    }

    protected function getApiUrl($api, $params = null)
    {
        if (strncmp($api, '/', 1)) {
            $api = '/' . $api;
        }
        $api = $this->baseApiUrl . $api;
        if (!empty($params)) {
            $api .= '?' . http_build_query($params);
        }
        return $api;
    }

    protected function createAuthHeader()
    {
        return 'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret);
    }

    private function storeAccessTokenFromResponse($accessTokenResponse)
    {
        if (empty($accessTokenResponse)) {
            throw new Exception('Could not connect to Cosmic Cart.');
        }
        if (!empty($accessTokenResponse->error)) {
            throw new Exception($accessTokenResponse->error_description);
        }

        /* Let's remove our existing token. Should be only one at any given time. */
        Mage::getModel('cosmiccart_integration/accessToken')->deleteExisting();

        $accessToken = Mage::getModel('cosmiccart_integration/accessToken');
        $accessToken->setAccessToken($accessTokenResponse->access_token);
        $accessToken->setRefreshToken($accessTokenResponse->refresh_token);
        $accessToken->setTokenType($accessTokenResponse->token_type);
        $accessToken->setScope($accessTokenResponse->scope);
        $expires_in = $accessTokenResponse->expires_in;
        $now = time();
        $expires = $now + $expires_in;
        $accessToken->setExpires($expires);

        $accessToken->save();

        return $accessToken;
    }

    public function shipAndSettle($package)
    {
        return $this->post('subOrder/package', $package);
    }

    public function refund($subOrderId, $refund) {
        return $this->post('sellers/orders/'.$subOrderId.'/refunds', $refund);
    }

    private function post($api, $params, $accessToken = null)
    {
        $response = null;
        if (empty($accessToken)) {
            $accessToken = $this->loadAccessToken();
        }
        if (!empty($accessToken)) {
            $ch = curl_init($this->getApiUrl($api));
            $params = json_encode($params);
            $headers = array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($params)
            );
            $headers[] = $this->createAccessTokenHeader($accessToken);
            curl_setopt_array($ch, array(
                CURLOPT_POST => 1,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_POSTFIELDS => $params,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers
            ));
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (empty($response)) {
                throw new Exception('Could not communicate with Cosmic Cart.');
            } else {
                $response = json_decode($response);
                if ($statusCode >= 400 && $statusCode <= 600) {
                    if (!empty($response->error)) {
                        throw new Exception($response->error_description);
                    } else if (!empty($response->message)) {
                        throw new Exception($response->message);
                    } else {
                        throw new Exception('Could not reach Cosmic Cart API '.$api.' '.$statusCode);
                    }
                }
            }
        } else {
            throw new Exception("Unable to load Cosmic Cart API access token.");
        }
        return $response;
    }

    public function saveClient() {
        // First delete any old ones.
        Mage::getModel('cosmiccart_integration/client')->deleteExisting();
        $client = Mage::getModel('cosmiccart_integration/client');
        $client->setClientId($this->client_id);
        $client->setClientSecret($this->client_secret);
        $client->save();
    }

    private function loadClient() {
        return Mage::getModel('cosmiccart_integration/client')->findExisting();
    }

    private function loadAccessToken()
    {
        $accessToken = null;
        $accessTokens = Mage::getModel('cosmiccart_integration/accessToken')->getCollection();
        foreach ($accessTokens as $token) {
            $accessToken = $token;
            break;
        }
        if (!empty($accessToken)) {
            $now = time();
            $expires = $accessToken->getExpires();
            if ($now >= $expires) {
                /* Our access token has expired. Refresh it! */
                $params = array(
                    'refresh_token' => $accessToken->getRefreshToken(),
                    'grant_type' => 'refresh_token'
                );
                $accessTokenResponse = $this->get($this->accessTokenUri, $params, $this->createAuthHeader());
                $accessToken = $this->storeAccessTokenFromResponse($accessTokenResponse);
            }
        }
        if (empty($accessToken)) {
            throw new Exception("No access token found. Has the module been activated?");
        }
        return $accessToken;
    }

    protected function createAccessTokenHeader($accessToken)
    {
        $header = 'Authorization: Bearer ' . $accessToken->getAccessToken();
        return $header;
    }

    public function registerStores($stores, $accessToken)
    {
        return $this->post('seller/store', $stores, $accessToken);
    }

    public function debugJson($data)
    {
        echo "<pre>";
        print_r($data);
        echo "</pre>";

        # Redirect where ever you need **************************************************************
        #$c_session = $this->provider."_profile";
        #$_SESSION[$this->provider] = "true";
        #$_SESSION[$c_session] = $data;

        #echo("<script> top.location.href='index.php#".$this->provider."'</script>");

    }
}
