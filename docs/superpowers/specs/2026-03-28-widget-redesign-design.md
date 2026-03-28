# Widget Redesign — Three Selectable Styles

## Context

The availability widget currently renders as a plain HTML `<input type="date">` and a button with minimal styling. It works but looks generic on every theme. The widget is a WordPress plugin used across thousands of sites, so it must inherit theme colors/fonts and not impose its own brand palette.

**Goal:** Redesign the full widget experience (date picker, availability response, booking form, success state) with three selectable layout styles that site admins choose from the settings page. Add configurable text fields so admins can personalize copy.

## Three Widget Styles

### A. Structured Card
- Widget wrapped in a subtle card container (border, slight background, border-radius)
- "CHECK AVAILABILITY" uppercase label at top
- "SELECT DATE" micro-label above the date input
- Date input and button side-by-side (inline)
- Availability response as a banner with colored left border inside the card
- Booking form fields in 2-column grid inside the card
- Success state: centered checkmark circle + confirmation text inside card

### B. Stepped Flow
- Multi-step wizard with numbered circles (1, 2, 3)
- Vertical connector line between steps
- Step 1: "Choose your date" — date input + check button
- Step 2: "Your details" — booking form (revealed after available)
- Step 3: "Confirmation" — success message
- Completed steps show green checkmark replacing number
- Active step is bold with dark number circle
- Upcoming steps are dimmed/grayed out

### C. Minimal Inline
- Heading text (configurable, default: "When's the big day?")
- Subheading text (configurable, default: "Check if we're available for your event")
- Combined input + button in one unified row (search-bar style, shared border-radius)
- Arrow icon on button ("Check Date →")
- Availability response as subtle banner below
- Booking form flows naturally underneath
- Success: centered checkmark + "You're all set!" + date badge

## Theme Inheritance Strategy

All styles use **inherited colors only**:
- Buttons: `inherit` font-family, use theme's text/background contrast
- Inputs: theme background + border colors
- Card backgrounds: subtle `rgba()` overlays that work on any background
- Status colors are the only hardcoded values (green/orange/red for available/booked/error) — these are semantic and universal
- No hardcoded font families, sizes use `em` relative to parent

## Settings Page — Widget Appearance Section

Add a new "Widget Appearance" section to the existing settings page (below the auth section, above the shortcode docs). Settings are saved to the existing `gigbuilder_tools_settings` option.

### Settings Fields

| Setting | Type | Default | Description |
|---------|------|---------|-------------|
| `widget_style` | Radio/select | `card` | Choose layout: Card, Stepped, or Minimal |
| `date_input` | Radio/select | `calendar` | Calendar picker or Month/Day/Year dropdowns |
| `heading_text` | Text input | (varies by style) | Main heading above the widget |
| `subheading_text` | Text input | (blank) | Subheading text (most relevant for Minimal style) |
| `button_text` | Text input | `Check Date` | Text on the check availability button |
| `submit_text` | Text input | `Submit Request` | Text on the booking form submit button |

**Style-specific defaults for heading_text:**
- Card: "Check Availability"
- Stepped: "Check Availability"
- Minimal: "When's the big day?"

### Shortcode Changes

Merge `[gigbuilder_availability]` and `[gigbuilder_datepicker]` into a single `[gigbuilder_availability]` shortcode. The `date_input` setting (or shortcode attribute) controls whether calendar or dropdowns are used. Keep `[gigbuilder_datepicker]` as a deprecated alias.

Shortcode attributes can override any setting:
```
[gigbuilder_availability style="stepped" heading="Pick Your Date" button_text="Check"]
```

## HTML Structure Changes

### Common wrapper (all styles)
```html
<div class="gigbuilder-widget gigbuilder-availability gigbuilder-style-{card|stepped|minimal}"
     id="gigbuilder-availability">
  <!-- style-specific markup -->
</div>
```

The `gigbuilder-style-*` class drives all layout differences via CSS. The JS behavior remains the same across styles — only the DOM structure and CSS change.

### Style A: Card
```html
<div class="gigbuilder-card">
  <div class="gigbuilder-card-label">Check Availability</div>
  <div class="gigbuilder-date-picker">
    <div class="gigbuilder-field-label">Select Date</div>
    <div class="gigbuilder-date-row">
      <!-- calendar or dropdowns -->
      <button class="gigbuilder-button gigbuilder-check-btn">Check Date</button>
    </div>
  </div>
  <div class="gigbuilder-message"></div>
  <div class="gigbuilder-selected-date"></div>
  <div class="gigbuilder-validation-errors"></div>
  <div class="gigbuilder-form-container"></div>
  <div class="gigbuilder-loading"></div>
</div>
```

