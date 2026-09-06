(function ($) {
	if (typeof Journal['filterSortExplicit'] === 'undefined') {
		try {
			var initialProductsUrl = new URL(window.location.href);
			var initialSort = initialProductsUrl.searchParams.get('sort');
			var initialOrder = initialProductsUrl.searchParams.get('order');

			Journal['filterSortExplicit'] = initialProductsUrl.searchParams.has('sort') && !(initialSort === 'p.sort_order' && initialOrder === 'ASC');
		} catch (e) {
			Journal['filterSortExplicit'] = false;
		}
	}

	function infiniteScrollHistoryUrl(url) {
		var prettyUrl = $('.module-filter').attr('data-pretty-url');

		try {
			var requestUrl = new URL(url, window.location.href);

			requestUrl.host = window.location.host;
			requestUrl.hostname = window.location.hostname;
			requestUrl.protocol = window.location.protocol;

			if (!prettyUrl) {
				return requestUrl.toString();
			}

			var historyUrl = new URL(prettyUrl, window.location.href);
			var copiedParams = {};

			// fa/fo/ff and fm are represented by path segments in prettyUrl.
			// Keep every other pagination/sort/filter parameter from the URL
			// used for the AJAX request, without putting the raw SEO filters
			// back into the address bar.
			requestUrl.searchParams.forEach(function (value, key) {
				if (copiedParams[key] || key === 'route' || key === 'path' || key === '_route_' || key === 'fm' || /^(fa|fo|ff)\d+$/.test(key) || ((key === 'sort' || key === 'order') && !Journal['filterSortExplicit'])) {
					return;
				}

				copiedParams[key] = true;
				historyUrl.searchParams.delete(key);
				requestUrl.searchParams.getAll(key).forEach(function (item) {
					historyUrl.searchParams.append(key, item);
				});
			});

			return historyUrl.toString();
		} catch (e) {
			return url;
		}
	}

	// Grid / List toggle
	$(document).on('click', '.grid-list .view-btn', function () {
		const $this = $(this);
		const $products = $('.main-products');
		const view = $this.data('view');
		const current = $products.hasClass('product-grid') ? 'grid' : 'list';

		$this.tooltip('hide');

		if (view !== current) {
			$products.addClass('no-transitions').removeClass('product-' + current).addClass('product-' + view);

			setTimeout(function () {
				$products.removeClass('no-transitions');
			}, 1);

			const d = new Date;
			d.setTime(d.getTime() + 24 * 60 * 60 * 1000 * 365);

			if (view === 'list') {
				document.cookie = 'view=list;path=/;expires=' + d.toGMTString();
			} else {
				document.cookie = 'view=grid;path=/;expires=' + d.toGMTString();
			}
		}

		$('.grid-list .view-btn').removeClass('active');
		$this.addClass('active');
	});

	// Sort / Limit handler
	$(document).on('change', '.main-products-wrapper .select-group select', function () {
		if (!window['journal_filter']) {
			window.location = $(this).val();
		}
	});

	// Infinite Scroll
	$(function () {
		if (Journal['infiniteScrollStatus'] && $('.main-products').length) {
			Journal['infiniteScrollInstance'] = $.ias({
				container: '.main-products',
				item: '.product-layout',
				pagination: '.pagination-results',
				next: '.pagination a.next'
			});

			Journal['infiniteScrollInstance'].extension(new IASTriggerExtension({
				offset: parseInt(Journal['infiniteScrollOffset'], 10) || Infinity,
				text: Journal['infiniteScrollLoadNext'],
				textPrev: Journal['infiniteScrollLoadPrev'],
				htmlPrev: '<div class="ias-trigger ias-trigger-prev"><a class="btn">{text}</a></div>',
				html: '<div class="ias-trigger ias-trigger-next"><a class="btn">{text}</a></div>'
			}));

			Journal['infiniteScrollInstance'].extension(new IASSpinnerExtension({
				html: '<div class="ias-spinner"><em class="fa fa-spinner fa-spin"></em></div>'
			}));

			Journal['infiniteScrollInstance'].extension(new IASNoneLeftExtension({
				text: Journal['infiniteScrollNoneLeft']
			}));

			Journal['infiniteScrollInstance'].extension(new IASPagingExtension());

			Journal['infiniteScrollInstance'].extension(new IASHistoryExtension({
				prev: '.pagination a.prev'
			}));

			// IASHistoryExtension writes the raw pagination request URL (for
			// example ?page=2&fo11=52) into history. Replace it with the same
			// page represented on the filter's SEO path so scrolling back to
			// page 1, and reloading at any page, retain the active filters.
			Journal['infiniteScrollInstance'].on('pageChange', function (page, scrollOffset, url) {
				if (!window.history || !window.history.replaceState) {
					return;
				}

				var historyUrl = infiniteScrollHistoryUrl(url);
				var state = $.extend({}, window.history.state || {}, {
					Title: document.title,
					Url: historyUrl
				});

				window.history.replaceState(state, document.title, historyUrl);
			}, -100);

			Journal['infiniteScrollInstance'].on('load', function (event) {
				try {
					var u = new URL(event.url);

					u.host = window.location.host;
					u.hostname = window.location.hostname;
					u.protocol = window.location.protocol;

					event.url = u.toString();
				} catch (e) {
				}
			});

			Journal['infiniteScrollInstance'].on('loaded', function (data) {
				$('.pagination-results').html($(data).find('.pagination-results'));
			});

			Journal['infiniteScrollInstance'].on('rendered', function (data) {
				Journal.lazy();
			});
		}
	});

})(jQuery);
