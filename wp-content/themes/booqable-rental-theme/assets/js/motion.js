/**
 * Staging Co. — motion layer.
 * Only ever queries `.sc-*` classes we own. Never touches `.booqable-*`
 * markup — that DOM is owned and hydrated by the Booqable widget script.
 */
(function () {
	"use strict";

	document.addEventListener( "DOMContentLoaded", function () {
		if ( window.gsap && window.ScrollTrigger ) {
			gsap.registerPlugin( ScrollTrigger );
			initPinnedGallery();
			initBentoHover();
		}
		initAccordion();
		initCarousel();
	} );

	/**
	 * Desire section: pin the left title while the before/after gallery
	 * scrolls; each image scales up + brightens into view, then fades/
	 * darkens as it scrolls past.
	 */
	function initPinnedGallery() {
		var section = document.querySelector( ".sc-pin-section" );
		if ( ! section ) {
			return;
		}

		var col = section.querySelector( ".sc-pin-col" );
		if ( col ) {
			ScrollTrigger.create( {
				trigger: section,
				start: "top 96px",
				end: "bottom bottom",
				pin: col,
				pinSpacing: false,
			} );
		}

		document.querySelectorAll( ".sc-pin-item" ).forEach( function ( item ) {
			gsap.fromTo(
				item,
				{ opacity: 0.25, scale: 0.85 },
				{
					opacity: 1,
					scale: 1,
					ease: "none",
					scrollTrigger: {
						trigger: item,
						start: "top 85%",
						end: "top 40%",
						scrub: true,
					},
				}
			);

			gsap.to( item, {
				opacity: 0.2,
				scale: 0.92,
				ease: "none",
				scrollTrigger: {
					trigger: item,
					start: "bottom 35%",
					end: "bottom -10%",
					scrub: true,
				},
			} );
		} );
	}

	/**
	 * Subtle hover physics for the bento cards.
	 */
	function initBentoHover() {
		document.querySelectorAll( ".sc-bento-card" ).forEach( function ( card ) {
			card.addEventListener( "mouseenter", function () {
				gsap.to( card, { scale: 1.015, duration: 0.5, ease: "power2.out" } );
			} );
			card.addEventListener( "mouseleave", function () {
				gsap.to( card, { scale: 1, duration: 0.5, ease: "power2.out" } );
			} );
		} );
	}

	/**
	 * Horizontal accordion: click/focus a slice to expand it, closing siblings.
	 */
	function initAccordion() {
		document.querySelectorAll( "[data-sc-accordion]" ).forEach( function ( group ) {
			var slices = group.querySelectorAll( ".sc-accordion-slice" );
			slices.forEach( function ( slice ) {
				slice.addEventListener( "click", function () {
					slices.forEach( function ( s ) {
						s.classList.toggle( "is-open", s === slice );
					} );
				} );
			} );
		} );
	}

	/**
	 * Testimonial carousel: simple prev/next cross-fade, auto-advances.
	 */
	function initCarousel() {
		document.querySelectorAll( "[data-sc-carousel]" ).forEach( function ( carousel ) {
			var quotes = carousel.querySelectorAll( ".sc-quote" );
			if ( ! quotes.length ) {
				return;
			}
			var index = 0;

			function show( next ) {
				quotes[ index ].classList.remove( "is-active" );
				index = ( next + quotes.length ) % quotes.length;
				quotes[ index ].classList.add( "is-active" );
			}

			var prevBtn = carousel.querySelector( ".sc-carousel-prev" );
			var nextBtn = carousel.querySelector( ".sc-carousel-next" );
			if ( prevBtn ) {
				prevBtn.addEventListener( "click", function () {
					show( index - 1 );
				} );
			}
			if ( nextBtn ) {
				nextBtn.addEventListener( "click", function () {
					show( index + 1 );
				} );
			}

			setInterval( function () {
				show( index + 1 );
			}, 7000 );
		} );
	}
} )();
