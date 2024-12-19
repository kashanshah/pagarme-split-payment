<?php
/**
 * Plugin Name: Pagar.me Split Payment with Recipient Management
 * Description: Implements Pagar.me split payment with recipient management and dynamic recipient selection for WooCommerce products.
 * Version: 1.1
 * Author: Teknoffice Technologies Inc.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// 1. Admin Page to Manage Main Recipient ID
add_action('admin_menu', 'pagarme_recipient_management_menu');
function pagarme_recipient_management_menu() {
    // Add main menu page
    add_menu_page(
        'Pagar.me Recipient Management',
        'Pagar.me Recipients',
        'manage_options',
        'pagarme-recipients',
        'pagarme_recipient_management_page',
        'dashicons-admin-network',
        20
    );
}


// Hook into Pagar.me marketplace configuration
// add_filter("pagarme_marketplace_config", 'configure_pagarme_marketplace', 10, 1);
function configure_pagarme_marketplace($marketplaceConfig) {
    $marketplaceConfig->mainRecipientId = "re_xxxxxxxxx0x00000xxxx000xx"; // Replace with your Marketplace recipient ID
    return $marketplaceConfig;
}

// add_action( 'woocommerce_checkout_order_processed', 'update_order_request_array', 10, 1 );

function update_order_request_array( $order_id ) {
    // Get the order object
    $order = wc_get_order( $order_id );
    error_log('check whats by default' . json_encode($order));

    // Initialize a placeholder for the order request array
    $orderRequest = new stdClass();
    $orderRequest->payments = [];

    // Example: Simulating a payment array if it doesn't already exist
    if (!isset($orderRequest->payments[0])) {
        $orderRequest->payments[0] = new stdClass();
    }

    // Check if 'split' is set and initialize if not
    if (!isset($orderRequest->payments[0]->split) || !is_array($orderRequest->payments[0]->split)) {
        $orderRequest->payments[0]->split = [];
    }

    // Add the first split item
    $orderRequest->payments[0]->split[] = [
        "amount" => 50,
        "recipient_id" => "re_cm4t4e5eg0wig0l9tqdyci0vc",
        "type" => "percentage",
        "options" => [
            "charge_processing_fee" => true,
            "charge_remainder_fee" => true,
            "liable" => true
        ]
    ];

    // Add the second split item
    $orderRequest->payments[0]->split[] = [
        "amount" => 50,
        "type" => "percentage",
        "recipient_id" => "re_cm4t4e5eg0wig0l9tqdyci0vc",
        "options" => [
            "charge_processing_fee" => false,
            "charge_remainder_fee" => false,
            "liable" => false
        ]
    ];

    // Log the updated order request array
    error_log('Updated Order Request: ' . json_encode($orderRequest));
}

// Hook into Pagar.me split rules
// add_filter("pagarme_split_order", 'add_pagarme_split_rules', 10, 2);
function add_pagarme_split_rules(\WC_Order $order, $paymentMethod) {
    $splitArray = [
        'sellers' => [],
        'marketplace' => [
            'totalCommission' => 0
        ]
    ];

    // Iterate through order items
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();

        // Fetch product meta data
        $recipient_id = get_post_meta($product_id, '_pagarme_recipient_id', true);
        $split_type = get_post_meta($product_id, '_pagarme_split_type', true);
        $split_value = get_post_meta($product_id, '_pagarme_split_value', true);

        // Ensure all meta data is present
        if (!$recipient_id || !$split_type || !$split_value) {
            continue; // Skip this product if split data is incomplete
        }

        // Calculate split values
        $line_total = $item->get_total() * 100; // Convert to cents
        $marketplace_commission = 0;
        $recipient_commission = 0;

        if ($split_type === 'percentage') {
            $marketplace_commission = intval(($split_value / 100) * $line_total);
        } elseif ($split_type === 'fixed') {
            $marketplace_commission = intval($split_value * 100);
        }

        $recipient_commission = $line_total - $marketplace_commission;

        // Add seller split data
        $splitArray['sellers'][] = [
            'marketplaceCommission' => $marketplace_commission,
            'commission' => $recipient_commission,
            'pagarmeId' => $recipient_id
        ];

        // Add marketplace commission
        $splitArray['marketplace']['totalCommission'] += $marketplace_commission;
    }
    
    $ret = array(array(
        "amount" => 50,
        "recipient_id" => "re_cm4t4e5eg0wig0l9tqdyci0vc",
        "type" => "percentage",
        "options" => array(
            "charge_processing_fee" => true,
            "charge_remainder_fee" => true,
            "liable" => true
       )
    ),
    array(
        "amount" => 50,
        "type" => "percentage",
        "recipient_id" => "re_cm4t4e5eg0wig0l9tqdyci0vc",
        "options" => array(
            "charge_processing_fee" => false,
            "charge_remainder_fee" => false,
            "liable" => false
        )
      )
  );
    error_log('this is the splitarray' . json_encode($ret));
    
    return $ret;

    // return $splitArray;
}

function pagarme_recipient_management_page() {
    // Save main recipient ID if submitted
    if ($_POST['pagarme_main_recipient_id']) {
        update_option('pagarme_main_recipient_id', sanitize_text_field($_POST['pagarme_main_recipient_id']));
        echo '<div class="updated"><p>Main Recipient ID Updated!</p></div>';
    }

    $main_recipient_id = get_option('pagarme_main_recipient_id', '');
    ?>
    <div class="wrap">
        <h1>Pagar.me Recipient Management</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="pagarme_main_recipient_id">Main Recipient ID</label></th>
                    <td><input type="text" id="pagarme_main_recipient_id" name="pagarme_main_recipient_id" value="<?php echo esc_attr($main_recipient_id); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button('Save Main Recipient ID'); ?>
        </form>
    </div>
    <?php
}


/**
 * Filter to modify the order request payments and add splits.
 *
 * @param object $orderRequest The order request object.
 * @return object The modified order request object.
 */
