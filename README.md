# Japan Post EMS for WooCommerce

**This plugin adds Japan Post EMS shipping rates to your WooCommerce store.** Please refer to their site for more information. [https://www.post.japanpost.jp/int/ems/index_en.html](https://www.post.japanpost.jp/int/ems/index_en.html)

## Features

* **Supports multiple zones:** Calculates shipping costs based on predefined zones (First Zone, Second Zone, etc. Check them all on [https://www.post.japanpost.jp/int/ems/country/all_en.html](https://www.post.japanpost.jp/int/ems/country/all_en.html)).
* **Weight-based pricing:** Calculates shipping costs based on the weight of the items in the cart.
* **Overweight handling:** Displays an error message and disables the shipping method if the package weight exceeds the shipping limits.
* **Easy configuration:** Basic settings can be configured within the WooCommerce Shipping settings.

## Roadmap

* **Suppor for Cool EMS.**
* **Better user notices.**
* **Delivery time estimates.**
* **Fine tuned configurations.**

## Installation

1. **Download:** Download the plugin ZIP file.
2. **Upload:** Upload the ZIP file to your WordPress site and activate the plugin.
3. **Configure:** 
    * Go to **WooCommerce > Settings > Shipping > Japan Post EMS**.
    * Check Enable/Disable to enable or disable it.
    * Change the title if the default doesn't suit your store.
    * **Important:** You need to configure selling and shipping locations in **WooCommerce > Settings > General**.
    * No other configuration needed.
  
* This plugin can also be installed as a mu-plugin.

## Usage

1. **Add products to the cart.**
2. Proceed to checkout.
3. The "Japan Post EMS" shipping method will be displayed as an option if the destination country falls within a defined zone and the package weight is within the allowed limits.
4. Select "Japan Post EMS" and proceed with the checkout process.

## Notes

Prices are denominated in yen, while weights are measured in kilograms.

## Support

For support, please create an issue in the plugin's Github page.

## License

This plugin is released under the GPLv3 or later license.

## Disclaimer

This plugin has no affiliation with Japan Post Group or WooCommerce and is provided "as is" without any warranty. Use at your own risk.
