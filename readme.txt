=== Restrict Content Pro - Authorize.net ===
Author URI: https://ithemes.com
Author: iThemes
Contributors: jthillithemes, ithemes
Tags: Restrict Content Pro, RCP, Authorize.net, payment gateway
Requires at least: 4.5
Tested up to: 5.8.0
Requires PHP: 5.6
Stable tag: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Sell Restrict Content Pro memberships through the Authorize.net payment gateway.

== Description ==

**On October 14th, 2021, all Restrict Content Pro add-ons will be removed from the WordPress plugin repository.**

**This plugin and all other Restrict Content Pro add-ons will remain available to download in your <a href="https://members.ithemes.com/panel/downloads.php">iThemes Member's Panel</a>.**

This plugin is an add-on for [Restrict Content Pro](https://restrictcontentpro.com/). It adds support for processing payments through Authorize.net.

Once activated, this plugin will allow you to enable the Authorize.net payment gateway in Restrict > Settings > Payments.

For more information, see the [documentation](https://docs.restrictcontentpro.com/article/1765-authorize-net).

== Installation ==

1. Go to Plugins > Add New in your WordPress dashboard.
2. Search for "Restrict Content Pro - Authorize.net"
3. Click "Install Now" on the plugin listed in the search results.
4. Click "Activate Plugin" after the plugin is installed.
5. Enable Authorize.net in Restrict > Settings > Payments > Enabled Gateways.
6. Fill out your Authorize.net API keys at the bottom of the payments settings page.
7. Set up a webhook in Authorize.net using the endpoint URL `https://yoursite.com/rcp-authorizenet-listener/` . Replace `yoursite.com` with your own domain name.

For more instructions, see the [documentation](https://docs.restrictcontentpro.com/article/1765-authorize-net).

== Frequently Asked Questions ==

= Which versions of Restrict Content Pro is this add-on compatible with? =
This add-on requires Restrict Content Pro version `3.0.5` or later.

= What payment features are supported? =
All major payment gateway features in RCP are supported, including:
* One-time transactions
* Recurring subscriptions
* Free trials
* Signup fees
* Subscription cancellations
* Update billing card

== Screenshots ==

1. Authorize.net in the enabled gateways list.
2. Authorize.net API key settings.
3. Registering with Authorize.net payment gateway.

== Changelog ==

= 1.0.4 - September 14th 2021 =
* New: Added New Updater

= 1.0.3 - November 6, 2019 =
* Tested with WordPress 5.3.
* Fix: Text fields on settings page missing `type` attribute.

= 1.0.2 - August 22, 2019 =
* New: Revoke membership on Authorize.net `net.authorize.customer.subscription.suspended` webhook.

= 1.0.1 - July 17, 2019 =
* New: Change how pending payments are checked in RCP 3.1+.
* Tweak: Update plugin author name and URL.

= 1.0 =
* Initial Release
