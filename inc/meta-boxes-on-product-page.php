<?php 
// Add a custom tab in the WooCommerce product data panel
add_filter('woocommerce_product_data_tabs', 'add_pagarme_payment_splits_tab');
function add_pagarme_payment_splits_tab($tabs) {
    $tabs['pagarme_splits'] = array(
        'label'    => __('Pagar.me Payment Splits', 'your-text-domain'),
        'target'   => 'pagarme_splits_tab',
        'class'    => array('show_if_simple', 'show_if_variable'),
    );
    return $tabs;
}

// Add content to the custom tab
add_action('woocommerce_product_data_panels', 'add_pagarme_payment_splits_fields');
function add_pagarme_payment_splits_fields() {
    global $post;

    // Retrieve the saved meta data
    $splits = get_post_meta($post->ID, '_pagarme_splits', true);
    ?>
    <div id="pagarme_splits_tab" class="panel woocommerce_options_panel">
        <div id="pagarme_splits_container">
            <?php
            if (!empty($splits)) {
                foreach ($splits as $split) {
                    ?>
                    <div class="pagarme_split_row">
                        <p>
                            <label style="margin: 0;"><?php _e('Recipient ID:', 'your-text-domain'); ?></label>
                            <input type="text" name="pagarme_splits[recipient_id][]" value="<?php echo esc_attr($split['recipient_id']); ?>" />
                        </p>
                        <p>
                            <label style="margin: 0;"><?php _e('Split Percentage:', 'your-text-domain'); ?></label>
                            <input type="number" name="pagarme_splits[percentage][]" max="100" value="<?php echo esc_attr($split['percentage']); ?>" />
                        </p>
                        <p>
                            <label style="margin: 0;"><?php _e('Liable for Chargeback:', 'your-text-domain'); ?></label>
                            <input type="checkbox" name="pagarme_splits[liable][]" <?php checked($split['liable'], 'yes'); ?> value="yes" />
                        </p>
                        <p>
                            <label style="margin: 0;"><?php _e('Charge Processing Fee:', 'your-text-domain'); ?></label>
                            <input type="checkbox" name="pagarme_splits[processing_fee][]" <?php checked($split['processing_fee'], 'yes'); ?> value="yes" />
                        </p>
                        <p>
                            <button type="button" class="button remove_split_button" class="button">
                                <?php _e('Remove Split', 'your-text-domain'); ?>
                            </button>
                        </p>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        <p stle="text-align: right;">
            <button type="button" id="add_split_button" class="button"><?php _e('Add Split', 'your-text-domain'); ?></button>
        </p>
    </div>

    <script>
        jQuery(function ($) {
            $('#add_split_button').on('click', function () {
                const rowHtml = `
                <div class="pagarme_split_row">
                    <p><label style="margin: 0;">Recipient ID:</label><input type="text" name="pagarme_splits[recipient_id][]" /></p>
                    <p><label style="margin: 0;">Split Percentage:</label><input type="number" name="pagarme_splits[percentage][]" max="100" /></p>
                    <p><label style="margin: 0;">Liable for Chargeback:</label><input type="checkbox" name="pagarme_splits[liable][]" value="yes" /></p>
                    <p><label style="margin: 0;">Charge Processing Fee:</label><input type="checkbox" name="pagarme_splits[processing_fee][]" value="yes" /></p>
                    <p><button type="button" class="button remove_split_button" class="button"><?php _e('Remove Split', 'your-text-domain'); ?></button></p>
                </div>`;
                $('#pagarme_splits_container').append(rowHtml);
            });
            $('body').on('click', '.remove_split_button', function() {
                $(this).closest('.pagarme_split_row').remove();
            });
        });
    </script>
    <?php
}

// Save the custom tab data
add_action('woocommerce_process_product_meta', 'save_pagarme_payment_splits_fields');
function save_pagarme_payment_splits_fields($post_id) {
    if (isset($_POST['pagarme_splits'])) {
        $splits = array();
        $total_percentage = 0;

        foreach ($_POST['pagarme_splits']['recipient_id'] as $index => $recipient_id) {
            $percentage = floatval($_POST['pagarme_splits']['percentage'][$index]);
            $total_percentage += $percentage;

            if ($total_percentage > 100) {
                wc_add_notice(__('The total split percentage cannot exceed 100%.', 'your-text-domain'), 'error');
                return;
            }

            $splits[] = array(
                'recipient_id'    => sanitize_text_field($recipient_id),
                'percentage'      => $percentage,
                'liable'          => isset($_POST['pagarme_splits']['liable'][$index]) ? 'yes' : 'no',
                'processing_fee'  => isset($_POST['pagarme_splits']['processing_fee'][$index]) ? 'yes' : 'no',
            );
        }

        update_post_meta($post_id, '_pagarme_splits', $splits);
    }
}
