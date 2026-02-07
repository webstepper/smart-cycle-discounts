/**
 * WooCommerce Blocks Checkout Integration
 *
 * Registers checkout filters to display strikethrough pricing for discounted
 * items in block-based cart and checkout pages.
 *
 * @package    WSSCD_Plugin
 * @since      1.5.70
 */

( function() {
	'use strict';

	/**
	 * Guard clause - exit if WooCommerce Blocks checkout filters not available
	 */
	if ( ! window.wc || ! window.wc.blocksCheckout || ! window.wc.blocksCheckout.registerCheckoutFilters ) {
		console.warn( '[Smart Cycle Discounts] WooCommerce Blocks checkout filters not available' );
		return;
	}

	/**
	 * Format price using WooCommerce currency settings
	 *
	 * @param {number} price Raw price value
	 * @return {string} Formatted price string
	 */
	function formatPrice( price ) {
		// Fallback to basic formatting if localized data not available.
		if ( ! window.wsscdBlocksData || ! window.wsscdBlocksData.currency ) {
			return '$' + price.toFixed( 2 );
		}

		var currency = window.wsscdBlocksData.currency;
		var locale = window.wsscdBlocksData.locale || { userLocale: 'en-US' };

		try {
			// Use Intl.NumberFormat for proper currency formatting.
			var formatter = new Intl.NumberFormat( locale.userLocale, {
				style: 'currency',
				currency: currency.code,
				minimumFractionDigits: parseInt( currency.decimals, 10 ) || 2,
				maximumFractionDigits: parseInt( currency.decimals, 10 ) || 2
			} );

			return formatter.format( price );
		} catch ( error ) {
			// Fallback to manual formatting if Intl fails.
			console.warn( '[SCD Blocks] Currency formatting error:', error );

			var formatted = price.toFixed( parseInt( currency.decimals, 10 ) || 2 );

			// Apply thousand separator.
			if ( currency.thousand_separator ) {
				var parts = formatted.split( '.' );
				parts[0] = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, currency.thousand_separator );
				formatted = parts.join( currency.decimal_separator || '.' );
			}

			// Apply currency symbol.
			var symbol = currency.symbol || currency.code;
			if ( currency.price_format ) {
				formatted = currency.price_format.replace( '%1$s', symbol ).replace( '%2$s', formatted );
			} else {
				formatted = symbol + formatted;
			}

			return formatted;
		}
	}

	/**
	 * Cart item price filter
	 *
	 * Displays strikethrough pricing for discounted items.
	 *
	 * @param {string} price     Default price HTML
	 * @param {Object} extensions Extension data from Store API
	 * @param {Object} args      Additional arguments
	 * @return {string} Modified price HTML
	 */
	function cartItemPriceFilter( price, extensions, args ) {
		// Guard clause - exit if no discount data.
		if ( ! extensions || ! extensions.wsscd || ! extensions.wsscd.has_discount ) {
			return price;
		}

		var discountData = extensions.wsscd;

		// Validate required data.
		if ( ! discountData.original_price || ! discountData.discounted_price ) {
			console.warn( '[SCD Blocks] Invalid discount data:', discountData );
			return price;
		}

		// Ensure prices are valid numbers.
		var originalPrice = parseFloat( discountData.original_price );
		var discountedPrice = parseFloat( discountData.discounted_price );

		if ( isNaN( originalPrice ) || isNaN( discountedPrice ) ) {
			console.warn( '[SCD Blocks] Invalid price values:', discountData );
			return price;
		}

		// Verify discount is actually applied (discounted < original).
		if ( discountedPrice >= originalPrice ) {
			return price;
		}

		// Format prices.
		var originalFormatted = formatPrice( originalPrice );
		var discountedFormatted = formatPrice( discountedPrice );

		// Return strikethrough HTML.
		return '<del>' + originalFormatted + '</del> <ins>' + discountedFormatted + '</ins>';
	}

	/**
	 * Cart item class filter
	 *
	 * Adds CSS class to discounted items for styling.
	 *
	 * @param {string} className Default class name
	 * @param {Object} extensions Extension data from Store API
	 * @return {string} Modified class name
	 */
	function cartItemClassFilter( className, extensions ) {
		// Add class if item has discount.
		if ( extensions && extensions.wsscd && extensions.wsscd.has_discount ) {
			return className + ' wsscd-discounted-item';
		}

		return className;
	}

	/**
	 * Register checkout filters
	 */
	try {
		window.wc.blocksCheckout.registerCheckoutFilters( 'smart-cycle-discounts', {
			cartItemPrice: cartItemPriceFilter,
			cartItemClass: cartItemClassFilter
		} );
	} catch ( error ) {
		console.error( '[SCD Blocks] Failed to register checkout filters:', error );
	}

} )();
