<?php
/**
 * Plugin Name: Pagar.me Split Payment with Recipient Management
 * Description: Implements Pagar.me split payment with recipient management and dynamic recipient selection for WooCommerce products.
 * Version: 1.1
 * Author: Teknoffice Technologies Inc.
 * Author URI: https://www.teknoffice.com
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once('inc/admin-menu.php');
include_once('inc/split-logic.php');
include_once('inc/meta-boxes-on-product-page.php');
