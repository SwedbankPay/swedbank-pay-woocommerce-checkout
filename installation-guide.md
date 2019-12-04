Installation and configuration manual for Swedbank Pay WooCommerce Checkout 
------------

This plugin is using Swedbank Pay RESTful API. For documentation about the API see https://developer.swedbankpay.com/payments/
## Prerequisites

1. WooCommerce 3.*

## Installation

Before you update to a newer version of WooCommerce, always make a backup as we don’t guarantee functionality of new versions of WooCommerce. We can only guarantee that the modules work in the standard theme and checkout of WooCommerce.

1. Sign in as administrator on your WordPress site, click the plugins menu item, then “add new”. 
![image1](https://user-images.githubusercontent.com/6286270/63705267-0f763780-c82d-11e9-901e-a9b94c993f1f.png)

2. Find the plugin in the WordPress Plugin Directory
![image2](https://user-images.githubusercontent.com/6286270/63705299-20bf4400-c82d-11e9-9a70-e6323b9bcd31.png)

## Configuration

Navigate to **WooCommerce -> Settings -> Payments** and pick the payment Method You want to configure.

![image3](https://user-images.githubusercontent.com/6286270/63705344-303e8d00-c82d-11e9-8383-919365ab61d1.png)

There are explanatory descriptions under each setting.
If you don’t have a special solution you want to check the box “Use Swedbank Pay Checkout instead of WooCommerce Checkout”

![image4](https://user-images.githubusercontent.com/6286270/63705382-44828a00-c82d-11e9-9940-b5632c76dd4d.png)

To connect your module to the Swedbank Pay system you need to navigate to https://admin.stage.payex.com/psp/login for test and https://admin.externalintegration.payex.com/ for production accounts and generate tokens:

![image5](https://user-images.githubusercontent.com/6286270/63705424-5e23d180-c82d-11e9-8f8d-f332594a444a.png)

Navigate to **Merchant->New Token** and mark the methods you intend to use. For more information about each method contact your Swedbank Pay Sales representative.
Copy the Token and insert it in the appropriate field in your WooCommerce Payment Method setting.

![image6](https://user-images.githubusercontent.com/6286270/69639000-0c712a00-105c-11ea-944c-a2b254cb34a1.png)

Navigate further down on the same page to select **Checkin country** and mark the country for which the payment method should work.

![image7](https://user-images.githubusercontent.com/6286270/69638120-6244d280-105a-11ea-9453-ae8352722c5b.png)

Don’t Forget to save.
Note that Tokens differ for Production and Test.

## Translation

For translation see https://developer.wordpress.org/themes/functionality/localization/#translate-po-file

## Troubleshooting
You’ll find the logfiles under **WooCommerce->Status->Logs**.
If you have rounding issues try to set Number of Decimals to “2” under **WooCommerce -> Settings -> General -> Currency options**

![image8](https://user-images.githubusercontent.com/6286270/63705458-78f64600-c82d-11e9-8d60-d76ecdfb06c8.png)


