# Gigbuilder Tools WordPress Plugin — Design Spec

## Overview

A modular WordPress plugin that lets CRM managers place Gigbuilder widgets on their WordPress sites. The first widget is **Check Availability** — a native date picker and booking form powered by the Gigbuilder Domino CRM. Future widgets include client/employee login buttons, song list builders, and more.

The plugin talks to a single Domino web agent (`jsontools`) via server-side proxy. WordPress owns all presentation; the CRM owns the data and form definitions.

## Architecture

### System Flow

```
Visitor Browser  →  WordPress (AJAX proxy)  →  Domino Agent (jsontools)
                 ←                           ←
```

- Browser never talks directly to Domino
- WordPress proxies all requests server-side (no CORS needed)
- Visitor IP is captured server-side by WordPress before forwarding
- Base URL stays hidden from the public

### Domino Endpoint

Full URL pattern:

```
{server_url}{db_path}/jsontools?open
```

Example: `https://tpa.gigbuilder.com/cal70.nsf/jsontools?open`

- **Server URL** — configured in WordPress settings (e.g., `https://tpa.gigbuilder.com`)
- **Database Path** — configured in WordPress settings (e.g., `/cal70.nsf` or `/cal/il/dj123.nsf`)
- **Agent** — `jsontools?open` (hardcoded in the plugin)

All requests are POST with the action inside the JSON body.

## API Contract

### Check Availability

**Request:**
```json
{
  "action": "checkAvailability",
  "date": "11/26/2026"
}
```

**Response (available):**
```json
{
  "status": "available",
  "message": "Hurry, last one!",
  "form": {
    "title": "Book This Date",
    "fields": [
      {
        "type": "input",
        "subType": "text",
        "name": "firstName",
        "label": "Your First Name",
        "placeholder": "i.e. Sam",
        "required": true,
        "values": []
      },
      {
        "type": "select",
        "name": "eventType",
        "label": "Event Type",
        "required": false,
        "values": ["Wedding", "School Dance", "Corporate"]
      }
    ]
  }
}
```

**Response (booked):**
```json
{
  "status": "booked",
  "message": "Sorry, that date is already booked."
}
```

### Submit Booking

**Request:**
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

**Response (success):**
```json
{
  "status": "success",
  "message": "Your request has been submitted!"
}
```

### Error Response

All error states return:
```json
{
  "status": "error",
  "message": "Something went wrong. Please try again."
}
```

### Status Values

| Status | Plugin Behavior |
|--------|----------------|
| `available` | Show message + render form from `form` object |
| `booked` | Show message only |
| `success` | Show message only (confirmation) |
| `error` | Show message only (styled as error) |

## WordPress Plugin

### File Structure

```
gigbuilder-tools/
  gigbuilder-tools.php           # Bootstrap, registers shortcodes
  includes/
    class-settings.php           # Admin settings page (server URL, DB path)
    class-api-client.php         # Handles all Domino API calls
    class-form-renderer.php      # Builds HTML forms from JSON schema
    class-shortcode-base.php     # Shared shortcode logic
  widgets/
    availability/
      class-availability.php     # Shortcode registration + AJAX handler
      availability.js            # Date picker, form submit, response handling
      availability.css           # Default styles (theme-overridable)
    login/                       # Future: client/employee login buttons
    songlist/                    # Future: song list builder
  assets/
    css/gigbuilder-common.css    # Shared styles across all widgets
    js/gigbuilder-common.js      # Shared utilities
```

### Settings Page (Settings → Gigbuilder Tools)

Two fields:
- **Server URL** — text input (e.g., `https://tpa.gigbuilder.com`)
- **Database Path** — text input (e.g., `/cal70.nsf`)

### Shortcode

```
[gigbuilder_availability title="Check Our Availability" button_text="Check Date"]
```

Optional attributes for customization. All visual styling inherits from the active WordPress theme.

### AJAX Flow

1. Visitor picks a date (calendar picker or MM/DD/YYYY dropdowns)
2. JS sends POST to `admin-ajax.php` with the date
3. WordPress `class-api-client.php` proxies request to `{server_url}{db_path}/jsontools?open`
4. WordPress injects visitor IP into the payload
5. Domino responds with JSON
6. WordPress passes response back to browser
7. JS checks `status` field and renders accordingly

### Date Input

Two input modes offered to the visitor:
- **Calendar picker** — click to select a date
- **MM/DD/YYYY dropdowns** — three select fields for month, day, year

Single date only (no ranges). Most events are pre-scheduled (school dances, weddings, etc.).

## Form Renderer

Shared component that builds native HTML forms from the CRM's JSON field definitions. Reusable by any widget that needs CRM-defined forms.

### Supported Field Types

| type | subType | Renders |
|------|---------|---------|
| `input` | `text` | Text input |
| `input` | `email` | Email input |
| `input` | `phone` | Tel input |
| `input` | `number` | Number input |
| `select` | — | Dropdown from `values` array |
| `textarea` | — | Multi-line text |
| `radio` | — | Radio buttons from `values` array |
| `checkbox` | — | Checkbox group from `values` array |

### Field Schema

Each field object:
```json
{
  "type": "input",
  "subType": "text",
  "name": "firstName",
  "label": "Your First Name",
  "placeholder": "i.e. Sam",
  "required": true,
  "values": []
}
```

### Rendered HTML

```html
<div class="gigbuilder-field gigbuilder-field--text">
  <label for="gb-firstName">Your First Name <span class="gigbuilder-required">*</span></label>
  <input type="text" id="gb-firstName" name="firstName" placeholder="i.e. Sam" required>
</div>
```

All classes prefixed with `gigbuilder-` to avoid theme collisions. No inline styles — class-based only so WordPress themes can override freely.

## Domino Webhook Agent (jsontools)

A single LotusScript web agent that handles all API actions.

### Routing

Reads the `action` field from the POST body JSON and dispatches to handler functions:
- `checkAvailability` — date lookup, returns status + form if available
- `submitBooking` — creates booking request document in CRM

Future actions (login URLs, song lists, etc.) add new cases to the router.

### LotusScript Considerations

- **POST body chunking** — must reassemble `request_content_000`, `request_content_001`, etc. for large payloads
- **JSON parsing** — use Domino 14 `NotesJSONNavigator` to parse incoming JSON
- **JSON building** — use `NotesJSONObject` for response construction
- **Error handling** — `On Error GoTo` with catch-all returning `{"status": "error", "message": "..."}`
- **Print-based output** — `Print "Content-Type: application/json"` then blank line then body
- **GetItemValue returns Variant** — always index with `(0)` for CGI variables

### Agent Structure

```
Sub Initialize
  ' Read POST body (handle chunking)
  ' Parse JSON with NotesJSONNavigator
  ' Read "action" field
  ' Route to handler:
  '   Case "checkAvailability" → CheckAvailability()
  '   Case "submitBooking"     → SubmitBooking()
  '   Case Else                → error response
  ' Print JSON response
End Sub
```

## Future Widgets

The modular architecture supports adding widgets without touching core code:

- **Login buttons** — `[gigbuilder_login type="client"]` / `[gigbuilder_login type="employee"]` — action: `getLoginUrl`
- **Song list** — `[gigbuilder_songlist]` — action: `getSongList`, `submitSongRequest`
- Additional widgets — new folder under `widgets/`, new action in `jsontools`

All share the settings page, API client, and form renderer.
