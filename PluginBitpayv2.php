<?php

require_once 'modules/billing/models/class.gateway.plugin.php';

class PluginBitpayv2 extends GatewayPlugin
{
    public function getVariables()
    {
        $variables = array (
            lang("Plugin Name") => array (
                "type"          => "hidden",
                "description"   => lang("How CE sees this plugin (not to be confused with the Signup Name)"),
                "value"         => lang("BitPayv2")
            ),
            lang("API Key") => array (
                "type"          => "text",
                "description"   => lang("Enter your API Key from your bitpay.com merchant account"),
                "value"         => ""
            ),
            lang("Transaction Speed") => array (
                "type"          => "options",
                "description"   => lang("Select the transaction speed to confirm payment"),
                "options"       => [
                    'low'    => lang('Low'),
                    'medium' => lang('Medium'),
                    'high'   => lang('High')
                ]
            ),
            lang("Use Testing Environment?") => array(
                "type"          => "yesno",
                "description"   => lang("Select YES if you wish to use the testing environment instead of the live environment"),
                "value"         => "0"
            ),
            lang("Signup Name") => array (
                "type"          => "text",
                "description"   => lang("Select the name to display in the signup process for this payment type. Example: eCheck or Credit Card."),
                "value"         => "Bitcoin (BTC)"
            )

        );
        return $variables;
    }

    public function credit($params)
    {
    }

    public function singlepayment($params, $test = false)
    {
        $data = [];
        $data['price'] = $params['invoiceTotal'];
        $data['currency'] = $params['userCurrency'];
        $data['orderId'] = $params['invoiceNumber'];
        $data['itemDesc'] = $params['invoiceDescription'];
        $data['notificationURL'] = $params['clientExecURL'] . '/plugins/gateways/bitpayv2/callback.php';
        $data['redirectURL'] = $params['invoiceviewURLSuccess'];
        $data['transactionSpeed'] = $params['plugin_bitpayv2_Transaction Speed'];
        $data['fullNotifications'] = true;
        $data['buyer']['name'] = $params['userFirstName'] . ' ' . $params['userLastName'];
        $data['buyer']['address1'] = $params['userAddress'];
        $data['buyer']['locality'] = $params['userCity'];
        $data['buyer']['region'] = $params['userState'];
        $data['buyer']['postalCode'] = $params['userZipcode'];
        $data['buyer']['email'] = $params['userEmail'];
        $data['buyer']['phone'] = $params['userPhone'];
        $data['posData'] = $params['invoiceNumber'];
        $data['token'] = $params['plugin_bitpayv2_API Key'];

        CE_Lib::log(4, 'BitPayv2 Params: ' . print_r($data, true));
        $data = json_encode($data);
        $return = $this->makeRequest($params, $data, true);

        if (isset($return['error'])) {
            $cPlugin = new Plugin($params['invoiceNumber'], "bitpayv2", $this->user);
            $cPlugin->setAmount($params['invoiceTotal']);
            $cPlugin->setAction('charge');
            $cPlugin->PaymentRejected($this->user->lang("There was an error performing this operation.") . ' ' . $return['error']['message']);
            return $this->user->lang("There was an error performing this operation.") . ' ' . $return['error']['message'];
        }
        header('Location: ' . $return['data']['url']);
        exit;
    }

    private function makeRequest($params, $data, $post = false)
    {
        $url = 'https://bitpay.com/invoices/';
        if ($params['plugin_bitpayv2_Use Testing Environment?'] == '1') {
            $url = 'https://test.bitpay.com/invoices/';
        }

        CE_Lib::log(4, 'Making request to: ' . $url);
        $ch = curl_init($url);
        if ($post === true) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $header = [
            'Content-Type: application/json',
            'X-Accept-Version: 2.0.0',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        if (!$response) {
            throw new CE_Exception('cURL BitPayv2 Error: ' . curl_error($ch) . '( ' .curl_errno($ch) . ')');
        }
        curl_close($ch);
        $response = json_decode($response, true);
        CE_Lib::log(4, 'BitPayv2 Response: ' . print_r($response, true));

        return $response;
    }
}