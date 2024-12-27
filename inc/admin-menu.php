<?php 
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
