=== Gigbuilder Tools ===
Contributors: gigbuilder
Tags: crm, booking, availability, events, entertainment
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Widgets and tools for connecting WordPress sites to Gigbuilder CRM.

== Description ==

Gigbuilder Tools lets CRM managers place interactive widgets on their WordPress sites. Connect your Gigbuilder CRM account and give your website visitors the ability to check date availability, submit booking requests, access the Client Center, and request songs.

**Available Widgets:**

* **Check Availability (Calendar)** — Full date checker with calendar picker and booking form
* **Check Availability (Dropdowns)** — Compact date checker with month/day/year dropdowns
* **Client Center** — Button to open the Gigbuilder Client Center
* **Guest Requests** — Button to open the Guest Music Request page

**Features:**

* CRM-defined booking forms rendered natively in your WordPress theme
* Two-column responsive grid layout (single column on mobile)
* Multiple field types: text, email, phone, select, radio, checkbox, time, duration, location, and more
* Section grouping with card-style containers
* Client-side validation with missing field list
* Session-based duplicate submission prevention
* Secure server-side proxy — visitor browsers never connect directly to your CRM

== Installation ==

1. Upload the `gigbuilder-tools` directory to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → Gigbuilder Tools
4. Enter your Gigbuilder CRM username and password, then click Authenticate
5. Add shortcodes to any page or post

**Shortcodes:**

* `[gigbuilder_availability]` — Check Availability with calendar
* `[gigbuilder_datepicker]` — Check Availability with dropdowns
* `[gigbuilder_clientcenter]` — Client Center button
* `[gigbuilder_guestrequests]` — Guest Requests button

== Frequently Asked Questions ==

= Do I need a Gigbuilder CRM account? =

Yes. This plugin connects to your existing Gigbuilder CRM. Visit [gigbuilder.com](https://gigbuilder.com) to learn more.

= Does this work with any WordPress theme? =

Yes. The widgets inherit your theme's styling and use prefixed CSS classes to avoid conflicts.

= Is visitor data secure? =

Yes. WordPress acts as a proxy between the visitor and your CRM. The CRM server URL is never exposed to the public, and all requests are authenticated.

== Changelog ==

= 1.5.0 =
* Initial public release
* Check Availability widget (calendar and dropdown variants)
* Client Center and Guest Requests button widgets
* CRM authentication via settings page
* JSON-schema form renderer with 10+ field types
* Responsive two-column grid layout
* Client-side form validation
* Session-based submission locking

== Upgrade Notice ==

= 1.5.0 =
Initial release.
