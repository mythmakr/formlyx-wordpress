=== Formlyx Forms ===
Contributors: razorsoftinc
Tags: forms
Requires at least: 6.7
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WordPress site to Formlyx and embed published forms.

== Description ==

Formlyx is a hosted form service from RazorSoft, Inc. Forms and submissions stay at [formlyx.com](https://www.formlyx.com). This plugin connects a site to that account and embeds published forms.

Open Settings → Formlyx and choose Connect to Formlyx. You can sign in or create a Formlyx account on Formlyx's site, including email verification, and then approve access. WordPress does not ask for an API key or your Formlyx password.

The site needs HTTPS. HTTP works only on localhost. The connection can be checked, reconnected, or disconnected from the same settings page. Disconnect removes the connection stored in WordPress and asks Formlyx to revoke it. It does not delete hosted forms, submissions, or the Formlyx account.

In the block editor, insert the Formlyx Form block and choose a published form, or enter its form ID. A public form can be embedded by ID without connecting the site. The form picker needs the connection.

The shortcode is `[formlyx id="FORM_ID"]`. An optional theme attribute accepts `auto`, `light`, `dark`, `inherit`, `unstyled`, or `match`. `match` is Match WordPress Theme. It styles the hosted form from this site's active theme and does not change the appearance saved in Formlyx.

A page can contain more than one form. Published pages load `https://www.formlyx.com/embed.js` once. That script displays the form and sends the submission to Formlyx. It does not receive this site's connection tokens.

The editor shows the selected form's name and ID. The live form appears on the published page. The form picker lists at most 100 published forms. An administrator can still enter a public form ID that is not in that list.

== External services ==

This plugin connects to Formlyx, operated by RazorSoft, Inc.

* `https://api.formlyx.com` is used only on the server, and only after an administrator connects the site. WordPress sends the OAuth authorization code, the refresh token, and the client id to obtain and renew access. It sends the access token to read the workspace name and the list of forms. The response is limited to 1 MB. Tokens stay in WordPress options and are not sent to visitors.
* `https://www.formlyx.com/embed.js` is loaded by a visitor's browser when a page contains a Formlyx form. The script is served by Formlyx and is not bundled with this plugin, because Formlyx renders the form and receives the submission. The script sends the form entry to Formlyx. It does not receive this site's OAuth tokens.
* Connecting the site sends the administrator's browser to `https://api.formlyx.com` to sign in or create an account and approve access. The site must use HTTPS, except for localhost.

Terms: https://www.formlyx.com/terms

Privacy policy: https://www.formlyx.com/privacy

Settings → Privacy includes suggested text for this site's privacy policy. It covers the stored connection tokens and the information a visitor submits through an embedded form.

== Installation ==

1. Upload the `formlyx` folder to `/wp-content/plugins/`, or install the zip through Plugins → Add New.
2. Activate Formlyx Forms on the Plugins screen.
3. Open Settings → Formlyx and connect your account.
4. Edit a page, insert the Formlyx Form block, and select a form. Or add `[formlyx id="FORM_ID"]`.

== Frequently Asked Questions ==

= Is Formlyx part of WordPress? =

No. Formlyx is an external hosted service at https://www.formlyx.com. After you connect, WordPress calls https://api.formlyx.com on the server.

= Where are form entries stored? =

Formlyx stores forms and submissions in the hosted service. This plugin does not create WordPress posts for them. Visitors submit the embedded form directly to Formlyx.

= Does embedding require a connection? =

The form picker does. A published form that Formlyx allows the public to embed can also be placed with its form ID in the block or the shortcode, without connecting the site.

= Which theme values work? =

`auto`, `light`, `dark`, `inherit`, `unstyled`, and `match`. The default is `auto`. `match` applies the active WordPress theme to the hosted form. The form's saved Formlyx appearance stays in place for the other values.

= Why does the editor not show the live form? =

The published page loads Formlyx's embed script, which finds each form when the page loads. The editor shows the form name and ID instead, and the live form appears on the published page.

= What does disconnect do? =

Disconnect deletes the access token and refresh token stored by this site and asks Formlyx to revoke that access. Your Formlyx account, forms, and submissions remain.

= What is deleted on uninstall? =

Uninstall deletes the Formlyx connection, the OAuth client record, unfinished connection attempts, and temporary cached form metadata. It does not delete WordPress posts or pages, hosted Formlyx forms, Formlyx submissions, or the Formlyx account.

= Who is the WordPress.org contributor? =

The WordPress.org contributor is razorsoftinc.

== Changelog ==

= 1.0.0 =
* Connect, reconnect, and disconnect a Formlyx account from Settings → Formlyx.
* Embed a published form with the Formlyx Form block or the `[formlyx]` shortcode.
* Match WordPress Theme styles that hosted form from the active theme. PHP 8.1 or newer is required.

== Upgrade Notice ==

= 1.0.0 =
Connect a Formlyx account and embed published forms.
