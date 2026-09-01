<?php
/**
 * Plugin Name:  Agent Ready
 * Description:  Makes this site readable and actionable by AI agents. Serves llms.txt and a
 *               capability manifest, publishes structured data from facts you enter, allows
 *               live AI agents in robots.txt, and registers WebMCP tools.
 * Version:      1.0.0
 * Author:       Sneeze It
 * License:      MIT
 * Text Domain:  agent-ready
 *
 * Everything this plugin publishes comes from the settings screen, never from a guess.
 * That is deliberate: structured data asserting a phone number that does not ring is a
 * machine readable lie, and agents repeat it to customers.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AGENTREADY_OPT', 'agentready_settings' );

function agentready_opt( $key, $default = '' ) {
	$o = get_option( AGENTREADY_OPT, array() );
	return isset( $o[ $key ] ) && $o[ $key ] !== '' ? $o[ $key ] : $default;
}

/* ------------------------------------------------------------------ settings */

add_action( 'admin_menu', function () {
	add_options_page( 'Agent Ready', 'Agent Ready', 'manage_options', 'agent-ready', 'agentready_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'agentready', AGENTREADY_OPT, array(
		'sanitize_callback' => 'agentready_sanitize',
	) );
} );

function agentready_sanitize( $in ) {
	$out = array();
	foreach ( (array) $in as $k => $v ) {
		$out[ sanitize_key( $k ) ] = is_string( $v ) ? sanitize_textarea_field( $v ) : '';
	}
	return $out;
}

function agentready_fields() {
	return array(
		'business_name' => array( 'Business name', 'text', '' ),
		'business_type' => array( 'Schema type', 'text', 'LocalBusiness' ),
		'summary'       => array( 'One sentence: what you do and for whom', 'textarea', '' ),
		'telephone'     => array( 'Phone (as dialled, e.g. +1-555-555-5555)', 'text', '' ),
		'email'         => array( 'Public email', 'text', '' ),
		'street'        => array( 'Street address', 'text', '' ),
		'city'          => array( 'City', 'text', '' ),
		'region'        => array( 'State or region', 'text', '' ),
		'postal'        => array( 'Postal code', 'text', '' ),
		'country'       => array( 'Country code', 'text', 'US' ),
		'hours'         => array( 'Opening hours, one per line: Mon-Fri 09:00-17:00', 'textarea', '' ),
		'services'      => array( 'Services, one per line: Name | description | price', 'textarea', '' ),
		'booking_url'   => array( 'Booking or contact URL', 'text', '' ),
		'price_range'   => array( 'Price range, e.g. $$', 'text', '' ),
		'allow_training'=> array( 'Allow AI training crawlers (GPTBot, CCBot)? yes or no', 'text', 'no' ),
	);
}

function agentready_settings_page() {
	$o = get_option( AGENTREADY_OPT, array() );
	?>
	<div class="wrap">
		<h1>Agent Ready</h1>
		<p>Everything below is published to AI agents as fact. Leave a field blank rather than
		guessing at it: a blank field costs you a few points, a wrong one costs you a customer.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'agentready' ); ?>
			<table class="form-table" role="presentation">
			<?php foreach ( agentready_fields() as $key => $f ) :
				$val = isset( $o[ $key ] ) ? $o[ $key ] : $f[2]; ?>
				<tr>
					<th scope="row"><label for="ar_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
					<td>
					<?php if ( 'textarea' === $f[1] ) : ?>
						<textarea id="ar_<?php echo esc_attr( $key ); ?>" rows="4" class="large-text"
							name="<?php echo esc_attr( AGENTREADY_OPT ); ?>[<?php echo esc_attr( $key ); ?>]"><?php
							echo esc_textarea( $val ); ?></textarea>
					<?php else : ?>
						<input id="ar_<?php echo esc_attr( $key ); ?>" type="text" class="regular-text"
							name="<?php echo esc_attr( AGENTREADY_OPT ); ?>[<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( $val ); ?>">
					<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
		<h2>Check your work</h2>
		<p>Scan this site at <a href="https://agentready.sneeze.it" target="_blank" rel="noopener">agentready.sneeze.it</a>.
		After saving, <code><?php echo esc_html( home_url( '/llms.txt' ) ); ?></code> and
		<code><?php echo esc_html( home_url( '/.well-known/mcp.json' ) ); ?></code> should both serve.
		If they 404, go to Settings, Permalinks and click Save once to flush the rewrite rules.</p>
	</div>
	<?php
}

