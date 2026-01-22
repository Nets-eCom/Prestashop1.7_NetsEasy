# NETS A/S - Prestashop 1.7 to 9 Payment Module
============================================

| Module         | Nets Easy Payment Module for Prestashop 1.7 to 9                                                |
|----------------|-------------------------------------------------------------------------------------------------|
| Shop Version   | `1.7 to 9.0`                                                                                    |
| Plugin Version | `1.1.14`                                                                                        |
| PHP Version    | `8.1+`                                                                                          |
| Guide          | https://developer.nexigroup.com/nexi-checkout/en-EU/docs/checkout-for-prestashop-prestashop/    |
| Github         | https://github.com/Nets-eCom/Prestashop1.7_NetsEasy                                             |

## CHANGELOG

### Version 1.1.14 - Released 2025-08-01
* Update: compatibility with php 8.4

### Version 1.1.13 - Released 2025-07-16
* Update: Compatibility with Prestashop9

### Version 1.1.12 - Released 2025-06-27
* Fixed: Danish language on checkout

### Version 1.1.11 - Released 2024-10-23
* Fixed: Remove webhook URL in plugin settings

### Version 1.1.10 - Released 2024-06-06
* Fix: grossTotalAmount and netTotalAmount taken from item total and total_wt to prevent rounding problems
* Fix: remove unused reporting code

### Version 1.1.9 - Released 2024-04-30
* SecurityFix: check cart secure_key in return.php
* Update: improve error message displayed during checkout
* Fix: error in admin panel for Swish payments

### Version 1.1.8 - Released 2024-03-06
* SecurityFix: Added generic font-family.
* SecurityFix: Added pSQL method to SQL queries.
* SecurityFix: URLs are no longer constructed with user-controlled data.
* Fix: Pixel values are now unified and refactored.
* Fix: Removed unused code.

### Version 1.1.7 - Released 2024-01-29
* Fixed : gift wrapping not being added to the total amount

### Version 1.1.6 - Released 2023-06-06
* New : New setting introduce Payment Split for each payment method as separate option.

### Version 1.1.5 - Released 2023-03-17
* Fixed : Compatibilty check plugin with prestashop 8 version.

### Version 1.1.4 - Released 2022-11-11
* Fixed : Improved Nets plugin latest version notification on configure page.
* Fixed : Phone number issue on checkout.


### Version 1.1.3 - Released 2022-09-08
* New : Custom Payment Name display on checkout page.
* New : Display payment name with payment method in Admin Order List.
* Fixed : Improved A2A Payment Methods compatibility.
* Fixed : Improved Nets plugin latest version notification on configure page.

### Version 1.1.2 - Released 2022-06-27
* Fixed : Admin Order Nets Easy details cancel button issue has been fixed.
* Fixed : Payment Nets Checkout issue for norwegian language locale has been fixed.

### Version 1.1.1 - Released 2022-06-03
* New : A new API call is introduced allowing Nets to receive information about the plugin version used by the PrestaShop store and return potential plugin upgrade instructions.

### Version 1.1.0 - Released 2022-03-24
* Fixed : Bugfixes in checkout section(Not getting country iso_code_3).
		  Bugfixes for not getting consumer details in Nets portal.
		  Bugfixes for not displaying live keys in admin configuration.
* Docs: Updated license, changelog and readme files.

### Version 1.0.0 - Released 2022-01-28
* New : Nets Easy plugin release with hosted and embedded payment page support.
