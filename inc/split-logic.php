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

    foreach ($orderRequest->payments as $payment) {
        // Ensure 'split' is set and initialize if not
        if (!isset($payment->split) || !is_array($payment->split)) {
            $payment->split = [];
        }

        $order = wc_get_order($orderRequest->code);
        $order_data = [
            "order_id" => $order->get_id(),
            "status" => $order->get_status(),
            "customer_id" => $order->get_customer_id(),
            "total" => $order->get_total(),
            "items" => [],
            "refunds" => $order->get_refunds(),
        ];

        foreach ($order->get_items() as $item_id => $item) {
            $order_data['items'][] = [
                "item_id" => $item_id,
                "product_id" => $item->get_product_id(),
                "variation_id" => $item->get_variation_id(),
                "quantity" => $item->get_quantity(),
                "subtotal" => $item->get_subtotal(),
                "total" => $item->get_total(),
                "name" => $item->get_name(),
                "sku" => $item->get_product() ? $item->get_product()->get_sku() : null,
                "meta_data" => $item->get_meta_data(),
            ];
        }
        error_log("here is the order" . json_encode($order_data));

        foreach ($order_data['items'] as $item) {
            $productId = $item["product_id"]; // Assuming 'code' is the product or order item ID
            $itemTotal = $item["total"];

            // Retrieve splits for this product
            $splits = get_post_meta($productId, '_pagarme_splits', true);
            if (!empty($splits)) {
                $itemTotalCents = round($itemTotal * 100); // Convert to cents
                $remainingPercentage = 100;
                $remainingAmount = $itemTotalCents;

                foreach ($splits as $split) {
                    $splitAmountCents = floor(($itemTotalCents * $split['percentage']) / 100);
                    $remainingAmount = $remainingAmount - $splitAmountCents;

                    $payment->split[] = [
                        "amount" => $splitAmountCents,
                        "recipient_id" => $split['recipient_id'],
                        "type" => "flat", // Use "flat" for calculated amounts
                        "options" => [
                            "charge_processing_fee" => !!($split['processing_fee'] === "yes"),
                            "charge_remainder_fee" => false,
                            "liable" => !!($split['liable'] === "yes")
                        ]
                    ];
                    $remainingPercentage -= $split['percentage'];
                }

                $payment->split[] = [
                    "amount" => $remainingAmount,
                    "recipient_id" => $mainRecipientId,
                    "type" => "flat",
                    "options" => [
                        "charge_processing_fee" => true,
                        "charge_remainder_fee" => false,
                        "liable" => true
                    ]
                ];
            } else {
                // If no splits are defined, assign the entire amount to the main recipient
                $payment->split[] = [
                    "amount" => $itemTotal * 100,
                    "recipient_id" => $mainRecipientId,
                    "type" => "flat",
                    "options" => [
                        "charge_processing_fee" => true,
                        "charge_remainder_fee" => false,
                        "liable" => true
                    ]
                ];
            }
        }
        // once we have the splits, set chanrge_remainder_fee to true for the last main recipient
        $payment->split[] = [
            "amount" => 0,
            "recipient_id" => $mainRecipientId,
            "type" => "flat",
            "options" => [
                "charge_processing_fee" => true,
                "charge_remainder_fee" => true,
                "liable" => true
            ]
        ];
    }

    error_log('Order request processed with splits: ' . json_encode($orderRequest));

    return $orderRequest;
}

// Hook the function to a filter in your plugin or theme
add_filter('pagarme_modify_order_request', 'modify_order_payments_split');