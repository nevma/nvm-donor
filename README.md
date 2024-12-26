# WooCommerce Price History Tracker by Nevma

A lightweight WooCommerce plugin to track and manage price changes for products over time. This plugin records regular and sale prices, provides a 100-day price history, and tracks the minimum price in the last 30 days.

## Features

- Tracks price changes for both simple and variable products.
- Records a 100-day history of product prices.
- Maintains the minimum sale price over the last 30 days.
- Provides price history and minimum price data for easy retrieval.

## Installation

1. Upload the plugin files to the `/wp-content/plugins/nvm-price-history-tracker` directory or install it via the WordPress plugin installer.
2. Activate the plugin through the "Plugins" menu in WordPress.

## Usage

### Price History Tracking

The plugin automatically tracks price changes whenever a WooCommerce product is updated. Price history is stored in custom metadata:

- `_nvm_price_history`: Contains the history of regular and sale prices.
- `_nvm_min_price_30`: Stores the minimum sale price for the last 30 days.

### Retrieving Price Data

You can use the provided functions to retrieve price history and minimum prices programmatically:

1. **Get minimun 30 day price:**
   ```php
   $product->get_price_min_30();
   ```
2. **Retrieve History of price:**
   ```php
   $product->get_history_price();
   ```
