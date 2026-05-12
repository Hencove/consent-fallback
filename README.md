# Consent Fallback

A small, CMP-agnostic WordPress plugin that displays a configurable message
inside embed wrappers (HubSpot forms, Greenhouse job boards, etc.) that fail
to populate — typically because cookie consent has been declined, but also
useful for ad blockers, network errors, or third-party outages.

The plugin does not talk to any specific Consent Management Platform. It
just watches the DOM: if a marked wrapper hasn't been populated by the
configured timeout, the fallback message is injected. If the embed
populates later (e.g. the user grants consent without a page reload), the
fallback is removed.

## Disclaimer

This plugin is provided as-is, without warranty of any kind. Hencove is not responsible for how it is used, cannot offer support, and accepts no liability for bugs, data loss, or other issues that may arise from its use. Use at your own risk.

## Install

Drop the plugin folder into `wp-content/plugins/consent-fallback/` (or
clone the repo there) and activate it in **Plugins → Installed Plugins**.

Releases provide zip files for uploading to Plugins panel.

The plugin is self-contained — all JS and CSS are served from the same
origin as the site, so a CMP can't accidentally block them.

## Usage

Wrap each embed in a `.consent-fallback` element. The plugin's JS finds
those wrappers and watches them. Two ways to produce the markup:

### 1. Direct HTML (theme Code module, custom HTML block, etc.)

```html
<div class="consent-fallback" data-fallback-label="form">
  <!-- existing HubSpot or Greenhouse embed code, unchanged -->
  <script>
    ...hubspot snippet...
  </script>
</div>
```

### 2. Shortcode

```
[consent_fallback label="form"]
  <!-- embed code -->
[/consent_fallback]
```

`data-fallback-label` (or the `label` shortcode attribute) is interpolated
into the message via the `{label}` placeholder. Use just the name of the
thing being blocked — e.g. `form`, `job board`, `video` — **not**
`this form`, since the default message template already begins with
"This". If omitted, it defaults to `content`.

### What counts as "populated"?

Any direct child element that is not `<script>`, `<noscript>`, or the
fallback message itself. HubSpot and Greenhouse both render their content
as a direct child element of the embed snippet (form wrapper, iframe, app
container), so the default rule covers them.

## Settings page

**Settings → Consent Fallback** in wp-admin exposes four fields:

| Field                    | Default                                                                                                    |
| ------------------------ | ---------------------------------------------------------------------------------------------------------- |
| Message template         | `This {label} requires Functional cookies to load. You can {settingsLink} and reload the page to view it.` |
| Settings link text       | `manage your cookie preferences`                                                                           |
| Settings link JavaScript | `window.ours_consent.showPreferences();`                                                                   |
| Detection timeout (ms)   | `2500`                                                                                                     |

The message template supports two placeholders:

- `{label}` — replaced with the wrapper's `data-fallback-label`
  (or `content` if missing). Should be a bare noun like `form` or
  `job board`; the default template already supplies the leading "This".
- `{settingsLink}` — replaced with an `<a>` element rendered by the
  plugin. Clicking it runs the configured JavaScript.

### Capabilities

- The settings page itself requires `manage_options` (any Administrator on
  a standard single-site install).
- The **Settings link JavaScript** field additionally requires
  `unfiltered_html`. On default single-site installs Administrators have
  both, so the gate is invisible. On multisite, only Super Admins can edit
  this field; site Administrators see it disabled with an explanatory note.
  Hardened single-site installs that strip `unfiltered_html` from
  Administrators behave the same way.

The capability gate is enforced both on the front end (the textarea is
rendered `disabled`) and server side (sanitization discards a forged POST
and retains the previously-saved value).

## Filter override

The `consent_fallback_config` filter runs after defaults + saved options
and has the final word. Resolution order is:

1. Built-in defaults
2. Saved options from the settings page
3. The `consent_fallback_config` filter

Use this to lock config in code for version-controlled environments:

```php
add_filter( 'consent_fallback_config', function ( $config ) {
    $config['observeTimeoutMs'] = 4000;
    $config['settingsJs']       = 'window.MyCMP.openPreferences();';
    return $config;
} );
```

## Switching CMPs

Most of the time you only need to swap `settingsJs` (and optionally
`settingsLinkText`).

If your CMP doesn't expose a JavaScript hook, point the link at the
relevant page and override the message template instead — for example:

```php
add_filter( 'consent_fallback_config', function ( $config ) {
    $config['messageTemplate']  = 'This {label} requires Functional cookies. {settingsLink}.';
    $config['settingsLinkText'] = 'Open cookie preferences';
    $config['settingsJs']       = 'window.location.href = "/cookie-preferences/";';
    return $config;
} );
```

## Styling

`assets/consent-fallback.css` ships minimal, low-specificity defaults you
can override. Three CSS custom properties are exposed:

```css
.consent-fallback__message {
  --consent-fallback-bg: #fafafa;
  --consent-fallback-border: #ccd0d4;
  --consent-fallback-padding: 1.25em 1.5em;
}
```

Or override the whole element directly:

```css
.consent-fallback__message {
  background: #fff7e0;
  border-color: #d9a300;
}
```

The fallback inherits theme typography (no `font-family` or `font-size`
set) and avoids `!important`.

## Detection trade-off

If an embed loads slower than `observeTimeoutMs`, the fallback shows
briefly and then disappears when the embed populates — a visible flash.
The alternative is a longer timeout that delays the fallback when it
genuinely is blocked. The default of 2500ms is a reasonable balance for
HubSpot and Greenhouse on most networks; bump to 4000–5000 on slow sites
via the settings page or filter.

## Configuration surface (reference)

| Key                | Type    | Default                                  |
| ------------------ | ------- | ---------------------------------------- |
| `messageTemplate`  | string  | see "Settings page" above                |
| `settingsLinkText` | string  | `manage your cookie preferences`         |
| `settingsJs`       | string  | `window.ours_consent.showPreferences();` |
| `observeTimeoutMs` | integer | `2500` (clamped to 500–30000)            |

The resolved config is shipped to the browser as
`window.ConsentFallback.config = {...}` immediately before the plugin's
JS file loads.

## License

GPLv3 or later.
