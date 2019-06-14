Installation and configuration manual for PayEx WooCommerce Checkout 
------------

This plugin is using PayEx RESTful API. For documentation about the API see https://developer.payex.com/xwiki/wiki/developer/view/Main/ecommerce/payex-checkout-main/
## Prerequisites

1. WooCommerce 3.*

## Installation

Before you update to a newer version of WooCommerce, always make a backup as we don’t guarantee functionality of new versions of WooCommerce. We can only guarantee that the modules work in the standard theme and checkout of WooCommerce.

1. Sign in as administrator on your WordPress site, click the plugins menu item, then “add new”. 
![image1](https://payex.github.io/payex-woocommerce-checkout/docs/image1.png)

2. Find the plugin in the WordPress Plugin Directory
![image2](https://payex.github.io/payex-woocommerce-checkout/docs/image2.png)

## Configuration

Navigate to **WooCommerce -> Settings -> Payments** and pick the payment Method You want to configure.

![image3](https://payex.github.io/payex-woocommerce-checkout/docs/image3.png)

There are explanatory descriptions under each setting.
If you don’t have a special solution you want to check the box “Use PayEx Checkout instead of WooCommerce Checkout”

![image4](https://payex.github.io/payex-woocommerce-checkout/docs/image4.png)

To connect your module to the PayEx system you need to navigate to https://admin.stage.payex.com/psp/login for test and https://admin.externalintegration.payex.com/ for production accounts and generate tokens:

![image5](https://payex.github.io/payex-woocommerce-checkout/docs/image5.png)

Navigate to **Merchant->New Token** and mark the methods you intend to use. For more information about each method contact your PayEx Sales representative.
Copy the Token and insert it in the appropriate field in your WooCommerce Payment Method setting.

![image6](https://payex.github.io/payex-woocommerce-payments/docs/image6.png)

Don’t Forget to save.
Note that Tokens differ for Production and Test.

## Translation

For translation see https://developer.wordpress.org/themes/functionality/localization/#translate-po-file

## Troubleshooting
You’ll find the logfiles under **WooCommerce->Status->Logs**.
If you have rounding issues try to set Number of Decimals to “2” under **WooCommerce -> Settings -> General -> Currency options**

![image7](https://payex.github.io/payex-woocommerce-payments/docs/image7.png)
