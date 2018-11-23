

## PayEx Checkout

## Prerequisite

Requiered WooCommerce version 3.\*

PHP extension:

- SOAP

## **Installation**

##### **Note:** We can only guarantee that the modules work on standard versions of WooCommerce version 3. \*

#####Before you update to a newer version of WooCommerce, always make a backup as we don&#39;t guarantee functionality of new versions of WooCommerce. We can only guarantee that the modules work in the standard theme and checkout of WooCommerce.


1.Sign in as administrator on your Wordpress site, click the plugins menu item, then &quot;add new&quot;: 
![Screenshot 1](https://i.imgur.com/73xVM3G.jpg)

2.Click the Upload Plugin button in the top next to the Add Plugins title: ![Screenshot 2](https://i.imgur.com/P5AoVEb.jpg)

3.Find the file PayEx.Checkout.WooCommerce-master.zip on your computer, choose it and then click Install Now: ![Screenshot 3](https://i.imgur.com/jymgiOi.jpg)

4.When the module have uploaded successfully then you can activate it by clicking Activate Plugin:
 ![Screenshot 4](https://i.imgur.com/jWVgh7i.jpg)

######You can also install the modules using ftp/sftp:

1. Unzip the modules on your computer and transfer the folders over to the plugins folder which should be in root/wp-content/plugins/ of your site using FTP/SFTP, the root is where all your Wordpress files are.
2. Then log on to your Wordpress site as admin and go to the plugins menu and click activate on all the modules you installed.

## Configuration


1.To configure the modules go to the menu called WooCommerce and click settings.
 ![Screenshot 5](https://i.imgur.com/UnFT32C.jpg)
 
2.Then in the overhead menu you click the Payments tab.
 ![Screenshot 6](https://i.imgur.com/iv02O7t.jpg)
 
3.There you can either click the name PayEx Checkout or the Manage button next to it to configure the module.
 ![Screenshot 9](https://i.imgur.com/NBawFdf.jpg)

## PayEx Checkout

 ![Screenshot 8](https://i.imgur.com/ZdhScOf.png)

**Enable/disable:** Check the box to enable the plugin
**Title:** Title of plugin as the user will see it
**Description:** This controls the title which the user sees during checkout
**Merchant Token:**  **For Merchant Token, please contact your sales representative at PayEx. You can also find it in** [PayEx eCommerce Admin](https://admin.payex.com/psp/login) **.** Remember there are different Merchant Tokens for test and production mode. For more information contact PayEx support: [support.ecom@payex.com](mailto:support.ecom@payex.com).

**Payee Id:**  **For Payee Id, please contact your sales representative at PayEx. You can also find it in** [PayEx eCommerce Admin](https://admin.payex.com/psp/login) **.** Remember there are different Payee Ids for test and production mode. For more information contact PayEx support: [support.ecom@payex.com](mailto:support.ecom@payex.com).

**Test mode:** check this box if you want the module to run in test mode
**Debug:** check this to get debug logs

**Language:** The language that the module will display to the user

**Use PayEx Checkout instead of WooCommerce Checkout:** Check this box if you want to use PaEx Checkout instead of WooCommerce Checkout

**Terms &amp; Conditions Url:** A URI that contains your terms and conditions for the payment, to be linked on the payment page.

**Save Changes:** Click the Save Changes button for the changes to have effect.

## Translating the modules to other languages

To translate the modules you can use program called Poedit [https://poedit.net/](https://poedit.net/) or Wordpress translation plugins like Loco Translate [https://wordpress.org/plugins/loco-translate/](https://wordpress.org/plugins/loco-translate/) or WPML https://wpml.org/. The files you need to translate are located in wp-content/plugins/woocommerce-gateway-payex/languages/

Every module have their own pot file that will be used to translate in to your desired language.

Simply open up the .pot file with Poedit and select the line which you want to translate and in the bottom row add the translation. ![Screenshot 9](https://i.imgur.com/Bgg7Zzt.jpg)The translation will appear in the right column of the main window.

When you&#39;re done don&#39;t forget to set the language in the top right to the language you just translated to.

Important: when you&#39;re done, save the file as a .po file.  And the file needs to be named with the language code of your country. So for example if you translated the woocommerce-gateway-payex module to german then the file should be named payex-de\_DE

 You can find the language code of your country can be found here: [http://www.lingoes.net/en/translator/langcode.htm](http://www.lingoes.net/en/translator/langcode.htm)

To change to the language that you just translated to you just need open wp-config and change the language to the translated one, IE for german its should say:

 define(&#39;WPLANG&#39;, &#39;de\_DE&#39;);

## **Troubleshooting**

- You&#39;ll find logfiles of the payment module at [wordpress-root]/wp-content/uploads/wc-logs
- There have been cases where the total cost of a product in the cart and the total in the payex payment view isn&#39;t the same. That would be because of woocommerce rounding the total up or down because of lack of decimals. To fix this you have to add decimals to your products cost.

1. To do this you login as admin
2. Click woocommerce and then settings
3. There under general look for &quot;Number of decimals&quot; and set them to 2 and then save the changes.

