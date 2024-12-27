<?php 

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
            // Check if the metadata `_pp_split_recipient` exists

            $productId = $item->code; // Assuming the item has an ID corresponding to the product or order item ID
            error_log('find until here now' . $productId);
            $splits = get_post_meta($productId, '_pagarme_splits', true);
            if (!empty($splits)) {
                $remainingPercentage = 100;
                foreach ($splits as $split) {
                    // adding split
                    $payment->split[] = [
                        "amount" => $split["percentage"],
                        "recipient_id" => $split["recipient_id"],
                        "type" => "percentage",
                        "options" => [
                            "charge_processing_fee" => !!($split['processing_fee'] === "yes"),
                            "charge_remainder_fee" => false,
                            "liable" => !!($split['liable'] === "yes")
                        ]
                    ];
                    $remainingPercentage = $remainingPercentage - $split["percentage"];
                }

                // Add the remaining split to the main recipient
                $payment->split[] = [
                    "amount" => $remainingPercentage,
                    "recipient_id" => $mainRecipientId,
                    "type" => "percentage",
                    "options" => [
                        "charge_processing_fee" => true,
                        "charge_remainder_fee" => true,
                        "liable" => true
                    ]
                ];
            }
        }
    }
    
    error_log('find here all set to return ' . json_encode($orderRequest));

    return $orderRequest;
}

// Hook the function to a filter in your plugin or theme
add_filter('pagarme_modify_order_request', 'modify_order_payments_split');