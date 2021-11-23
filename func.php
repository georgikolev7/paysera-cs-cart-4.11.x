<?php

defined('BOOTSTRAP') or die('Access denied');

use Tygh\Registry;

/**
 * Installs Paysera payment processor.
 */
function fn_paysera_install_payment_processors()
{
    fn_paysera_remove_payment_processors();

    db_query("INSERT INTO ?:payment_processors ?e", array (
        'processor' => 'Paysera',
        'processor_script' => 'paysera_payment_processor.php',
        'processor_template' => 'views/orders/components/payments/cc_outside.tpl',
        'admin_template' => 'paysera.tpl',
        'callback' => 'N',
        'type' => 'P'
    ));
}

/**
 * Disables Paysera payment methods upon add-on uninstallation.
 */
function fn_paysera_remove_payment_processors()
{
    db_query("DELETE FROM ?:payment_processors WHERE processor_script = ?s", 'paysera.php');
}

/**
 * @param $order_id
 * @param $pp_response
 * @param array $force_notification
 */
function fn_paysera_payment_end($order_id, $pp_response, $force_notification = array())
{
    $valid_id = db_get_field("SELECT order_id FROM ?:order_data WHERE order_id = ?i AND type = 'S'", $order_id);

    if (!empty($valid_id)) {
        db_query("DELETE FROM ?:order_data WHERE order_id = ?i AND type = 'S'", $order_id);

        fn_update_order_payment_info($order_id, $pp_response);

        if ($pp_response['order_status'] == 'N' && !empty($_SESSION['cart']['placement_action']) && $_SESSION['cart']['placement_action'] == 'repay') {
            $pp_response['order_status'] = 'I';
        }
        fn_set_hook('finish_payment', $order_id, $pp_response, $force_notification);
    }

    fn_change_order_status($order_id, $pp_response['order_status'], '', $force_notification);
}