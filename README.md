# payscrypt-woocommerce

Woocommerce payment plugin for the safest cryptocurrency payment gateway **Payscrypt**

## Why Payscrypt is the Safest Cryptocurrency Payment Gateway

Because Payscrypt only needs the public key. [Check it out](https://payscrypt.com)

## Installation

1. Download by clicking `Clone or download` and click `Download zip`.
2. Go to your Wordpress administration panel > `Plugins` > `Add New` and click `Upload Plugin` to upload the zip.
3. Activate

## Initial Setup

1. Create a Payscript account and login on [payscrypt.com](https://payscrypt.com)
2. Create a wallet and add an asset, you will need your public key at this step. (for more info about key choice, see below)
3. Create an API token in `Settings`.
4. Go to dashboard, hover over the name of your wallet to get to pg-wallet-id.
5. Now go to Woocommerce settings > `Payments` > `Payscrypt` and click `Manage`, fill in the pg-wallet-id, the API token.
6. Done.

## Crypto Price for Products

You can use MultiCurrency plugin for Woocommerce or the plugin of your choice. As long as the currency in Woocommerce matches the Payscrypt asset it should work just fine.

## How to Get A Public Key

TBD

## License

Apache
