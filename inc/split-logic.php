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

    $pyment = json_decode(json_encode($orderRequest->payments[0], true));
    $noOfInstallments = $pyment->credit_card->installments;;
    $installments_without_interest = get_option('woocommerce_woo-pagarme-payments-credit_card_cc_installments_without_interest', 1);
    $baseInterestRate = get_option('woocommerce_woo-pagarme-payments-credit_card_cc_installments_interest', 10);
    $incrementalInterestRate = get_option('woocommerce_woo-pagarme-payments-credit_card_cc_installments_interest_increase', 1);

    $order = wc_get_order($orderRequest->code);
    $i = 0;

    $totalInterestRate = 0;

    if($noOfInstallments > $installments_without_interest) {
        $totalInterestRate = $baseInterestRate + ($incrementalInterestRate * ($noOfInstallments - $installments_without_interest - 1));
    }


    $totalOrderValue = 0;
    foreach ($order->get_items() as $item) {
        $orderRequest->items[$i]->amount = round(($item->get_total() + ($item->get_total() * $totalInterestRate / 100)) * 100);
        $totalOrderValue += $orderRequest->items[$i]->amount;
        $i++;
    }


    // Get the order
    $remainingAmount = $totalOrderValue;

    error_log('starting from here' . $remainingAmount . 'then orderRequest: ' . json_encode($orderRequest, true));

    foreach ($orderRequest->payments as $payment) {
        if (!isset($payment->split) || !is_array($payment->split)) {
            $payment->split = [];
        }

        foreach ($orderRequest->items as $item) {
            $productId = $item->code;
            $itemTotal = $item->amount; // Convert to cents 100

            // Get splits for the product
            $splits = get_post_meta($productId, '_pagarme_splits', true);

            if (!empty($splits) && is_array($splits) && count($splits) > 0 && $splits[0]['percentage'] > 0) {
                foreach ($splits as $split) {
                    $splitAmount = round(($itemTotal * $split['percentage']) / 100);
                    $remainingAmount -= round($splitAmount);

                    $payment->split[] = [
                        "amount" => round($splitAmount),
                        "recipient_id" => $split['recipient_id'],
                        "type" => "flat",
                        "options" => [
                            "charge_processing_fee" => !!($split['processing_fee'] === "yes"),
                            "charge_remainder_fee" => false,
                            "liable" => !!($split['liable'] === "yes")
                        ]
                    ];
                }
            } else {
                // If no splits are defined, assign item's full contribution to the main recipient
                $splitAmount = $itemTotal;
                $remainingAmount -= round($splitAmount);

                $payment->split[] = [
                    "amount" => round($splitAmount),
                    "recipient_id" => $split['recipient_id'],
                    "type" => "flat",
                    "options" => [
                        "charge_processing_fee" => true,
                        "charge_remainder_fee" => false,
                        "liable" => true
                    ]
                ];
            }
        }

        // Assign remaining percentage to the main recipient
        $payment->split[] = [
            "amount" => round($remainingAmount),
            "recipient_id" => $mainRecipientId,
            "type" => "flat",
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