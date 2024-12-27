<?php 
function partnersAmountOverOrder(\WC_Order $order)
{
    $items = $order->get_items();
    $partners = [];

    if (!$items) {
        return $partners;
    }

    foreach ($items as $item) {
        foreach (getPartnersFromProduct($item->get_product_id()) as $partner) {
            $userId = (int) $partner['psp_partner'][0]['id'];
            $partner = new Partner($userId);

            $orderItemPartnerComission = $partner->calculateComission($item)->getComission();

            if (empty($partners[$userId])) {
                $partners[$userId] = ['value' => 0];
            }

            $partners[$userId]['value'] += $orderItemPartnerComission;
        }
    }

    return $partners;
}

function getPartnersFromProduct(int $productId): array
    {
        $partners = [
            'percentage' => carbon_get_post_meta($productId, 'psp_percentage_partners'),
            'fixed_amount' => [[
                'psp_partner' => carbon_get_post_meta($productId, 'psp_fixed_partner'),
                'psp_comission_value' => carbon_get_post_meta($productId, 'psp_comission_value')
            ]]
        ];

        $comissionType = carbon_get_post_meta($productId, 'psp_comission_type');

        return $partners[$comissionType];
    }
