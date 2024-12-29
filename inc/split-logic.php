<?php
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

    // Get the order
    $order = wc_get_order($orderRequest->code);
    $totalOrderValue = $order->get_total() * 100; // Convert to cents 150
    $totalAssignedPercentage = 0;

    foreach ($orderRequest->payments as $payment) {
        if (!isset($payment->split) || !is_array($payment->split)) {
            $payment->split = [];
        }

        foreach ($order->get_items() as $item) {
            $productId = $item->get_product_id();
            $itemTotal = $item->get_total() * 100; // Convert to cents 100
            $itemContributionToOrder = ($itemTotal / $totalOrderValue) * 100; // Item's percentage of the total order

            // Get splits for the product
            $splits = get_post_meta($productId, '_pagarme_splits', true);

            if (!empty($splits) && is_array($splits) && count($splits) > 0 && $splits[0]['percentage'] > 0) {
                foreach ($splits as $split) {
                    $splitPercentage = ($itemContributionToOrder * $split['percentage']) / 100;
                    $totalAssignedPercentage += round($splitPercentage);

                    $payment->split[] = [
                        "amount" => round($splitPercentage),
                        "recipient_id" => $split['recipient_id'],
                        "type" => "percentage",
                        "options" => [
                            "charge_processing_fee" => !!($split['processing_fee'] === "yes"),
                            "charge_remainder_fee" => false,
                            "liable" => !!($split['liable'] === "yes")
                        ]
                    ];
                }
            } else {
                // If no splits are defined, assign item's full contribution to the main recipient
                $totalAssignedPercentage += round($itemContributionToOrder);

                $payment->split[] = [
                    "amount" => round($itemContributionToOrder),
                    "recipient_id" => $mainRecipientId,
                    "type" => "percentage",
                    "options" => [
                        "charge_processing_fee" => true,
                        "charge_remainder_fee" => false,
                        "liable" => true
                    ]
                ];
            }
        }

        // Assign remaining percentage to the main recipient
        $remainingPercentage = 100 - $totalAssignedPercentage;
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

    error_log('Order request processed with normalized percentage splits: ' . json_encode($orderRequest));

    return $orderRequest;
}

// Hook the function to a filter in your plugin or theme
add_filter('pagarme_modify_order_request', 'modify_order_payments_split');