# Gigbuilder Tools — WordPress Plugin

Widgets and tools for connecting WordPress sites to [Gigbuilder CRM](https://gigbuilder.com), an HCL Domino-based CRM for entertainment professionals.

## What It Does

Gigbuilder Tools lets CRM managers place interactive widgets on their WordPress sites. WordPress acts as a proxy between the visitor's browser and the backend services. The browser never talks to the CRM or AI agent directly — no CORS configuration needed, and server URLs stay hidden from the public.

## Widgets

### Check Availability

A date picker and dynamic booking form powered by the CRM.

**Shortcodes:**
```
[gigbuilder_availability]          Calendar date picker
[gigbuilder_datepicker]            Month/Day/Year dropdown picker
```

**Flow:**
1. Visitor picks a date
2. WordPress checks availability against the CRM
3. If available — CRM-defined booking form appears
4. Visitor fills out and submits — booking request created in the CRM

**Features:**
- Three layout styles: Structured Card, Stepped Flow, Minimal Inline
- CRM defines the form fields via JSON — WordPress renders them natively
- 2-column responsive grid (collapses to single column under 800px)
- Field types: text, email, phone, number, textarea, select, radio, checkbox, time, duration, location
- Section grouping with card-style containers and descriptions
- Default values, required field validation with missing field list
- Session-based duplicate submission prevention

### AI Chat Widget

An AI-powered chat assistant that connects visitors to your business through an n8n agent backed by Gigbuilder CRM via MCP.

**Shortcodes:**
```
[gigbuilder_chat]                  Inline chat embedded in page
[gigbuilder_chat_popup]            Floating popup bubble (bottom-right corner)
```

**Features:**
- Dark glass morphism popup design with smooth open/close animations
- Theme-neutral inline mode that inherits your site's styling
- Speech recognition — click the mic, speak, auto-sends after silence detection
- Text-to-speech — bot responses are spoken when mic mode is active
- Typing indicator with animated dots while AI processes
- Status messages every 10 seconds during long AI responses
- 90-second request timeout with graceful error handling
- Server-side proxy — n8n webhook URL and CRM token never exposed to visitors
- Independent instances — inline and popup work simultaneously on the same page
- Persistent visitor session via sessionStorage
- Responsive — popup goes full-screen on mobile

### Utility Shortcodes

```
[gigbuilder_clientcenter]          Button linking to the Client Center
[gigbuilder_guestrequests]         Button linking to Guest Music Requests
```

## Installation

### Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- A Gigbuilder CRM account
- HTTPS required for speech recognition features

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
2. Enter your CRM **username** and **password**, then click **Authenticate**
3. The plugin connects to Gigbuilder CRM, stores your credentials, and auto-populates the database path, MCP access token, and company name

### Settings

**Widget Appearance** (availability widget):
- Widget Style — Card, Stepped, or Minimal layout
- Heading, subheading, button text, and submit button text
- Shortcode attribute overrides for per-page customization

**Chat Widget:**
- Company Name — displayed in the chat header
- Avatar Emoji — clickable picker with 64 emoji options
- Launcher Text — text on the popup bubble (e.g., "Click to Chat")
- Welcome Message — initial bot message when chat opens (HTML supported)
- MCP Access Token — CRM-issued token for the AI agent (auto-populated on login, editable)

### Add to a Page

Edit any page or post and add a shortcode:

```
[gigbuilder_availability]
[gigbuilder_chat_popup]
```

Widgets inherit your theme's styling. All CSS classes are prefixed with `gigbuilder-` or `gb-` to avoid conflicts.

## Architecture

```
Browser → WordPress AJAX → Backend Service → Response back through same chain
```

- **Availability widget** proxies to the Domino CRM (`jsontools` agent)
- **Chat widget** proxies to an n8n webhook which runs an AI agent connected to the CRM via MCP
- Credentials and tokens are stored server-side in `wp_options` — never exposed to visitors

## File Structure

```
gigbuilder-tools/
  gigbuilder-tools.php              Plugin bootstrap
  uninstall.php                     Cleanup on plugin deletion
  readme.txt                        WordPress.org metadata
  includes/
    class-settings.php              Admin settings page & CRM auth
    class-api-client.php            Domino API proxy
    class-form-renderer.php         JSON schema → HTML form renderer
  widgets/
    availability/
      class-availability.php        Shortcode + AJAX handlers
      availability.js               Date picker + form handling
      availability.css              Widget styles (3 variants)
    chat/
      class-chat.php                Shortcode + AJAX proxy to n8n
      chat.js                       Chat UI, speech, typing indicators
      chat.css                      Glass morphism popup + inline styles
  assets/
    css/gigbuilder-common.css       Shared styles (all widgets)
    js/gigbuilder-common.js         Shared JS utilities
```

## Domino Setup

The availability widget communicates with a Domino web agent called `jsontools`. The full endpoint URL is:

```
{server_url}{db_path}/jsontools?open
```

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

## n8n Chat Agent

The chat widget proxies to an n8n webhook. The payload sent to n8n:

```json
{
  "action": "sendMessage",
  "sessionId": "visitor-session-id",
  "chatInput": "user message",
  "voiceEnabled": false,
  "metadata": {
    "test": false,
    "path": "cal70.nsf",
    "token": "crm-issued-token",
    "url": "https://example.com/page",
    "domain": "https://example.com",
    "html": true,
    "widget": true
  }
}
```

The n8n workflow handles AI processing, CRM lookups via MCP, and returns the response. The `path` and `token` are derived from the CRM authentication — `path` from the database path, `token` from the MCP access token stored in settings.

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
