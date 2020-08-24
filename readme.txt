=== WP Webhooks - Contact Form 7 Integration ===
Author URI: https://ironikus.com/
Plugin URI: https://ironikus.com/downloads/contact-form-7-webhook-integration/
Contributors: ironikus
Donate link: https://paypal.me/ironikus
Tags: contact form 7, webhooks, automation, ironikus, contact, email form WordPress, email form, zapier, api, wp webhooks
Requires at least: 4.7
Tested up to: 5.5
Stable Tag: 1.1.2
License: GNU Version 3 or Any Later Version

A WP Webhooks extension to integrate Contact Form 7

== Description ==

This plugin extends the possibilities of WP Webhooks by integrating Contact Form 7 with webhook functionality.
For a full list of the features, please check down below:

= Features =

* Submit Contact Form 7 forms to webhooks
* Send all forms by default to webhooks
* Deactivate sending the contact form email for one, multiple or all forms
* Support for special HTML tags [https://contactform7.com/special-mail-tags/](https://contactform7.com/special-mail-tags/)
* Trigger webhook only for logged in or non logged in users
* Test Webhooks right out of the box
* Payload customization - Alows you to minimize the data that is send over to the opposite webhook

= For devs =

Feel free to message us in case you want special features - We love to help!

== Installation ==

1. Upload the zip file to your WordPress site (Plugins > Add New > Upload)
2. Activate the plugin
3. Go to Settings > WP Webhooks Pro -> Settings
4. Scroll down, activate the specified trigger and click Save
5. Go to "Send Data" and start implementing the webhook


== Changelog ==

= 1.1.2: August 24, 2020 =
* Tweak: Support for Contact Form 7 5.2.1 (wpcf7_special_mail_tags receives the mail_tag argument)
* Tweak: Renewed description for the Send Data on Contact Form 7 Submits
* Fix: The webhook trigger URL settings only showed five forms instead of all

= 1.1.1: February 18, 2020 =
* Fix: Multiselect fields return null instead of the actual value

= 1.1.0: January 31, 2020 =
* Feature: Support for Special Mail Tags: https://contactform7.com/special-mail-tags/
* Feature: Payload Customization - Alows you to minimize the data that is send over to the opposite webhook
* Feature: Form Data Key Customization - Customize the key of the data that is send over from your form
* Fix: Strip unsanitized slashes away

= 1.0.0: May 20, 2019 =
* Birthday of WPWH - Contact Form 7
