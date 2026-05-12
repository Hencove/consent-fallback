/**
 * Consent Fallback — observer/timer logic.
 *
 * Detection model is purely DOM-based and CMP-agnostic. For each
 * .consent-fallback wrapper:
 *
 *   1. If meaningful content already exists, do nothing.
 *   2. Otherwise, start a timer (observeTimeoutMs) AND a MutationObserver.
 *      - If content appears before the timer fires, cancel the timer and
 *        disconnect the observer (content won't disappear).
 *        The observer watches the full subtree because some embeds populate
 *        an existing child container rather than injecting a new top-level
 *        element; hasMeaningfulContent still checks direct children only.
 *      - If the timer fires with no content, inject the fallback message.
 *        The observer keeps running so that if the user later grants consent
 *        and the embed loads, the fallback is removed and the observer
 *        disconnects.
 */
(function () {
	'use strict';

	var DEFAULTS = {
		messageTemplate: 'This {label} requires Functional cookies to load. You can {settingsLink} and reload the page to view it.',
		settingsLinkText: 'manage your cookie preferences',
		settingsJs: 'window.ours_consent.showPreferences();',
		observeTimeoutMs: 2500
	};

	var FALLBACK_CLASS = 'consent-fallback__message';
	var LINK_CLASS = 'consent-fallback__settings-link';

	function getConfig() {
		var injected = (window.ConsentFallback && window.ConsentFallback.config) || {};
		var merged = {};
		for (var k in DEFAULTS) {
			if (Object.prototype.hasOwnProperty.call(DEFAULTS, k)) {
				merged[k] = (k in injected) ? injected[k] : DEFAULTS[k];
			}
		}
		return merged;
	}

	/**
	 * "Did meaningful content appear?"
	 *
	 * Direct children only — both HubSpot and Greenhouse render their content
	 * (form wrapper / iframe / app container) as a direct child of the
	 * snippet container, which is itself a direct child of our wrapper.
	 *
	 * Critically, we skip our own injected fallback message; otherwise the
	 * observer would see its own injection as content and immediately remove
	 * the fallback in an infinite loop.
	 */
	function hasMeaningfulContent(wrapper) {
		var children = wrapper.children;
		for (var i = 0; i < children.length; i++) {
			var child = children[i];
			if (child.classList && child.classList.contains(FALLBACK_CLASS)) {
				continue;
			}
			var tag = child.tagName;
			if (tag === 'SCRIPT' || tag === 'NOSCRIPT' || tag === 'BR') {
				continue;
			}
			return true;
		}
		return false;
	}

	function escapeHtml(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function buildFallbackElement(wrapper, config) {
		var label = wrapper.getAttribute('data-fallback-label') || 'content';

		// Settings link is built as an actual element so we can attach a
		// click handler and own its accessibility/focus behavior. We render
		// it to a string only to splice into the message template.
		var linkHtml = '<a href="#" class="' + LINK_CLASS + '">' + escapeHtml(config.settingsLinkText) + '</a>';

		var rendered = String(config.messageTemplate)
			.replace(/\{label\}/g, escapeHtml(label))
			.replace(/\{settingsLink\}/g, linkHtml);

		var el = document.createElement('div');
		el.className = FALLBACK_CLASS;
		el.setAttribute('role', 'status');
		el.setAttribute('aria-live', 'polite');
		// Safe: messageTemplate is stored after sanitize_textarea_field (strips
		// tags); {label} and settingsLinkText are HTML-escaped above.
		el.innerHTML = rendered;

		var link = el.querySelector('.' + LINK_CLASS);
		if (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				try {
					// new Function() is intentional here. The settingsJs
					// snippet comes from a manage_options + unfiltered_html
					// admin (or a code-level filter), so trust matches what
					// they could already do via WP's normal post HTML.
					new Function(config.settingsJs)();
				} catch (err) {
					if (window.console && window.console.error) {
						window.console.error('[ConsentFallback] settings link error:', err);
					}
				}
			});
		}

		return el;
	}

	function getInjectedFallback(wrapper) {
		for (var i = 0; i < wrapper.children.length; i++) {
			var child = wrapper.children[i];
			if (child.classList && child.classList.contains(FALLBACK_CLASS)) {
				return child;
			}
		}
		return null;
	}

	function attach(wrapper, config) {
		// Already populated at page load — nothing to do.
		if (hasMeaningfulContent(wrapper)) {
			return;
		}

		var fallbackShown = false;
		var timeoutId = setTimeout(function () {
			timeoutId = null;
			if (!hasMeaningfulContent(wrapper)) {
				wrapper.appendChild(buildFallbackElement(wrapper, config));
				fallbackShown = true;
			} else {
				// Content arrived before the timeout (observer missed the mutation
				// because it registered after the embed loaded). Nothing to do.
				observer.disconnect();
			}
		}, config.observeTimeoutMs);

		var observer = new MutationObserver(function () {
			var hasContent = hasMeaningfulContent(wrapper);

			if (!fallbackShown && hasContent) {
				// Embed populated before our timer fired. Cancel the timer
				// and disconnect — content won't disappear.
				if (timeoutId !== null) {
					clearTimeout(timeoutId);
					timeoutId = null;
				}
				observer.disconnect();
				return;
			}

			if (fallbackShown && hasContent) {
				// Late-loading embed (e.g. user just granted consent).
				// Remove the fallback, then disconnect — content won't disappear.
				var existing = getInjectedFallback(wrapper);
				if (existing && existing.parentNode === wrapper) {
					wrapper.removeChild(existing);
				}
				fallbackShown = false;
				observer.disconnect();
			}
		});

		observer.observe(wrapper, { childList: true, subtree: true });
	}

	function init() {
		if (!('MutationObserver' in window)) {
			return;
		}
		var config = getConfig();
		var wrappers = document.querySelectorAll('.consent-fallback');
		for (var i = 0; i < wrappers.length; i++) {
			attach(wrappers[i], config);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