function modify_order_payments_split($orderRequest)
{
    if (!isset($orderRequest->items) || !is_array($orderRequest->items)) {
        error_log("Error: 'items' array is not properly initialized.");
        return $orderRequest;
    }

    $mainRecipientId = get_option('pagarme_main_recipient_id', '');
    if (empty($mainRecipientId)) {
        error_log("Error: Main recipient ID is not configured.");
        return $orderRequest;
    }

    foreach ($orderRequest->payments as $payment) {
        // Ensure 'split' is set and initialize if not
        if (!isset($payment->split) || !is_array($payment->split)) {
            $payment->split = [];
        }

        foreach ($orderRequest->items as $item) {
            error_log('find until here now');
            // Check if the metadata `_pp_split_recipient` exists

            $productId = $item->code; // Assuming the item has an ID corresponding to the product or order item ID
            $recipientId = get_post_meta($productId, '_pp_split_recipient', true);
            $splitPercentage = get_post_meta($productId, '_pp_split_percentage', true);
            $liable = get_post_meta($productId, '_pp_split_liable', true);
            $chargeFee = get_post_meta($productId, '_pp_split_charge_fee', true);

            // Validate and cast values to the correct types
            $splitPercentage = is_numeric($splitPercentage) ? (int)$splitPercentage : 0; // Ensure it's a number
            $liable = filter_var($liable, FILTER_VALIDATE_BOOLEAN); // Ensure it's a boolean
            $chargeFee = filter_var($chargeFee, FILTER_VALIDATE_BOOLEAN); // Ensure it's a boolean

            if (empty($recipientId)) {
                continue;
            }

            if ($splitPercentage > 0) {
                // Add the split for the product-specific recipient
                $payment->split[] = [
                    "amount" => $splitPercentage,
                    "recipient_id" => $recipientId,
                    "type" => "percentage",
                    "options" => [
                        "charge_processing_fee" => $liable,
                        "charge_remainder_fee" => false,
                        "liable" => $chargeFee
                    ]
                ];

                // Add the remaining split to the main recipient
                $payment->split[] = [
                    "amount" => 100 - $splitPercentage,
                    "recipient_id" => $mainRecipientId,
                    "type" => "percentage",
                    "options" => [
                        "charge_processing_fee" => !$chargeFee,
                        "charge_remainder_fee" => true,
                        "liable" => !$liable
                    ]
                ];
                error_log('find here all set to return ' . json_encode($payment));
            } else {
                error_log("Warning: Split percentage is missing or invalid for item ID: {$item->id}");
            }
        }
    }

    return $orderRequest;
}

// Hook the function to a filter in your plugin or theme
add_filter('pagarme_modify_order_request', 'modify_order_payments_split');

