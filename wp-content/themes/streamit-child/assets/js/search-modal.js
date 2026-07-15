/**
 * Fullscreen search modal — opens on header search icon click.
 */
(function ($) {
	'use strict';

	var config = window.streamitChildSearch || {};
	var debounceTimer = null;

	function setResults(html) {
		$('#streamit-search-modal-results').html(html);
	}

	function showHint() {
		setResults(
			'<div class="streamit-search-modal__hint">' + (config.hint || '') + '</div>'
		);
	}

	function showLoading() {
		setResults(
			'<div class="streamit-search-modal__loading"><span class="spinner-border text-primary" role="status" aria-hidden="true"></span><span class="ms-2">' + (config.loading || 'در حال جستجو...') + '</span></div>'
		);
	}

	function runSearch(query) {
		if (!query || query.length < 2) {
			showHint();
			return;
		}

		showLoading();

		$.ajax({
			url: config.ajaxurl,
			type: 'GET',
			data: {
				action: 'streamit_child_modal_search',
				nonce: config.nonce,
				s: query,
			},
		})
			.done(function (response) {
				if (response && response.success && response.data) {
					setResults(response.data);
				} else {
					setResults(
						'<div class="streamit-search-modal__empty">' + (config.empty || '') + '</div>'
					);
				}
			})
			.fail(function () {
				setResults(
					'<div class="streamit-search-modal__empty">' + (config.error || '') + '</div>'
				);
			});
	}

	function getBootstrapModal() {
		var el = document.getElementById('streamit-search-modal');
		if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
			return null;
		}

		return bootstrap.Modal.getOrCreateInstance(el, {
			backdrop: true,
			keyboard: true,
		});
	}

	function openModalManual() {
		var el = document.getElementById('streamit-search-modal');
		if (!el) {
			return;
		}

		el.classList.add('show');
		el.style.display = 'block';
		el.setAttribute('aria-modal', 'true');
		el.removeAttribute('aria-hidden');

		document.body.classList.add('modal-open');
		document.body.style.overflow = 'hidden';

		if (!document.querySelector('.streamit-search-modal-backdrop')) {
			var backdrop = document.createElement('div');
			backdrop.className = 'modal-backdrop fade show streamit-search-modal-backdrop';
			document.body.appendChild(backdrop);
		}

		showHint();
		setTimeout(function () {
			var input = document.getElementById('streamit-search-modal-input');
			if (input) {
				input.focus();
			}
		}, 100);
	}

	function closeModalManual() {
		var el = document.getElementById('streamit-search-modal');
		if (!el) {
			return;
		}

		el.classList.remove('show');
		el.style.display = 'none';
		el.setAttribute('aria-hidden', 'true');
		el.removeAttribute('aria-modal');

		document.body.classList.remove('modal-open');
		document.body.style.overflow = '';

		var backdrop = document.querySelector('.streamit-search-modal-backdrop');
		if (backdrop) {
			backdrop.remove();
		}

		$('#streamit-search-modal-input').val('');
		setResults('');
	}

	function openModal() {
		$('#header_search_input').removeClass('show');
		$('.search_result_section').empty();

		var modal = getBootstrapModal();
		if (modal) {
			modal.show();
			return;
		}

		var trigger = document.getElementById('streamit-search-modal-open');
		if (trigger) {
			trigger.click();
			return;
		}

		openModalManual();
	}

	function bind() {
		var $modal = $('#streamit-search-modal');
		var $input = $('#streamit-search-modal-input');
		var $trigger = $('#st-search-drop');

		if (!$modal.length || !$trigger.length) {
			return;
		}

		$trigger.attr({
			'data-bs-toggle': 'modal',
			'data-bs-target': '#streamit-search-modal',
		});

		document.addEventListener(
			'click',
			function (event) {
				if (!event.target.closest('#st-search-drop')) {
					return;
				}

				event.preventDefault();
				event.stopPropagation();
				openModal();
			},
			true
		);

		$modal.on('shown.bs.modal', function () {
			$input.val('');
			showHint();
			setTimeout(function () {
				$input.trigger('focus');
			}, 100);
		});

		$modal.on('hidden.bs.modal', function () {
			$input.val('');
			setResults('');
		});

		$modal.find('[data-bs-dismiss="modal"]').on('click', function () {
			if (!getBootstrapModal()) {
				closeModalManual();
			}
		});

		$(document).on('click', '.streamit-search-modal-backdrop', closeModalManual);

		$(document).on('keydown', function (event) {
			if (event.key === 'Escape' && $modal.hasClass('show') && !getBootstrapModal()) {
				closeModalManual();
			}
		});

		$input.on('input', function () {
			var query = $(this).val().trim();
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(function () {
				runSearch(query);
			}, 350);
		});
	}

	function init() {
		bind();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	window.addEventListener('load', init);
})(jQuery);