### Style B: Stepped
```html
<div class="gigbuilder-steps">
  <div class="gigbuilder-step gigbuilder-step--active" data-step="1">
    <div class="gigbuilder-step-indicator">
      <div class="gigbuilder-step-number">1</div>
      <div class="gigbuilder-step-line"></div>
    </div>
    <div class="gigbuilder-step-content">
      <div class="gigbuilder-step-title">Choose your date</div>
      <div class="gigbuilder-date-picker"><!-- ... --></div>
    </div>
  </div>
  <div class="gigbuilder-step gigbuilder-step--upcoming" data-step="2">
    <div class="gigbuilder-step-indicator">
      <div class="gigbuilder-step-number">2</div>
      <div class="gigbuilder-step-line"></div>
    </div>
    <div class="gigbuilder-step-content">
      <div class="gigbuilder-step-title">Your details</div>
      <div class="gigbuilder-form-container"></div>
    </div>
  </div>
  <div class="gigbuilder-step gigbuilder-step--upcoming" data-step="3">
    <div class="gigbuilder-step-indicator">
      <div class="gigbuilder-step-number">3</div>
    </div>
    <div class="gigbuilder-step-content">
      <div class="gigbuilder-step-title">Confirmation</div>
    </div>
  </div>
</div>
```

### Style C: Minimal
```html
<div class="gigbuilder-minimal">
  <h3 class="gigbuilder-heading">When's the big day?</h3>
  <p class="gigbuilder-subheading">Check if we're available for your event</p>
  <div class="gigbuilder-date-picker">
    <div class="gigbuilder-search-row">
      <!-- calendar or dropdowns -->
      <button class="gigbuilder-button gigbuilder-check-btn">Check Date <span>&rarr;</span></button>
    </div>
  </div>
  <div class="gigbuilder-message"></div>
  <div class="gigbuilder-selected-date"></div>
  <div class="gigbuilder-validation-errors"></div>
  <div class="gigbuilder-form-container"></div>
  <div class="gigbuilder-loading"></div>
</div>
```

## CSS Architecture

- `gigbuilder-common.css` — shared resets, form field styling, message colors, grid layout, responsive breakpoints (already exists, update as needed)
- `availability.css` — all three style layouts keyed off `.gigbuilder-style-card`, `.gigbuilder-style-stepped`, `.gigbuilder-style-minimal`
- No separate CSS files per style — single file with class-scoped rules keeps things simple

### Key CSS principles:
- All spacing in `em` units
- Border-radius: `0.5em` for containers, `0.375em` for inputs
- Use `currentColor` and `inherit` wherever possible
- Card backgrounds: `rgba(128,128,128,0.06)` — works on both light and dark themes
- Input backgrounds: `rgba(128,128,128,0.08)` with `1px solid rgba(128,128,128,0.2)` border
- Status colors remain hardcoded (green `#4caf50`, orange `#ff9800`, red `#f44336`) as these are semantic

## JavaScript Changes

The JS logic in `availability.js` stays mostly the same. Changes:
1. Remove reliance on specific element IDs for mode detection (already fixed)
2. For Stepped style: add step transition logic (add/remove `--active`, `--completed`, `--upcoming` classes)
3. For success state: respect style when rendering final HTML
4. Detect style from widget class (`gigbuilder-style-*`)

## Files to Modify

| File | Changes |
|------|---------|
| `includes/class-settings.php` | Add Widget Appearance settings section, save/load new fields |
| `widgets/availability/class-availability.php` | Merge shortcodes, render style-specific HTML, read settings |
| `widgets/availability/availability.js` | Step transitions for Stepped style, style-aware success rendering |
| `widgets/availability/availability.css` | Three style layouts, theme-neutral colors |
| `assets/css/gigbuilder-common.css` | Update shared form styling for theme inheritance |

## Verification

1. Activate plugin on a light theme (e.g., Twenty Twenty-Five) — verify all 3 styles look correct
2. Switch to a dark theme — verify contrast and readability
3. Test each style with both calendar and dropdown date inputs
4. Test full flow: select date → check availability → fill form → submit → success
5. Test settings page: change style, verify front-end updates
6. Test shortcode attribute overrides: `[gigbuilder_availability style="minimal" heading="Book Us"]`
7. Test `[gigbuilder_datepicker]` still works as deprecated alias
8. Deploy to clearwater-dj.com via SSH and verify on live site
