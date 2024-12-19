# Pagar.me Split Payment
Pagar.me Split Payment is a plugin that allows you to split the payment of a WooCommerce order between multiple sellers. It allows you to define the percentage of the product value that each seller will receive.

Right now, the plugin only works with WooCommerce and Pagar.me as payment gateway.

## Requirements
- PHP 7.0 or higher
- WordPress 4.9 or higher
- WooCommerce 3.0 or higher
- Pagar.me WooCommerce plugin

## Installation
1. Download the plugin zip file from the [latest release](
2. Go to your WordPress admin panel and click on Plugins > Add New
3. Click on the "Upload Plugin" button and select the zip file you downloaded
4. Click on the "Install Now" button
5. Activate the plugin
6. Go to WooCommerce > Settings > Payments > Pagar.me and configure your Pagar.me API keys
7. Go to Pagar.me Recipients and add the main recipient.
8. Go to the product you want to split the payment and set the seller in the "Product Data" metabox
9. Go to file `wp-content/plugins/pagarme-payments-for-woocommerce/vendor/pagarme/ecommerce-module-core/src/Kernel/Services/APIService.php` and look for the following line:
```php
$configInfo = $this->configInfoService->retrieveInfo("");
```
10. Add the following line after the line above:
```php 
$orderRequest = apply_filters('pagarme_modify_order_request', $orderRequest);
```
11. Done!

## How it works
Right now it works only with single product checkout. When the customer buys a product, the plugin will split the payment between the main recipient and the seller of the product. The recipient defined in the product will receive the percentage of the product value defined in the product, while the main recipient will receive the remaining value.

## License
This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details

## Contributing
This project is open source and we would love your help.

To contribute, just fork the project, create a new branch and submit a pull request.

## Authors
- [Pagar.me](https://pagar.me)
- [Teknoffice Tecnologies Inc.](https://www.teknoffice.com)
- [Kashan Shah](https://www.kashanshah.com)

## Acknowledgments
- [WooCommerce](https://woocommerce.com/)
- [Pagar.me](https://pagar.me)

## Support
If you have any questions or need help, please contact us at [wordpress@teknoffice.com](mailto:wordpress@teknoffice.com)
