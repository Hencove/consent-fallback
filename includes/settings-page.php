<?php
/**
 * Settings → Consent Fallback admin page.
 *
 * @package ConsentFallback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add the menu entry under Settings.
 */
function consent_fallback_register_settings_page() {
	add_options_page(
		__( 'Consent Fallback', 'consent-fallback' ),
		__( 'Consent Fallback', 'consent-fallback' ),
		'manage_options',
		'consent-fallback',
		'consent_fallback_render_settings_page'
	);
}
add_action( 'admin_menu', 'consent_fallback_register_settings_page' );

/**
 * Register the option, settings section, and fields.
 */
function consent_fallback_register_settings() {
	register_setting(
		'consent_fallback_settings',
		CONSENT_FALLBACK_OPTION_KEY,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'consent_fallback_sanitize_options',
			'default'           => consent_fallback_defaults(),
		)
	);

	add_settings_section(
		'consent_fallback_usage',
		__( 'How to use', 'consent-fallback' ),
		'consent_fallback_render_usage_section',
		'consent-fallback'
	);

	add_settings_section(
		'consent_fallback_main',
		__( 'Fallback message', 'consent-fallback' ),
		'consent_fallback_render_section_intro',
		'consent-fallback'
	);

	add_settings_field(
		'messageTemplate',
		__( 'Message template', 'consent-fallback' ),
		'consent_fallback_field_message_template',
		'consent-fallback',
		'consent_fallback_main',
		array( 'label_for' => 'consent_fallback_messageTemplate' )
	);

	add_settings_field(
		'settingsLinkText',
		__( 'Settings link text', 'consent-fallback' ),
		'consent_fallback_field_settings_link_text',
		'consent-fallback',
		'consent_fallback_main',
		array( 'label_for' => 'consent_fallback_settingsLinkText' )
	);

	add_settings_field(
		'settingsJs',
		__( 'Settings link JavaScript', 'consent-fallback' ),
		'consent_fallback_field_settings_js',
		'consent-fallback',
		'consent_fallback_main',
		array( 'label_for' => 'consent_fallback_settingsJs' )
	);

	add_settings_field(
		'observeTimeoutMs',
		__( 'Detection timeout (ms)', 'consent-fallback' ),
		'consent_fallback_field_timeout',
		'consent-fallback',
		'consent_fallback_main',
		array( 'label_for' => 'consent_fallback_observeTimeoutMs' )
	);
}
add_action( 'admin_init', 'consent_fallback_register_settings' );

/**
 * "How to use" reference section — shown above the editable fields so authors
 * can copy the markup without leaving the page.
 */
function consent_fallback_render_usage_section() {
	$shortcode_example = "[consent_fallback label=\"this form\"]\n  <!-- paste your HubSpot, Greenhouse, etc. embed code here -->\n[/consent_fallback]";
	$html_example      = "<div class=\"consent-fallback\" data-fallback-label=\"this form\">\n  <!-- paste your HubSpot, Greenhouse, etc. embed code here -->\n</div>";
	?>
	<p>
		<?php
		echo wp_kses(
			__( 'Wrap each embed (HubSpot form, Greenhouse board, etc.) in a <code>.consent-fallback</code> element. If the embed hasn\'t populated by the detection timeout below, the fallback message is injected. Pick whichever method fits the editor you\'re using:', 'consent-fallback' ),
			array( 'code' => array() )
		);
		?>
	</p>

	<h3 style="margin-top:1.25em;"><?php esc_html_e( 'Option 1 — Shortcode', 'consent-fallback' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'Easiest in the classic editor or a Gutenberg shortcode block.', 'consent-fallback' ); ?>
	</p>
	<pre style="padding:0.75em 1em; background:#f6f7f7; border:1px solid #dcdcde; border-radius:3px; overflow:auto;"><code><?php echo esc_html( $shortcode_example ); ?></code></pre>

	<h3 style="margin-top:1.25em;"><?php esc_html_e( 'Option 2 — Direct HTML', 'consent-fallback' ); ?></h3>
	<p class="description">
		<?php
		echo wp_kses(
			__( 'For Divi <strong>Code</strong> modules, Gutenberg <strong>Custom HTML</strong> blocks, or any builder that lets you paste raw markup.', 'consent-fallback' ),
			array( 'strong' => array() )
		);
		?>
	</p>
	<pre style="padding:0.75em 1em; background:#f6f7f7; border:1px solid #dcdcde; border-radius:3px; overflow:auto;"><code><?php echo esc_html( $html_example ); ?></code></pre>

	<p class="description" style="margin-top:1em;">
		<?php
		echo wp_kses(
			__( 'The <code>label</code> attribute (or <code>data-fallback-label</code>) is interpolated into the message via the <code>{label}</code> placeholder. If you omit it, it defaults to <code>this content</code>.', 'consent-fallback' ),
			array( 'code' => array() )
		);
		?>
	</p>
	<?php
}

/**
 * Section blurb above the fields.
 */
function consent_fallback_render_section_intro() {
	echo '<p>' . esc_html__(
		'These values control the message that appears inside .consent-fallback wrappers when an embed fails to load. Use {label} and {settingsLink} as placeholders inside the message template.',
		'consent-fallback'
	) . '</p>';
}

/**
 * Render the full settings page.
 */