/* ------------------------------------------------------- llms.txt + manifest */

add_action( 'init', function () {
	add_rewrite_rule( '^llms\.txt$', 'index.php?agentready=llms', 'top' );
	add_rewrite_rule( '^\.well-known/mcp\.json$', 'index.php?agentready=mcp', 'top' );
} );

add_filter( 'query_vars', function ( $v ) { $v[] = 'agentready'; return $v; } );

register_activation_hook( __FILE__, function () { flush_rewrite_rules(); } );
register_deactivation_hook( __FILE__, function () { flush_rewrite_rules(); } );

add_action( 'template_redirect', function () {
	$what = get_query_var( 'agentready' );
	if ( ! $what ) { return; }

	if ( 'llms' === $what ) {
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo agentready_llms_txt(); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}
	if ( 'mcp' === $what ) {
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( agentready_manifest(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}
} );

function agentready_llms_txt() {
	$name = agentready_opt( 'business_name', get_bloginfo( 'name' ) );
	$out  = "# {$name}\n\n";
	$sum  = agentready_opt( 'summary' );
	if ( $sum ) { $out .= "> {$sum}\n\n"; }

	$services = array_filter( array_map( 'trim', explode( "\n", agentready_opt( 'services' ) ) ) );
	if ( $services ) {
		$out .= "## Services\n";
		foreach ( $services as $line ) {
			$p = array_map( 'trim', explode( '|', $line ) );
			$out .= '- **' . $p[0] . '**';
			if ( ! empty( $p[1] ) ) { $out .= ' — ' . $p[1]; }
			if ( ! empty( $p[2] ) ) { $out .= ' (' . $p[2] . ')'; }
			$out .= "\n";
		}
		$out .= "\n";
	}

	$addr = array_filter( array( agentready_opt( 'street' ), agentready_opt( 'city' ),
		agentready_opt( 'region' ), agentready_opt( 'postal' ) ) );
	if ( $addr || agentready_opt( 'telephone' ) ) {
		$out .= "## Contact\n";
		if ( $addr ) { $out .= '- ' . implode( ', ', $addr ) . "\n"; }
		if ( agentready_opt( 'telephone' ) ) { $out .= '- ' . agentready_opt( 'telephone' ) . "\n"; }
		if ( agentready_opt( 'email' ) ) { $out .= '- ' . agentready_opt( 'email' ) . "\n"; }
		$out .= "\n";
	}

	$hours = array_filter( array_map( 'trim', explode( "\n", agentready_opt( 'hours' ) ) ) );
	if ( $hours ) { $out .= "## Hours\n" . implode( "\n", array_map( function ( $h ) { return "- $h"; }, $hours ) ) . "\n\n"; }

	if ( agentready_opt( 'booking_url' ) ) { $out .= "## Booking\n" . agentready_opt( 'booking_url' ) . "\n\n"; }

	$out .= "## Key pages\n";
	foreach ( get_pages( array( 'number' => 12, 'sort_column' => 'menu_order' ) ) as $p ) {
		$out .= '- [' . $p->post_title . '](' . get_permalink( $p ) . ")\n";
	}
	$out .= "\nLast updated: " . gmdate( 'Y-m-d' ) . "\n";
	return $out;
}

function agentready_manifest() {
	return array(
		'name'        => agentready_opt( 'business_name', get_bloginfo( 'name' ) ),
		'description' => agentready_opt( 'summary', get_bloginfo( 'description' ) ),
		'version'     => '1.0.0',
		'contact'     => array( 'email' => agentready_opt( 'email' ), 'url' => home_url( '/' ) ),
		'tools'       => apply_filters( 'agentready_tools', array() ),
	);
}

/* --------------------------------------------------------------- structured data */

add_action( 'wp_head', function () {
	if ( ! is_front_page() && ! is_home() ) { return; }
	$name = agentready_opt( 'business_name' );
	if ( ! $name ) { return; } // Nothing entered means nothing asserted.

	$ld = array(
		'@context' => 'https://schema.org',
		'@type'    => agentready_opt( 'business_type', 'LocalBusiness' ),
		'name'     => $name,
		'url'      => home_url( '/' ),
	);
	foreach ( array( 'telephone' => 'telephone', 'email' => 'email', 'price_range' => 'priceRange' ) as $k => $prop ) {
		if ( agentready_opt( $k ) ) { $ld[ $prop ] = agentready_opt( $k ); }
	}
	if ( agentready_opt( 'street' ) || agentready_opt( 'city' ) ) {
		$ld['address'] = array_filter( array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => agentready_opt( 'street' ),
			'addressLocality' => agentready_opt( 'city' ),
			'addressRegion'   => agentready_opt( 'region' ),
			'postalCode'      => agentready_opt( 'postal' ),
			'addressCountry'  => agentready_opt( 'country', 'US' ),
		) );
	}
	$spec = agentready_hours_spec();
	if ( $spec ) { $ld['openingHoursSpecification'] = $spec; }

	$offers = array();
	foreach ( array_filter( array_map( 'trim', explode( "\n", agentready_opt( 'services' ) ) ) ) as $line ) {
		$p    = array_map( 'trim', explode( '|', $line ) );
		$item = array_filter( array( '@type' => 'Service', 'name' => $p[0], 'description' => isset( $p[1] ) ? $p[1] : '' ) );
		$offer = array( '@type' => 'Offer', 'itemOffered' => $item );
		if ( ! empty( $p[2] ) && is_numeric( preg_replace( '/[^0-9.]/', '', $p[2] ) ) ) {
			$offer['price']         = preg_replace( '/[^0-9.]/', '', $p[2] );
			$offer['priceCurrency'] = 'USD';
		}
		$offers[] = $offer;
	}
	if ( $offers ) {
		$ld['hasOfferCatalog'] = array( '@type' => 'OfferCatalog', 'name' => 'Services', 'itemListElement' => $offers );
	}
	if ( agentready_opt( 'booking_url' ) ) {
		$ld['potentialAction'] = array(
			'@type'  => 'ReserveAction',
			'target' => array( '@type' => 'EntryPoint', 'urlTemplate' => agentready_opt( 'booking_url' ) ),
		);
	}
	echo "\n<script type=\"application/ld+json\">" . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . "</script>\n";
}, 20 );

/** "Mon-Fri 09:00-17:00" into a schema.org OpeningHoursSpecification. */
function agentready_hours_spec() {
	$map = array( 'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
		'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday' );
	$out = array();
	foreach ( array_filter( array_map( 'trim', explode( "\n", agentready_opt( 'hours' ) ) ) ) as $line ) {
		if ( ! preg_match( '/^([a-z]{3})(?:\s*-\s*([a-z]{3}))?\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/i', $line, $m ) ) { continue; }
		$keys  = array_keys( $map );
		$start = array_search( strtolower( $m[1] ), $keys, true );
		$end   = $m[2] ? array_search( strtolower( $m[2] ), $keys, true ) : $start;
		if ( false === $start || false === $end ) { continue; }
		$days = array();
		for ( $i = $start; $i <= $end; $i++ ) { $days[] = $map[ $keys[ $i ] ]; }
		$out[] = array( '@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days,
			'opens' => $m[3], 'closes' => $m[4] );
	}
	return $out;
}

/* ------------------------------------------------------------------- robots.txt */

add_filter( 'robots_txt', function ( $txt ) {
	$allow_training = 'yes' === strtolower( agentready_opt( 'allow_training', 'no' ) );
	$add  = "\n# Agent Ready: live AI agents are customers mid-task. Always allowed.\n";
	foreach ( array( 'ChatGPT-User', 'Claude-User', 'Perplexity-User', 'OAI-SearchBot', 'Claude-SearchBot' ) as $ua ) {
		$add .= "User-agent: {$ua}\nAllow: /\n\n";
	}
	if ( ! $allow_training ) {
		$add .= "# Training crawlers, blocked by choice in Settings > Agent Ready.\n";
		foreach ( array( 'GPTBot', 'CCBot', 'Google-Extended', 'Applebot-Extended' ) as $ua ) {
			$add .= "User-agent: {$ua}\nDisallow: /\n\n";
		}
	}
	$add .= 'Sitemap: ' . home_url( '/wp-sitemap.xml' ) . "\n";
	return $txt . $add;
}, 20 );

/* ----------------------------------------------------------------------- WebMCP */

add_action( 'wp_enqueue_scripts', function () {
	$file = plugin_dir_path( __FILE__ ) . 'webmcp.js';
	if ( ! file_exists( $file ) ) { return; }
	wp_enqueue_script( 'agentready-webmcp', plugin_dir_url( __FILE__ ) . 'webmcp.js',
		array(), '1.0.0', true );
	wp_localize_script( 'agentready-webmcp', 'AgentReadyConfig', array(
		'root'       => esc_url_raw( rest_url() ),
		'bookingUrl' => agentready_opt( 'booking_url' ),
	) );
} );
