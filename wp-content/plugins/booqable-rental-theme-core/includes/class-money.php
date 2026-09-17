<?php
/**
 * Formats `*_in_cents` integers using the store's *real* Booqable currency
 * (`Company_Cache`) — never a hardcoded "$". Uses PHP's `intl` extension
 * (`NumberFormatter`) so the correct number of decimal places per ISO 4217
 * (e.g. JPY shows 0, USD/EUR/IDR show 2) is never hand-maintained.
 *
 * Deliberately does **not** use `get_locale()` (the WP site's admin/UI
 * language) to pick formatting conventions — that reflects the site
 * owner's dashboard language, not the currency's own market. A Booqable
 * store selling in IDR should show "Rp" whether the WordPress admin is set
 * to English or Indonesian. Falls back to a plain "$1.23" format only if
 * `intl` genuinely isn't installed on the host.
 *
 * @package Booqable_Rental_Core
 */

namespace Booqable_Rental_Core;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Money {

	/**
	 * Maps a currency to the locale ICU actually has a native symbol for
	 * (e.g. `id_ID` knows IDR is "Rp"; `en_US` only knows its own dollar and
	 * falls back to printing "IDR" as a bare ISO code for anything else).
	 * Falls back to `en_US` for any currency not listed — still correct
	 * decimals/separators, just an ISO-code-style symbol instead of a glyph.
	 *
	 * @var array<string,string>
	 */
	const CURRENCY_LOCALES = array(
		'usd' => 'en_US',
		'eur' => 'en_IE',
		'gbp' => 'en_GB',
		'idr' => 'id_ID',
		'jpy' => 'ja_JP',
		'cny' => 'zh_CN',
		'inr' => 'en_IN',
		'aud' => 'en_AU',
		'cad' => 'en_CA',
		'sgd' => 'en_SG',
		'myr' => 'ms_MY',
		'php' => 'en_PH',
		'thb' => 'th_TH',
		'vnd' => 'vi_VN',
		'krw' => 'ko_KR',
		'chf' => 'de_CH',
		'nzd' => 'en_NZ',
		'zar' => 'en_ZA',
		'brl' => 'pt_BR',
		'mxn' => 'es_MX',
	);

	/**
	 * @return string Lowercase ISO 4217 code, e.g. "idr". Falls back to
	 *                "usd" if Booqable isn't configured/reachable yet.
	 */
	public static function get_currency_code() {
		$company = Company_Cache::get();
		if ( is_wp_error( $company ) || empty( $company['currency'] ) ) {
			return 'usd';
		}
		return $company['currency'];
	}

	/**
	 * @param string $code Uppercase ISO 4217 code.
	 * @return \NumberFormatter|null Null when `intl` isn't installed.
	 */
	protected static function make_formatter( $code ) {
		if ( ! class_exists( '\NumberFormatter' ) ) {
			return null;
		}
		$locale = self::CURRENCY_LOCALES[ strtolower( $code ) ] ?? 'en_US';
		return new \NumberFormatter( $locale, \NumberFormatter::CURRENCY );
	}

	/**
	 * Server-side formatting — used by render.php templates.
	 *
	 * @param int $cents
	 * @return string e.g. "Rp50,000.00" or "$50.00", per the store's real currency.
	 */
	public static function format( $cents ) {
		$code      = strtoupper( self::get_currency_code() );
		$formatter = self::make_formatter( $code );
		if ( ! $formatter ) {
			return '$' . number_format( $cents / 100, 2 );
		}
		return $formatter->formatCurrency( $cents / 100, $code );
	}

	/**
	 * The frontend cart formats *live* totals after every add/update/remove
	 * without a page reload, so it needs the formatting primitives, not a
	 * pre-baked string — passed to `assets/js/cart.js` via
	 * `wp_localize_script()`.
	 *
	 * @return array{code:string,symbol:string,decimals:int,decimalSeparator:string,groupSeparator:string,symbolFirst:bool}
	 */
	public static function get_js_config() {
		$code      = strtoupper( self::get_currency_code() );
		$formatter = self::make_formatter( $code );
		if ( ! $formatter ) {
			return array(
				'code'             => $code,
				'symbol'           => '$',
				'decimals'         => 2,
				'decimalSeparator' => '.',
				'groupSeparator'   => ',',
				'symbolFirst'      => true,
			);
		}

		// Format a sample first — ICU only applies this specific currency's
		// fraction-digit rule (e.g. JPY=0) once formatCurrency() has run for
		// it; reading the attribute beforehand would just give the locale's
		// own default currency's digit count.
		$sample = $formatter->formatCurrency( 1234, $code );
		$symbol = $formatter->getSymbol( \NumberFormatter::CURRENCY_SYMBOL );

		return array(
			'code'             => $code,
			'symbol'           => $symbol,
			'decimals'         => (int) $formatter->getAttribute( \NumberFormatter::FRACTION_DIGITS ),
			'decimalSeparator' => $formatter->getSymbol( \NumberFormatter::DECIMAL_SEPARATOR_SYMBOL ),
			'groupSeparator'   => $formatter->getSymbol( \NumberFormatter::GROUPING_SEPARATOR_SYMBOL ),
			// Is the symbol the first non-space character of a formatted sample,
			// or does it trail the number? Locale + currency dependent (e.g. "$1.00" vs "1,00 €").
			'symbolFirst'      => 0 === strpos( ltrim( $sample ), $symbol ),
		);
	}
}
