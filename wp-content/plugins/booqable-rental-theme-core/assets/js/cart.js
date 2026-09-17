/**
 * Cart widget — the cart itself is plain state in the visitor's own
 * `localStorage`, not a Booqable Order. Booqable is only ever contacted for
 * two things:
 *
 *  1. A read-only stock check (`GET /availability`) — fired once in the
 *     background right after an item is added, and again in the background
 *     whenever dates change. It never blocks anything the visitor sees; it
 *     only refines the `max` this cart already assumed.
 *  2. The actual `POST /checkout` — the one moment the cart becomes a real
 *     Order.
 *
 * Every add/qty/remove/date interaction below is instant because none of it
 * calls an API at all. This replaced an earlier design where the cart *was*
 * a Booqable Order and every click was a live 1-5s round trip — see
 * cart-and-checkout.md and known-gaps.md for the full history and the
 * trade-off (stock isn't held until Checkout; the total shown here is an
 * estimate, not Booqable's authoritative number).
 */
( function () {
	'use strict';

	var config = window.BooqableCartConfig || {};
	var restUrl = ( config.restUrl || '' ).replace( /\/$/, '' );
	var STORAGE_KEY = 'bq_cart_v1';

	// Only the final Checkout submission is a real network call from this
	// file's own actions, so this only ever guards against a double-submit —
	// nothing else here needs a busy lock any more.
	var checkoutBusy = false;

	function request( method, path, body ) {
		var headers = { 'Content-Type': 'application/json' };
		if ( method !== 'GET' ) {
			headers[ 'X-WP-Nonce' ] = config.nonce || '';
		}
		return fetch( restUrl + path, {
			method: method,
			headers: headers,
			credentials: 'same-origin',
			body: body ? JSON.stringify( body ) : undefined,
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					throw new Error( data.message || 'Something went wrong.' );
				}
				return data;
			} );
		} );
	}

	// Real currency from the Booqable account (symbol, decimal places,
	// separators, symbol position) — see Money::get_js_config(). Never
	// hardcode "$"; IDR/JPY show 0 decimals, others show 2, etc.
	var currency = config.currency || {
		symbol: '$',
		decimals: 2,
		decimalSeparator: '.',
		groupSeparator: ',',
		symbolFirst: true,
	};

	function formatMoney( cents ) {
		var value = ( cents / 100 ).toFixed( currency.decimals );
		var parts = value.split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, currency.groupSeparator );
		var number = currency.decimals > 0 ? parts.join( currency.decimalSeparator ) : parts[ 0 ];
		return currency.symbolFirst ? currency.symbol + number : number + ' ' + currency.symbol;
	}

	function isoDate( date ) {
		return date.toISOString().slice( 0, 10 );
	}

	function defaultCart() {
		var tomorrow = new Date( Date.now() + 24 * 3600 * 1000 );
		var dayAfter = new Date( Date.now() + 2 * 24 * 3600 * 1000 );
		return {
			starts_at: isoDate( tomorrow ),
			stops_at: isoDate( dayAfter ),
			lines: [], // { product_id, title, price_in_cents, price_period, quantity, max }
		};
	}

	// localStorage can throw (private browsing, storage disabled, quota) —
	// every call is wrapped so a storage failure just means "cart doesn't
	// persist across reloads", never a broken page.
	function loadCart() {
		try {
			var raw = window.localStorage.getItem( STORAGE_KEY );
			if ( ! raw ) {
				return defaultCart();
			}
			var parsed = JSON.parse( raw );
			if ( ! parsed || ! Array.isArray( parsed.lines ) ) {
				return defaultCart();
			}
			return parsed;
		} catch ( e ) {
			return defaultCart();
		}
	}

	function saveCart( cart ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( cart ) );
		} catch ( e ) {
			/* falls back to in-memory-only for this page view */
		}
	}

	function findLine( cart, productId ) {
		for ( var i = 0; i < cart.lines.length; i++ ) {
			if ( cart.lines[ i ].product_id === productId ) {
				return cart.lines[ i ];
			}
		}
		return null;
	}

	function estimateTotal( cart ) {
		return cart.lines.reduce( function ( sum, line ) {
			return sum + line.price_in_cents * line.quantity;
		}, 0 );
	}

	function itemCount( cart ) {
		return cart.lines.reduce( function ( sum, line ) {
			return sum + line.quantity;
		}, 0 );
	}

	function anyLineAtLimit( cart ) {
		return cart.lines.some( function ( line ) {
			return null !== line.max && line.quantity >= line.max;
		} );
	}

	function render( cart ) {
		var countEl = document.getElementById( 'bq-cart-count' );
		if ( countEl ) {
			countEl.textContent = itemCount( cart );
		}

		var startsEl = document.getElementById( 'bq-cart-starts' );
		var stopsEl = document.getElementById( 'bq-cart-stops' );
		// Don't clobber a date the visitor is actively editing.
		if ( startsEl && document.activeElement !== startsEl ) {
			startsEl.value = cart.starts_at;
		}
		if ( stopsEl && document.activeElement !== stopsEl ) {
			stopsEl.value = cart.stops_at;
		}

		var body = document.getElementById( 'bq-cart-body' );
		if ( ! body ) {
			return;
		}

		if ( ! cart.lines.length ) {
			body.innerHTML = '<p class="bq-cart-empty">Your cart is empty.</p>';
		} else {
			var rows = cart.lines
				.map( function ( line ) {
					return (
						'<div class="bq-cart-line" data-product-id="' + line.product_id + '">' +
						'<span class="bq-cart-line-title">' + line.title + '</span>' +
						'<span class="bq-cart-line-qty">' +
						'<button class="bq-qty-dec" aria-label="Decrease quantity">&minus;</button>' +
						'<span>' + line.quantity + '</span>' +
						'<button class="bq-qty-inc" aria-label="Increase quantity">&plus;</button>' +
						'</span>' +
						'<span class="bq-cart-line-price">' + formatMoney( line.price_in_cents * line.quantity ) + '</span>' +
						'<button class="bq-cart-line-remove" aria-label="Remove item">&times;</button>' +
						'</div>'
					);
				} )
				.join( '' );
			body.innerHTML = rows;
		}

		var totalEl = document.getElementById( 'bq-cart-total' );
		if ( totalEl ) {
			totalEl.textContent = formatMoney( estimateTotal( cart ) );
		}

		var shortageEl = document.getElementById( 'bq-cart-shortage' );
		if ( shortageEl ) {
			shortageEl.hidden = ! anyLineAtLimit( cart );
		}
	}

	function showError( message ) {
		var el = document.getElementById( 'bq-cart-error' );
		if ( ! el ) {
			return;
		}
		el.textContent = message;
		el.hidden = false;
		window.setTimeout( function () {
			el.hidden = true;
		}, 5000 );
	}

	function openDrawer() {
		var drawer = document.getElementById( 'bq-cart-drawer' );
		if ( drawer ) {
			drawer.classList.add( 'is-open' );
		}
	}

	// Three mutually exclusive views inside the drawer: the cart itself,
	// the checkout form, and the post-checkout confirmation screen.
	function showStep( step ) {
		var view = document.getElementById( 'bq-cart-view' );
		var panel = document.getElementById( 'bq-checkout-panel' );
		var confirmation = document.getElementById( 'bq-checkout-confirmation' );
		if ( view ) {
			view.hidden = step !== 'cart';
		}
		if ( panel ) {
			panel.hidden = step !== 'checkout';
		}
		if ( confirmation ) {
			confirmation.hidden = step !== 'confirmation';
		}
	}

	// Background-only: refines a line's known `max` against the cart's
	// current dates. Never awaited by anything the visitor is looking at —
	// by the time this resolves the item is already sitting in the cart.
	function refreshAvailability( productId ) {
		var cart = loadCart();
		var line = findLine( cart, productId );
		if ( ! line ) {
			return;
		}
		request(
			'GET',
			'/availability?product_id=' + encodeURIComponent( productId ) +
				'&from=' + encodeURIComponent( cart.starts_at + 'T00:00:00Z' ) +
				'&till=' + encodeURIComponent( cart.stops_at + 'T23:59:59Z' )
		)
			.then( function ( result ) {
				var fresh = loadCart();
				var freshLine = findLine( fresh, productId );
				if ( ! freshLine ) {
					return; // removed while the check was in flight
				}
				freshLine.max = 'number' === typeof result.available ? result.available : null;
				if ( null !== freshLine.max && freshLine.quantity > freshLine.max ) {
					freshLine.quantity = Math.max( 0, freshLine.max );
					if ( 0 === freshLine.quantity ) {
						fresh.lines = fresh.lines.filter( function ( l ) {
							return l.product_id !== productId;
						} );
						showError( 'Sorry, "' + freshLine.title + '" just sold out for these dates.' );
					} else {
						showError( 'Only ' + freshLine.max + ' left of "' + freshLine.title + '" — quantity adjusted.' );
					}
				}
				saveCart( fresh );
				render( fresh );
			} )
			.catch( function () {
				/* stock check failed silently — line keeps its last-known max, still re-verified for real at checkout */
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		if ( event.target.closest( '#bq-cart-toggle' ) ) {
			var drawer = document.getElementById( 'bq-cart-drawer' );
			if ( drawer ) {
				drawer.classList.toggle( 'is-open' );
			}
			return;
		}

		if ( event.target.closest( '#bq-cart-close' ) ) {
			var d = document.getElementById( 'bq-cart-drawer' );
			if ( d ) {
				d.classList.remove( 'is-open' );
			}
			return;
		}

		if ( event.target.closest( '#bq-cart-checkout' ) ) {
			if ( ! loadCart().lines.length ) {
				showError( 'Your cart is empty.' );
				return;
			}
			showStep( 'checkout' );
			return;
		}

		if ( event.target.closest( '#bq-checkout-back' ) ) {
			showStep( 'cart' );
			return;
		}

		if ( event.target.closest( '#bq-checkout-done' ) ) {
			[ 'bq-checkout-name', 'bq-checkout-email', 'bq-checkout-phone' ].forEach( function ( id ) {
				var field = document.getElementById( id );
				if ( field ) {
					field.value = '';
				}
			} );
			showStep( 'cart' );
			render( loadCart() );
			// The booking is done — close the drawer instead of leaving it
			// open on an now-empty cart view.
			var doneDrawer = document.getElementById( 'bq-cart-drawer' );
			if ( doneDrawer ) {
				doneDrawer.classList.remove( 'is-open' );
			}
			return;
		}

		if ( event.target.closest( '#bq-checkout-submit' ) ) {
			if ( checkoutBusy ) {
				return;
			}
			var nameEl = document.getElementById( 'bq-checkout-name' );
			var emailEl = document.getElementById( 'bq-checkout-email' );
			var phoneEl = document.getElementById( 'bq-checkout-phone' );

			if ( ! nameEl.value.trim() || ! emailEl.value.trim() ) {
				showError( 'Please fill in your name and email.' );
				return;
			}

			var cart = loadCart();
			if ( ! cart.lines.length ) {
				showError( 'Your cart is empty.' );
				return;
			}

			checkoutBusy = true;
			var drawerEl = document.getElementById( 'bq-cart-drawer' );
			if ( drawerEl ) {
				drawerEl.classList.add( 'is-busy' );
			}

			// This is the one real Booqable round trip in the whole cart
			// flow — the visitor already expects a short wait at this exact
			// point (a "Processing your booking..." moment), unlike a qty
			// click while just browsing.
			request( 'POST', '/checkout', {
				name: nameEl.value.trim(),
				email: emailEl.value.trim(),
				phone: phoneEl ? phoneEl.value.trim() : '',
				starts_at: cart.starts_at + 'T00:00:00Z',
				stops_at: cart.stops_at + 'T23:59:59Z',
				lines: cart.lines.map( function ( line ) {
					return { product_id: line.product_id, quantity: line.quantity };
				} ),
			} )
				.then( function ( result ) {
					var numberEl = document.getElementById( 'bq-checkout-order-number' );
					var totalEl = document.getElementById( 'bq-checkout-order-total' );
					var instructionsEl = document.getElementById( 'bq-checkout-instructions' );
					var shortageEl = document.getElementById( 'bq-checkout-shortage' );
					if ( numberEl ) {
						numberEl.textContent = result.order_number;
					}
					if ( totalEl ) {
						totalEl.textContent = result.total;
					}
					if ( instructionsEl ) {
						instructionsEl.innerHTML = result.payment_instructions || '';
					}
					if ( shortageEl ) {
						shortageEl.hidden = ! result.shortage;
					}
					// The order is real now — start fresh for any further browsing.
					saveCart( defaultCart() );
					showStep( 'confirmation' );
				} )
				.catch( function ( error ) {
					showError( error.message );
				} )
				.finally( function () {
					checkoutBusy = false;
					if ( drawerEl ) {
						drawerEl.classList.remove( 'is-busy' );
					}
				} );
			return;
		}

		var addBtn = event.target.closest( '[data-bq-add-to-cart]' );
		if ( addBtn ) {
			var productId = addBtn.getAttribute( 'data-product-id' );
			var title = addBtn.getAttribute( 'data-title' ) || '';
			var priceInCents = parseInt( addBtn.getAttribute( 'data-price-in-cents' ), 10 ) || 0;

			// Everything below is synchronous, local state — the item is in
			// the cart and the drawer is open before anything touches the
			// network. The stock check that follows only refines `max` in
			// the background; it never gates this.
			openDrawer();

			var cart = loadCart();
			var line = findLine( cart, productId );
			if ( line ) {
				if ( null !== line.max && line.quantity >= line.max ) {
					showError( 'Only ' + line.max + ' left of "' + line.title + '".' );
				} else {
					line.quantity += 1;
				}
			} else {
				cart.lines.push( {
					product_id: productId,
					title: title,
					price_in_cents: priceInCents,
					quantity: 1,
					max: null, // learned in the background below
				} );
			}
			saveCart( cart );
			render( cart );
			refreshAvailability( productId );
			return;
		}

		var line = event.target.closest( '.bq-cart-line' );
		if ( ! line ) {
			return;
		}
		var productId = line.getAttribute( 'data-product-id' );
		var cart = loadCart();
		var cartLine = findLine( cart, productId );
		if ( ! cartLine ) {
			return;
		}

		if ( event.target.closest( '.bq-qty-inc' ) ) {
			if ( null !== cartLine.max && cartLine.quantity >= cartLine.max ) {
				showError( 'Only ' + cartLine.max + ' left of "' + cartLine.title + '".' );
				return;
			}
			cartLine.quantity += 1;
		} else if ( event.target.closest( '.bq-qty-dec' ) ) {
			if ( cartLine.quantity <= 1 ) {
				return;
			}
			cartLine.quantity -= 1;
		} else if ( event.target.closest( '.bq-cart-line-remove' ) ) {
			cart.lines = cart.lines.filter( function ( l ) {
				return l.product_id !== productId;
			} );
		} else {
			return;
		}

		saveCart( cart );
		render( cart );
	} );

	document.addEventListener( 'change', function ( event ) {
		if ( event.target.id !== 'bq-cart-starts' && event.target.id !== 'bq-cart-stops' ) {
			return;
		}
		var startsEl = document.getElementById( 'bq-cart-starts' );
		var stopsEl = document.getElementById( 'bq-cart-stops' );
		if ( ! startsEl || ! stopsEl || ! startsEl.value || ! stopsEl.value ) {
			return;
		}

		// Booqable rejects stops_at <= starts_at outright at checkout —
		// rather than surface that as an error this far upstream, keep the
		// range valid automatically by nudging whichever field the visitor
		// didn't just touch (a visitor moving just their start date later is
		// a completely normal interaction, not a mistake).
		if ( stopsEl.value <= startsEl.value ) {
			if ( event.target.id === 'bq-cart-stops' ) {
				var s = new Date( stopsEl.value + 'T00:00:00Z' );
				s.setUTCDate( s.getUTCDate() - 1 );
				startsEl.value = isoDate( s );
			} else {
				var e = new Date( startsEl.value + 'T00:00:00Z' );
				e.setUTCDate( e.getUTCDate() + 1 );
				stopsEl.value = isoDate( e );
			}
		}

		var cart = loadCart();
		cart.starts_at = startsEl.value;
		cart.stops_at = stopsEl.value;
		saveCart( cart );
		render( cart );

		// Dates changed, so every line's last-known `max` is potentially
		// stale — re-check each in the background (still non-blocking; the
		// dates themselves already updated above with no wait).
		cart.lines.forEach( function ( l ) {
			refreshAvailability( l.product_id );
		} );
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		render( loadCart() );
	} );
} )();
