<?php

use Tygh\Http;
use Tygh\Registry;
use Tygh\Session;

require_once (dirname(__FILE__) . '/vendor/webtopay/libwebtopay/WebToPay.php');

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

if (defined('PAYMENT_NOTIFICATION')) {
    if ($mode == 'success') {
        if (fn_check_payment_script('paysera.php', $_REQUEST['order_id'])) {
            $order_info = fn_get_order_info($_REQUEST['order_id'], true);
            if ($order_info['status'] == 'N') {
                fn_change_order_status($_REQUEST['order_id'], 'O', '', false);
            }
        }
        fn_order_placement_routines('route', $_REQUEST['order_id']);
        exit;
    } elseif ($mode == 'notify') {
        $order_info = fn_get_order_info($_REQUEST['order_id']);
        if (empty($processor_data)) {
            $processor_data = fn_get_processor_data($order_info['payment_id']);
        }
        if (empty($order_info)) {
            throw new Exception(sprintf("Missing order by specified id (order_id=%s)", $response['order_id']));
        }

        try {
            $response = WebToPay::checkResponse($_REQUEST, array(
                'projectid' => $processor_data['processor_params']['project_id'],
                'sign_password' => $processor_data['processor_params']['sign'],

            ));

            if ($response['status'] == 1) {
                if ($response['currency'] != $order_info['secondary_currency']) {
                    throw new Exception('The currency does not match.');
                }

                if ($response['amount'] < intval(number_format($order_info['total'], 2, '', ''))) {
                    throw new Exception('The amounts do not match.');
                }
                $response = $response + array(
                        'order_status' => 'O'
                    );

                if ($response['order_status'] == 'O') {
                    $response['order_status'] = 'P';
                    fn_paysera_payment_end($response['orderid'], $response);
                }
                else {
                    fn_change_order_status($response['orderid'], 'P');
                }
            }

            exit("OK");
        }
        catch(Exception $e) {
            exit(sprintf("ERROR: %s", $e->getMessage()));
        }
    }
    elseif ($mode == 'failed') {
        fn_order_placement_routines('route', $_REQUEST['order_id']);
        exit;
    }
    exit;

} else {

    $pid = $order_info['payment_method']['processor_params']['project_id'];
    $psign = $order_info['payment_method']['processor_params']['sign'];
    $test = ($order_info['payment_method']['processor_params']['mode'] == 'T') ? true : false;
    $pcurr = $order_info['payment_method']['processor_params']['currency'];

    $_order_id = ($order_info['repaid']) ? ($order_id . '_' . $order_info['repaid']) : $order_id;

    $order_info['b_country'] = strtolower($order_info['b_country']);

    $language = CART_LANGUAGE;
    $currency = CART_SECONDARY_CURRENCY;

    $currency_coefficient = Registry::get('currencies.' . CART_SECONDARY_CURRENCY . '.coefficient');
    $_order_total = !empty($currency_coefficient) ? $order_info['total'] / floatval($currency_coefficient) : $order_info['total'];

    try {
        $payment_info = array(
            'projectid' => $pid,
            'sign_password' => $psign,
            'orderid' => $_order_id,
            'lang' => ($language === 'LT') ? 'LIT' : 'ENG',
            'amount' => intval(number_format($_order_total, 2, '', '')),
            'currency' => $currency,
            'accepturl' => fn_url("payment_notification.success?payment=paysera_payment_processor&order_id=$order_id", AREA, 'current') ,
            'cancelurl' => fn_url("payment_notification.failed?payment=paysera_payment_processor&order_id=$order_id", AREA, 'current') ,
            'callbackurl' => fn_url("payment_notification.notify?payment=paysera_payment_processor&order_id=$order_id", AREA, 'current') ,
            'payment' => '',
            'country' => $order_info['b_country'],
            'logo' => '',

            'p_firstname' => $order_info['b_firstname'],
            'p_lastname' => $order_info['b_lastname'],
            'p_email' => $order_info['email'],
            'p_street' => $order_info['b_address'],
            'p_city' => $order_info['b_city'],
            'p_state' => $order_info['b_state'],
            'p_zip' => $order_info['b_zipcode'],
            'p_countrycode' => $order_info['b_country'],

            'test' => $test,
        );

        WebToPay::redirectToPayment($payment_info);

    }
    catch(WebToPayException $e) {
        exit(get_class($e) . ': ' . $e->getMessage());
    }
    die;

    fn_start_payment($_order_id, false, $payment_info);
}