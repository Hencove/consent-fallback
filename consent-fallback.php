<?php
/**
 * Plugin Name:       Consent Fallback
 * Plugin URI:        https://github.com/hencove/consent-alert
 * Description:       Shows a configurable fallback message inside embed wrappers (HubSpot forms, Greenhouse boards, etc.) that fail to populate because of cookie/consent gating, network issues, or ad blockers.
 * Version:           1.0.3
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Hencove
 * License:           GPLv3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       consent-fallback
 * Domain Path:       /languages
 *
 * @package ConsentFallback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CONSENT_FALLBACK_VERSION', '1.0.3' );
define( 'CONSENT_FALLBACK_FILE', __FILE__ );
define( 'CONSENT_FALLBACK_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONSENT_FALLBACK_URL', plugin_dir_url( __FILE__ ) );
define( 'CONSENT_FALLBACK_OPTION_KEY', 'consent_fallback_options' );

require_once CONSENT_FALLBACK_DIR . 'includes/settings-page.php';

/**
 * Built-in default config values. Acts as the floor; saved options layer on
 * top, and the consent_fallback_config filter has the final word.
 *
 * @return array<string, mixed>
 */
function consent_fallback_defaults() {
	return array(
		'messageTemplate'  => 'This {label} requires Functional cookies to load. You can {settingsLink} and reload the page to view it.',
		'settingsLinkText' => 'manage your cookie preferences',
		'settingsJs'       => 'window.ours_consent.showPreferences();',
		'observeTimeoutMs' => 2500,
	);
}

/**
 * Resolve the config: defaults <- saved options <- filter override.
 *
 * @return array<string, mixed>
 */
function consent_fallback_get_config() {
	$defaults = consent_fallback_defaults();
	$saved    = get_option( CONSENT_FALLBACK_OPTION_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$config = wp_parse_args( $saved, $defaults );

	// Re-clamp the timeout in case a stale option slipped in below the floor.
	$config['observeTimeoutMs'] = max( 500, min( 30000, (int) $config['observeTimeoutMs'] ) );

	/**
	 * Filter the resolved Consent Fallback config before it is shipped to the browser.
	 *
	 * Code-level overrides win over the saved options — useful in
	 * version-controlled environments where config should live in the repo.
	 *
	 * @param array $config Resolved config keyed by messageTemplate, settingsLinkText, settingsJs, observeTimeoutMs.
	 */
	$config = apply_filters( 'consent_fallback_config', $config );

	return $config;
}

/**
 * Register the front-end script + style and inject the resolved config.
 */
function consent_fallback_enqueue_assets() {
	wp_register_style(
		'consent-fallback',
		CONSENT_FALLBACK_URL . 'assets/consent-fallback.css',
		array(),
		CONSENT_FALLBACK_VERSION
	);

	wp_register_script(
		'consent-fallback',
		CONSENT_FALLBACK_URL . 'assets/consent-fallback.js',
		array(),
		CONSENT_FALLBACK_VERSION,
		false // Load in <head> so observers attach before embed scripts run.
	);

	$config = consent_fallback_get_config();

	$inline = 'window.ConsentFallback = window.ConsentFallback || {};'
		. ' window.ConsentFallback.config = ' . wp_json_encode( $config ) . ';';

	wp_add_inline_script( 'consent-fallback', $inline, 'before' );

	wp_enqueue_style( 'consent-fallback' );
	wp_enqueue_script( 'consent-fallback' );
}
add_action( 'wp_enqueue_scripts', 'consent_fallback_enqueue_assets' );

/**
 * Shortcode: [consent_fallback label="form"]...embed HTML...[/consent_fallback]
 *
 * Wraps inner content in <div class="consent-fallback" data-fallback-label="...">.
 *
 * @param array<string, mixed> $atts    Shortcode attributes.
 * @param string|null          $content Inner content.
 * @return string Rendered HTML.
 */
function consent_fallback_shortcode( $atts, $content = null ) {
	$atts = shortcode_atts(
		array(
			'label' => 'content',
		),
		$atts,
		'consent_fallback'
	);

	// wpautop (which runs before shortcodes) can inject <br> tags at newlines
	// inside the shortcode body. Strip them so they don't register as
	// meaningful content in the JS observer.
	$inner = $content !== null ? do_shortcode( preg_replace( '/<br\s*\/?>/i', '', $content ) ) : '';

	return sprintf(
		'<div class="consent-fallback" data-fallback-label="%s">%s</div>',
		esc_attr( $atts['label'] ),
		$inner
	);
}
add_shortcode( 'consent_fallback', 'consent_fallback_shortcode' );

/**
 * Load translations.
 */
function consent_fallback_load_textdomain() {
	load_plugin_textdomain(
		'consent-fallback',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'consent_fallback_load_textdomain' );
