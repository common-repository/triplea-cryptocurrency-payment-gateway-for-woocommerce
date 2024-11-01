
=== Cryptocurrency Payment Gateway for WooCommerce ===

Contributors: tripleatechnology, adnanshawkat, zamanshakir
Donate link: https://triple-a.io/
Tags: stablecoins, crypto payments, crypto ownership, crypto payment gateway, crypto
Requires at least: 5.5
Tested up to: 6.6.2
Stable tag: 2.0.22
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Start accepting crypto payments on your store with our secure and easy-setup white-label crypto payments plugin.

Install our plugin in under a minute to accept fast crypto payments from your customers, attract new customers and increase your business’s revenue.

#### Triple-A for WooCommerce

Benefits of installing this plugin:

- Accept a wide range of cryptocurrencies such as Bitcoin, Lightning Bitcoin, Ethereum, USDC, and USDT.
- Our plugin is completely wallet agnostic: your customers can pay from any crypto wallet
- Receive your funds in your preferred local currency. We support USD, EUR, GBP, and over 50 other [fiat currencies](https://triplea-technologies.stoplight.io/docs/triplea-api-doc/6ff5db26b3b16-supported-fiat-currencies)
- Get settled directly into your bank account or withdraw your funds in crypto
- Instantly get notified about all processed transactions
- Track and manage all of your transactions via your merchant dashboard
- Get paid in crypto without any chargeback or volatility risk
- No additional setup cost is required
- Benefit from a settlement fee of only 1.0%. Learn more about our pricing [here](https://support.triple-a.io/knowledge/what-fees-will-i-have-to-pay-to-use-triplea-for-payment-processing)
- Control and manage the cryptocurrencies you wish to accept on your site. Click [here](https://support.triple-a.io/knowledge/how-do-i-select-which-cryptocurrencies-to-accept) to learn how to easily enable or disable cryptocurrencies from your merchant dashboard.

#### Customer’s Journey:

1. Your customer proceeds to the checkout page and selects one of the displayed cryptocurrencies.
2.  A QR code payment form will be generated and will prompt the user to complete their payment. They can scan the QR code, click “Open in Wallet” or copy the payment details.
3.  Our locked-in exchange rate offers your customer 25 minutes to complete their payment while avoiding any price fluctuations.
4.  Once the payment has been made, the merchant will receive an instant confirmation of the successful payment.

Watch demo video [here](https://www.youtube.com/watch?v=Y3JZi3WHpYQ)

#### Merchant’s Journey:

1. Once the payment is successful, the merchant will receive their funds in their Triple-A account. They can access it directly from their dashboard.
2. If you choose to have your funds settled in fiat, the funds will be transferred automatically to your bank account within the next business day, providing the funds in the account amounting to USD 1000 or equivalent. Learn more about our settlement process [here.](https://support.triple-a.io/knowledge/settlement-withdrawals)
3. Alternatively, merchants can also choose to be settled in crypto and withdraw their funds to any wallet via the TripleA dashboard. If you wish to be settled in crypto, reach out to our team via [support@triple-a.io](mailto:support@triple-a.io), and our team will enable this option for you.

**Create your Triple-A account [here](https://triple-a.io/signup/) & start accepting crypto payments.**

**Please note that you will need to complete our standard KYB Account verification process before we can start settling your funds in your bank account. Learn more here or get in touch with us via [sales@triple-a.io](mailto:sales@triple-a.io)**

#### About Triple-A

Triple-A is a licensed crypto payment gateway empowering businesses to attract new customers and increase their revenue by allowing them to enable crypto payments and payouts seamlessly.

Thanks to Triple-A’s white-label crypto payments solutions, businesses from all industries can leverage the benefits of the growing crypto market without being exposed to volatility risk or having to handle or convert digital currencies.

Their solutions are compatible with all wallets, easy to integrate, and offer instant confirmation, locked-in exchange rates, and chargeback protection.

Licensed by the central bank of Singapore and trusted by over 15k businesses, TripleA aims to continue making crypto payments more accessible for businesses across the globe.

As a licensed entity, terms and conditions apply to settlements and AML, etc. Please find out more about us at [www.triple-a.io](https://triple-a.io/)

== Installation ==

1. Install via the searchable Plugin Directory within your WordPress site's plugin page.
1. (Or: Upload `triplea-cryptocurrency-payment-gateway-for-woocommerce.php` to the `/wp-content/plugins/` directory.)
1. Activate the plugin through the 'Plugins' menu in WordPress.

#### Using a Triple-A local currency wallet:

1. Provide merchant key, client id, and client secret on the settings page.
2. Click 'Proceed'. Your Triple-A local currency account will be connected to your Triple-A dashboard.
3. Settings will be saved, the page will reload automatically and you will be good to go!

#### Customise look & feel

We like to keep things short, clear, and simple.
If you require more than customizing the payment gateway text and logo, let us know at <a href="mailto:plugin.support@triple-a.io">plugin.support@triple-a.io</a>.

Certain WooCommerce plugins might add custom order statuses. We have tried to accommodate this, however carefully test a payment if you change the default settings, and let us know if you're uncertain about anything.

== Frequently Asked Questions ==

= Can customers pay with cryptocurrencies without registering on my website? =

There is no account needed for your clients to pay with cryptocurrencies. They just scan the payment QR code and enter the right amount to pay. Very Easy.

= Which cryptocurrency wallet do you support? =

We support all wallets allowing public keys, meaning BIP 44-compatible HD wallets.

= Can you help me to integrate cryptocurrency payments into my website? =

Of course, our support team is always here to help. <a href="mailto:plugin.support@triple-a.io">Contact us by e-mail</a>.


== Screenshots ==

1. Receive your account details by email - Crypto Payment Gateway by Triple-A
2. Start accepting crypto payments on your site - Crypto Payment Gateway by Triple-A
3. Customize your site's payment form - Crypto Payment Gateway by Triple-A
4. Verify your account - Crypto Payment Gateway by Triple-A
5. Offer your customers an intuitive payment journey - Crypto Payment Gateway by Triple-A


== Changelog ==

= 2.0.22 =
Add: Order-pay feaure

= 2.0.21 =
Add: Multi currency plugin compabilty on checkout page

= 2.0.20 =
Added: Removed depreciated error

= 2.0.19 =
Added: Payment session removed when currenc/total ammount changes

= 2.0.18 =
Added: Updated tags for plugin

= 2.0.17 =
Fixed: Leave site promt removed in checkout page after displaying payment form success

= 2.0.16 =
Fixed: Order currency is now visible in the payment form

= 2.0.15 =
Fixed: Checkout page leave confirmation after requesting for payment form

= 2.0.14 =
Fixed: WooCommerce product page seo plugin conflict

= 2.0.13 =
Added: Compatibility with WooCommerce HPOS (High-Performance Order System)

= 2.0.12 =
Fixed: Validation for getting token and thank you message

= 2.0.11 =
Fixed: Review message will be displayed only for admin
Fixed: Validation for Thank you message for less payments

= 2.0.10 =
Fixed: Updated priority for checking custom validation of the checkout form

= 2.0.9 =
Fixed: Updated checkout page form validation notices display logic

= 2.0.8 =
Fixed: Check custom validation in checkout form before calling for payment form
Added: Blur effect on payment form
Added: Payment Reference added on Order Notes

= 2.0.7 =
Fixed: Unusual token request from elementor or javascript from frontend
Fixed: Prevent printing confedential information into logs
Fixed: Add triple-a plugin version to user-agent

= 2.0.6 =
Fixed: Multiple oAuth token generation request issue while calling the payment form issue fixed

= 2.0.5 =
Fixed: Payment form loading after clicking "Pay with Cryptocurrency" issue fixed

= 2.0.4 =
Removed: Payment Method description is removed
Fix: Minor css issues fixed

= 2.0.3 =
Added: Order status option added to settings page.
Removed: Console errors from checkout js file.
Updated: Language files updated

= 2.0.2 =
Fixed: New text domain updated in plugin header & fixed the translation files

= 2.0.1 =
Fixed: Subscription product cart contains issue fixed

= 2.0.0 =
Added: Brand new code base from scratch for better performance

= 1.8.3 =
Fixed: oAuth token generation issue while calling the payment issue fixed

= 1.8.2 =
Updated: Updated the refreshToken checker to check the expiry of the token
Fixed: oAuth token addition error fix while settings saved

= 1.8.1 =
Removed: Personal wallet option was removed & cannot be used anymore.

= 1.8 =
Removed: The signup option was removed from the plugin settings page.
Added: New fields added to add your merchant into the WooCommerce ecosystem.
Fixed: Some minor technical issues were fixed.

= 1.7.6 =
Added: Debug logs security enhancement. Users will be asked to enable specific options to print sensitive information into the log.
Updated: Language files updated.

= 1.7.5 =
Added: Review notice option dismiss functionality added.
Fix: Some minor bugs with frontend code.
= 1.7.4 =
Removed: Personal Wallet [Expert Mode] is removed though existing user can use this feature but consider updating your wallet to local currency.
Added: Admin notice added.
Fix: Minor code fixation.
Fix: Payment form loading view issue fix.

= 1.7.3 =
Added: Support for choosing preferred currency for settlement from Triple-A dashboard while signing up from the plugin.
Fix: Bug in checkout page while debuging is on.

= 1.7.2 =
Added: Preserve settings options added to keep the record of plugin settings in db after deactivating or uninstalling

= 1.7.1 =
Added: Improved compatibility with 3rd party WooCommerce plugins such as 'Payment Gateway Based Fees and Discounts'.
Fix: Some minor bug with api update

= 1.7 =
Added: Allowed multi-crypto payments (ETH, USDT, LNBVC)
Update: Updated the strings to crypto instead of bitcoin
Update: Translate files updated
Fix: Some minor fix to backend option

= 1.6.5 =
Fix: Order pay page checkout issue resolved
Fix: Undefined offset issue on class-rest issue resolved
Update: Strong message added to pay in full on checkout option

= 1.6.4 =
Message update for Master public key of your cryptocurrency wallet field instruction on expert mode.

= 1.6.3 =
Disabling the "place order"(/"waiting for payment") button the on Checkout page when our payment method is selected, to avoid confusing customers.
Added a fix for some sites where our payment gateway shows up for subscription products (it should not appear for these).
Updated real-time updates due to API update.

= 1.6.2 =
Security updates to code dependencies. No change to plugin's features.

= 1.6.1 =
Adding support for testnet cryptocurrency public keys (starting with upub and vpub).

= 1.6.0 =
Improvements to translations. Added/updated French, Spanish, Portuguese, Dutch. Some more text made translatable.
Some important bug fixes added.

= 1.5.7 =
Made a fix allowing the plugin to be disabled :)
Adding debug log messages for checkout form validation.

= 1.5.6 =
Important small fix for merchants experiencing real-time update issues for their orders.

= 1.5.5 =
Minor fix for sites experiencing issues displaying the payment form.

= 1.5.3 =
Minor fix for sites experiencing issues getting the OTP.

= 1.5.2 =
Better public key validation and clearer error messages to assist users having issues with account activation.

= 1.5.1 =
WooCommerce Bundled Products compatibility fix.

= 1.5.0 =
Upgraded the plugin to use a new API by Triple-A.
Instant confirmation available when using local currency settlement.
Better checkout page integration, less UI/CSS bugs thanks to iframe loading, and more.
Better account management (sandbox payments available; better email notifications; integration credentials provided..).

= 1.4.8 =
Small change in debug info display.

= 1.4.7 =
Qr code not updated when user paid too little.

= 1.4.6 =
CSS styling improvement to avoid interference with qr code size on some sites.

= 1.4.5 =
Bug fix for users experiencing problems updating product images while other plugins (such as Tera Wallet) are also enabled.

= 1.4.3 =
Minor bug fixes and plugin file structure improvements.

= 1.4.0 =
Payment form expiry now at 25 minutes instead of 15.
Minor QR code related bug fix.
Plugin stability and performance improvements, thanks to open-source contributors.

= 1.3.1 =
Added configuration options for bitcoin payment option in checkout page.
Added order status customisation (only for those who know exactly what they're doing!).
Added debug log settings (enable/disable logging, easily view log, easily clear log).

= 1.2.1 =
Confirmed working with latest WooCommerce v4.0.1 and latest Wordpress (v5.3.2)

= 1.2.0 =
Overhauled plugin settings page to make things simpler, clearer, and hopefully much less confusing for some users.

= 1.1.3 =
Fixed T&C not appearing on some sites with our plugin enabled.

= 1.1.2 =
The QR code is now a link. Click or tap to open with the default bitcoin wallet (should work on mobile, depending on mobile setup and app used).
Minor improvements were added for sites with custom checkout submit buttons.


== Upgrade Notice ==

= 2.0.22 =
Simply install the update. No further action is needed.

= 2.0.21 =
Simply install the update. No further action is needed.

= 2.0.20 =
Simply install the update. No further action is needed.

= 2.0.19 =
Simply install the update. No further action is needed.

= 2.0.18 =
Simply install the update. No further action is needed.

= 2.0.17 =
Simply install the update. No further action is needed.

= 2.0.16 =
Simply install the update. No further action is needed.

= 2.0.15 =
Simply install the update. No further action is needed.

= 2.0.14 =
Simply install the update. No further action is needed.

= 2.0.13 =
Simply install the update. No further action is needed.

= 2.0.12 =
Simply install the update. No further action is needed.

= 2.0.11 =
Simply install the update. No further action is needed.

= 2.0.10 =
Simply install the update. No further action is needed.

= 2.0.9 =
Simply install the update. No further action is needed.

= 2.0.8 =
Simply install the update. No further action is needed.

= 2.0.7 =
Simply install the update. No further action is needed.

= 2.0.6 =
Simply install the update. No further action is needed.

= 2.0.5 =
Simply install the update. No further action is needed.

= 2.0.4 =
Simply install the update. No further action is needed.

= 2.0.3 =
Simply install the update. No further action is needed.

= 2.0.2 =
Simply install the update. No further action is needed.

= 2.0.1 =
Simply install the update. No further action is needed.

= 2.0.0 =
Take a backup before installing the update

= 1.8.3 =
Simply install the update. No further action is needed.

= 1.8.2 =
Take a backup before installing the update

= 1.8.1 =
Take a backup before installing the update

= 1.8 =
Take a backup before installing the update

= 1.7.6 =
Simply install the update. No further action is needed.

= 1.7.5 =
Simply install the update. No further action is needed.

= 1.7.4 =
Simply install the update. No further action is needed.

= 1.7.3 =
If you want to choose your preferred currency for settlement from Triple-A dashboard, then you need to uninstall the plugin & install it, then signup up again.

= 1.7.2 =
Simply install the update. No further action is needed.

= 1.7.1 =
Simply install the update. No further action is needed.

= 1.7 =
Simply install the update. No further action is needed.

= 1.6.5 =
Simply install the update. No further action is needed.

= 1.6.4 =
Simply install the update. No further action is needed.

= 1.6.3 =
Simply install the update. No further action is needed.

= 1.6.2 =
Simply install the update. No further action is needed.

= 1.6.1 =
Simply install the update. No further action is needed.

= 1.6.0 =
Simply install the update. No further action is needed.

= 1.5.7 =
Simply update. No further action is needed.

= 1.5.5 =
Minor fixes for sites experiencing issues displaying the payment form.

= 1.5.3 =
Minor fix for sites experiencing issues getting the OTP.

= 1.5.2 =
Better public key validation and clearer error messages to assist users having issues with account activation.

= 1.5.1 =
WooCommerce Bundled Products compatibility fix.

= 1.5.0 =
Upgraded the plugin to use a new API by Triple-A.
Instant confirmation available when using local currency settlement.
Better checkout page integration, less UI/CSS bugs thanks to iframe loading, and more.
Better account management (sandbox payments available; better email notifications; integration credentials provided..).

= 1.4.8 =
Small change in debug info display.

= 1.4.7 =
Qr code not updated when user paid too little.

= 1.4.6 =
CSS styling improvement to avoid interference with qr code size on some sites.

= 1.4.5 =
Bug fix for users experiencing problems updating product images while other plugins (such as Tera Wallet) are also enabled.

= 1.4.3 =
No need to update unless the "place order/pay with cryptocurrency" button on your checkout page is misbehaving.

= 1.4.0 =
Please update this plugin, to ensure the best experience for yourself and your customers.
Plugin stability and performance improved. Minor bugfixes included.
Simply let WordPress update the plugin for you, no further action required.

= 1.3.1 =
Apologies for the required bug fix for the encryption system used.
Please update this plugin to ensure you no users experience problems with placing orders.

= 1.3.0 =
Please update this plugin to ensure you benefit from the latest improvements.
After updating, no further action is needed, but it is recommended that you have a look at the improved settings page and save your preferences.


## Privacy Policy
Cryptocurrency Payment Gateway for WooCommerce uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon the user's confirmation. This helps us to troubleshoot problems faster & make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**. We collect the data to ensure a great user experience for all our users.

Integrating Appsero SDK **DOES NOT IMMEDIATELY** start gathering data, **without confirmation from users in any case.**

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).
