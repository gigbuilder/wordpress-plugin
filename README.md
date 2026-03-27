# Gigbuilder Tools — WordPress Plugin

Widgets and tools for connecting WordPress sites to [Gigbuilder CRM](https://gigbuilder.com), an HCL Domino-based CRM for entertainment professionals.

## What It Does

Gigbuilder Tools lets CRM managers place interactive widgets on their WordPress sites. The first widget — **Check Availability** — allows website visitors to pick a date, check if it's available, and fill out a booking request form. All data flows back to the Gigbuilder CRM.

WordPress acts as a proxy between the visitor's browser and the Domino server. The browser never talks to Domino directly — no CORS configuration needed, and the CRM server URL stays hidden from the public.

## Current Widgets

### Check Availability

A date picker and dynamic booking form powered by the CRM.

**Shortcode:**
```
[gigbuilder_availability]
```

**Flow:**
1. Visitor picks a date (calendar or month/day/year dropdowns)
2. WordPress checks availability against the CRM
3. If available — CRM-defined booking form appears
4. Visitor fills out and submits — booking request created in the CRM

**Features:**
- CRM defines the form fields via JSON — WordPress renders them natively
- 2-column responsive grid (collapses to single column under 800px)
- Field types: text, email, phone, number, textarea, select, radio, checkbox, time, duration, location
- Section grouping with card-style containers and descriptions
- `Label|value` format for select/radio/checkbox options (e.g., `Arizona|AZ`)
- Default values, required field validation with missing field list
- HTML supported in status messages

### Future Widgets

- Client/Employee login buttons
- Song list builder
- Additional tools as needed

## Installation

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- A Gigbuilder CRM account with a Domino database

### Install via ZIP Upload

1. Download the latest release ZIP from [GitHub Releases](https://github.com/gigbuilder/wordpress-plugin/releases)
2. In WordPress admin, go to **Plugins → Add New Plugin → Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Click **Activate**

### Install via FTP/SSH

1. Clone or download this repository
2. Copy the `gigbuilder-tools/` directory to `wp-content/plugins/` on your WordPress server
3. In WordPress admin, go to **Plugins → Installed Plugins**
4. Find "Gigbuilder Tools" and click **Activate**

### Configure

1. Go to **Settings → Gigbuilder Tools**
2. Enter your **Server URL** (e.g., `https://tpa.gigbuilder.com`)
3. Enter your **Database Path** (e.g., `/cal70.nsf` or `/cal/il/dj123.nsf`)
4. Click **Save Changes**

### Add to a Page

Edit any page or post and add the shortcode:

```
[gigbuilder_availability]
```

The widget inherits your theme's styling. All CSS classes are prefixed with `gigbuilder-` to avoid conflicts.

## Domino Setup

The plugin communicates with a single Domino web agent called `jsontools`. The full endpoint URL is:

```
{server_url}{db_path}/jsontools?open
```

### Required Views

| View Name | Key | Purpose |
|-----------|-----|---------|
| `CalendarByDate` | Date string (MM/DD/YYYY) | Check if a date is booked |
| `ConfigByKey` | Config key string | Read configurable messages |
| `BookingFormFields` | Sorted by display order | Define booking form fields |

### Required Config Documents

| Key | Purpose | Default |
|-----|---------|---------|
| `AvailableMessage` | Message when date is available | "Great news! This date is available." |
| `BookedMessage` | Message when date is booked | "Sorry, that date is already booked." |
| `BookingFormTitle` | Form heading | "Book This Date" |
| `BookingSuccessMessage` | Confirmation after submit | "Your request has been submitted!" |

### API Contract

All requests are POST with JSON body to `jsontools?open`.

**Check Availability:**
```json
{"action": "checkAvailability", "date": "11/26/2026"}
```

**Submit Booking:**
```json
{
  "action": "submitBooking",
  "date": "11/26/2026",
  "ip": "98.143.88.2",
  "answers": [
    {"name": "firstName", "value": "Sam"},
    {"name": "eventType", "value": "Wedding"}
  ]
}
```

**Response statuses:** `available`, `booked`, `success`, `error` — each includes a `message` field (HTML supported).

## File Structure

```
gigbuilder-tools/
  gigbuilder-tools.php              Plugin bootstrap
  includes/
    class-settings.php              Admin settings page
    class-api-client.php            Domino API proxy
    class-form-renderer.php         JSON schema → HTML form renderer
  widgets/
    availability/
      class-availability.php        Shortcode + AJAX handlers
      availability.js               Date picker + form handling
      availability.css              Widget styles
  assets/
    css/gigbuilder-common.css       Shared styles (all widgets)
    js/gigbuilder-common.js         Shared JS utilities
  domino/
    jsontools.lss                   LotusScript agent source (reference)
```

## Form Field JSON Schema

The CRM defines form fields as a JSON array. Each field object:

```json
{
  "type": "input",
  "subType": "text",
  "name": "firstName",
  "label": "Your First Name",
  "placeholder": "i.e. Sam",
  "required": true,
  "value": "",
  "columns": 1,
  "values": []
}
```

### Supported Types

| Type | Description | Notes |
|------|-------------|-------|
| `input` | Text input | subType: text, email, phone, number |
| `textarea` | Multi-line text | 5-line height, respects column span |
| `select` | Dropdown | values use `Label\|value` format |
| `radio` | Radio buttons | values use `Label\|value` format |
| `checkbox` | Checkbox group | values use `Label\|value` format |
| `time` | Time picker | 3 dropdowns: hour (1-12), minute (5-min), AM/PM |
| `duration` | Duration picker | 2 dropdowns: hours (1-24), minutes (5-min) |
| `location` | Location select | Shows text input for values `1` (Private Residence) or `999` (Not Found) |
| `section` | Section heading | Groups fields in a card container. Supports `description` |

## License

GPL v2 or later