function consent_fallback_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'consent_fallback_settings' );
			do_settings_sections( 'consent-fallback' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Helper: pull a single option value with default fallback.
 *
 * @param string $key Option sub-key.
 * @return mixed
 */
function consent_fallback_get_option( $key ) {
	$defaults = consent_fallback_defaults();
	$saved    = get_option( CONSENT_FALLBACK_OPTION_KEY, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	$merged = wp_parse_args( $saved, $defaults );
	return isset( $merged[ $key ] ) ? $merged[ $key ] : ( $defaults[ $key ] ?? '' );
}

/**
 * Field: messageTemplate.
 */
function consent_fallback_field_message_template() {
	$value = consent_fallback_get_option( 'messageTemplate' );
	?>
	<textarea
		id="consent_fallback_messageTemplate"
		name="<?php echo esc_attr( CONSENT_FALLBACK_OPTION_KEY ); ?>[messageTemplate]"
		rows="3"
		cols="60"
		class="large-text"
	><?php echo esc_textarea( $value ); ?></textarea>
	<p class="description">
		<?php
		echo wp_kses(
			__( 'Plain text. <code>{label}</code> is replaced with each wrapper\'s <code>data-fallback-label</code>. <code>{settingsLink}</code> is replaced with the link below.', 'consent-fallback' ),
			array( 'code' => array() )
		);
		?>
	</p>
	<?php
}

/**
 * Field: settingsLinkText.
 */
function consent_fallback_field_settings_link_text() {
	$value = consent_fallback_get_option( 'settingsLinkText' );
	?>
	<input
		type="text"
		id="consent_fallback_settingsLinkText"
		name="<?php echo esc_attr( CONSENT_FALLBACK_OPTION_KEY ); ?>[settingsLinkText]"
		value="<?php echo esc_attr( $value ); ?>"
		class="regular-text"
	/>
	<p class="description">
		<?php esc_html_e( 'The visible text of the link inside the fallback message.', 'consent-fallback' ); ?>
	</p>
	<?php
}

/**
 * Field: settingsJs. Disabled when the user lacks unfiltered_html.
 */
function consent_fallback_field_settings_js() {
	$value      = consent_fallback_get_option( 'settingsJs' );
	$can_edit   = current_user_can( 'unfiltered_html' );
	$disabled   = $can_edit ? '' : ' disabled="disabled"';
	$readonly   = $can_edit ? '' : ' readonly="readonly"';
	?>
	<textarea
		id="consent_fallback_settingsJs"
		name="<?php echo esc_attr( CONSENT_FALLBACK_OPTION_KEY ); ?>[settingsJs]"
		rows="4"
		cols="60"
		class="large-text code"
		<?php echo $disabled . $readonly; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute strings ?>
	><?php echo esc_textarea( $value ); ?></textarea>
	<?php if ( ! $can_edit ) : ?>
		<p class="description" style="color:#b32d2e;">
			<?php esc_html_e( 'This field requires the unfiltered_html capability. On multisite, only Super Admins can edit it. The previously saved value will be preserved.', 'consent-fallback' ); ?>
		</p>
	<?php endif; ?>
	<p class="description">
		<?php esc_html_e( 'JavaScript that runs when the fallback link is clicked. Typically opens your CMP\'s preferences UI. Errors are caught and logged to the console.', 'consent-fallback' ); ?>
	</p>
	<?php
}

/**
 * Field: observeTimeoutMs.
 */
function consent_fallback_field_timeout() {
	$value = consent_fallback_get_option( 'observeTimeoutMs' );
	?>
	<input
		type="number"
		id="consent_fallback_observeTimeoutMs"
		name="<?php echo esc_attr( CONSENT_FALLBACK_OPTION_KEY ); ?>[observeTimeoutMs]"
		value="<?php echo esc_attr( (string) $value ); ?>"
		min="500"
		max="30000"
		step="100"
		class="small-text"
	/>
	<p class="description">
		<?php esc_html_e( 'How long to wait (in milliseconds) for an embed to populate before showing the fallback. Default 2500.', 'consent-fallback' ); ?>
	</p>
	<?php
}

/**
 * Sanitize the option array.
 *
 * Server-side enforcement of the unfiltered_html gate on the JS field: if the
 * current user can't edit it, retain the previously saved value rather than
 * trusting the (possibly forged) POST.
 *
 * @param mixed $input Raw POST value.
 * @return array<string, mixed>
 */
function consent_fallback_sanitize_options( $input ) {
	$defaults  = consent_fallback_defaults();
	$existing  = get_option( CONSENT_FALLBACK_OPTION_KEY, array() );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}
	$existing = wp_parse_args( $existing, $defaults );

	if ( ! is_array( $input ) ) {
		$input = array();
	}

	$out = array();

	$out['messageTemplate'] = isset( $input['messageTemplate'] )
		? sanitize_textarea_field( wp_unslash( $input['messageTemplate'] ) )
		: $existing['messageTemplate'];
	if ( '' === $out['messageTemplate'] ) {
		$out['messageTemplate'] = $defaults['messageTemplate'];
	}

	$out['settingsLinkText'] = isset( $input['settingsLinkText'] )
		? sanitize_text_field( wp_unslash( $input['settingsLinkText'] ) )
		: $existing['settingsLinkText'];
	if ( '' === $out['settingsLinkText'] ) {
		$out['settingsLinkText'] = $defaults['settingsLinkText'];
	}

	if ( current_user_can( 'unfiltered_html' ) ) {
		// Trust this user. JS cannot be sanitized in any meaningful way; we
		// rely on wp_json_encode at output time to keep it safe as a string.
		$out['settingsJs'] = isset( $input['settingsJs'] )
			? (string) wp_unslash( $input['settingsJs'] )
			: $existing['settingsJs'];
	} else {
		// Discard whatever was POSTed; keep what was already saved.
		$out['settingsJs'] = $existing['settingsJs'];
	}

	$timeout = isset( $input['observeTimeoutMs'] ) ? absint( $input['observeTimeoutMs'] ) : (int) $existing['observeTimeoutMs'];
	$out['observeTimeoutMs'] = max( 500, min( 30000, $timeout ) );

	return $out;
}
