Coinbase Commerce
=====================

# Drupal module: Commerce Coinbase Commerce Gateway
This module provides a Drupal Commerce payment method to embed the payment services provided by Coibase Commerce.
Coinbase Commerce is a new service that enables merchants to accept multiple cryptocurrencies directly into a user-controlled wallet.
This module allows you to integrate Coinbase Commerce easily on your platform.
Additional information can be found at:
https://commerce.coinbase.com/

## Required dependencies

- Commerce Payment (from [Commerce](http://drupal.org/project/commerce) core)
- Commerce Order (from [Commerce](http://drupal.org/project/commerce) core)

## Installation / Configuration

1. Install the Coinbase Commerce Gateway module by copying the commerce_coinbase_payments to a modules directory `modules/contrib`.
2. In your Drupal site, enable the module in Drupal Extend/List find Coinbase Commerce, click Install button.
3. Add payment gateway at setting page and configure your API keys:
   Commerce -> Configuration -> Payment gateways -> Add payment gateway
4. Log into your Coinbase Commerce Dashboard and go to "Settings" section, copy the Api Key and Webhook Shared Secret from your account and paste them into the corresponding fields at the module's setup page on your Drupal site.
5. Copy the "Webhook subscription url" from your Drupal Commmerce module setup and paste it into the "Webhook Url" field at the "Notifications" section of your Coinbase Commerce Dashboard https://commerce.coinbase.com/dashboard/settings, then save the changes.
