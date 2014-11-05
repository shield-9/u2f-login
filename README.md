# U2F Login
* **Contributors**: extendwings
* **Donate link**: http://www.extendwings.com/donate/
* **Tags**: FIDO U2F, U2F, security, login, yubikey
* **Requires at least**: 4.0
* **Tested up to**: 4.0
* **Stable tag**: (none)
* **License**: AGPLv3 or later
* **License URI**: http://www.gnu.org/licenses/agpl.txt

*Make WordPress login secure with U2F (Universal Second Factor) protocol*

## Description

You may use strong password, security plugin, and etc to protect your WordPress from attacks. But once your password has leaked out, you have no choice but to change it. (New one is stronger and *longer*)

With this plugin, you can use WordPress's login feature much safer even if your password is "1234"!

### Notice
* **Important**: To use this plugin, check following.
	1. Using PHP 5.5 or later
	2. Using WordPress 4.0 or later
	3. Owns FIDO U2F device (We recommend [FIDO U2F SECURITY KEY](https://www.yubico.com/products/yubikey-hardware/fido-u2f-security-key/) to get started.)

### License
* Copyright (c) 2012-2014 [Daisuke Takahashi(Extend Wings)](http://www.extendwings.com/)
* Portions (c) 2010-2012 Web Online.
* Unless otherwise stated, all files in this repo is licensed under *GNU AFFERO GENERAL PUBLIC LICENSE, Version 3*. See *LICENSE* file.

## Installation

1. Upload the `u2f` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress

## Frequently Asked Questions

### This plugin is broken! Thanks for nothing!
First of all, we supports PHP 5.5+, MySQL 5.5+, WordPress 4.0+. Old software(vulnerable!) is not supported.
If you're in supported environment, please create [pull request](https://github.com/shield-9/u2f-login/compare/) or [issue](https://github.com/shield-9/u2f-login/issues/new).

## Screenshots

## Changelog

### 0.1.0
* Initial Beta Release

## Upgrade Notice

### 0.1.0
* None
