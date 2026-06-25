jQuery(document).ready(function ($) {
	'use strict';

	// Cache DOM elements.
	const $tabsNav         = $('.discogs-tabs-nav .nav-tab');
	const $tabContents     = $('.discogs-tab-content');
	const $searchForm      = $('#discogs-search-form');
	const $searchQuery     = $('#discogs-search-query');
	const $searchField     = $('#discogs-search-field');
	const $resultsHeader   = $('.discogs-results-header');
	const $resultsTitle    = $('#discogs-results-title');
	const $resultsCount    = $('#discogs-results-count');
	const $resultsGrid     = $('#discogs-search-results');
	const $pagination      = $('#discogs-pagination');
	const $clearBtn        = $('#discogs-clear-search');
	const $importType      = $('#discogs_importer_import_type');
	const $wcOnlyRow       = $('.woocommerce-only-row');
	
	// Progress Bar elements.
	const $progressContainer = $('#discogs-import-progress-container');
	const $progressStatus    = $('#discogs-progress-status');
	const $progressPercent   = $('#discogs-progress-percent');
	const $progressBar       = $('#discogs-progress-bar');

	let currentSearchQuery = '';
	let currentSearchField = 'q';
	let currentPage        = 1;
	let progressInterval   = null;

	/* ==========================================
	 * Tab Navigation
	 * ========================================== */
	$tabsNav.on('click', function (e) {
		e.preventDefault();
		const targetTab = $(this).data('tab');

		// Update Active Tab Class.
		$tabsNav.removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		// Toggle Content.
		$tabContents.removeClass('tab-active');
		$(`#discogs-tab-${targetTab}`).addClass('tab-active');

		// Update URL hash.
		window.location.hash = 'tab-' + targetTab;
	});

	// Handle initial URL hash on page load.
	const hash = window.location.hash;
	if (hash) {
		const tabName = hash.replace('#tab-', '');
		const $matchedTab = $(`.discogs-tabs-nav .nav-tab[data-tab="${tabName}"]`);
		if ($matchedTab.length) {
			$matchedTab.trigger('click');
		}
	}

	/* ==========================================
	 * Settings Logic
	 * ========================================== */
	$importType.on('change', function () {
		if ($(this).val() === 'woocommerce') {
			$wcOnlyRow.slideDown(200);
		} else {
			$wcOnlyRow.slideUp(200);
		}
	});

	/* ==========================================
	 * Search Operations
	 * ========================================== */
	$searchForm.on('submit', function (e) {
		e.preventDefault();
		currentSearchQuery = $searchQuery.val().trim();
		currentSearchField = $searchField.val();
		currentPage = 1;

		if (!currentSearchQuery) {
			alert('Please enter a search term.');
			return;
		}

		performSearch();
	});

	$clearBtn.on('click', function () {
		$searchQuery.val('');
		$resultsGrid.empty();
		$pagination.empty().hide();
		$resultsHeader.hide();
		$(this).hide();
	});

	function performSearch() {
		// Show skeleton loaders.
		renderSkeletons();
		$resultsHeader.show();
		$resultsTitle.text('Searching...');
		$resultsCount.hide();
		$pagination.hide();
		$clearBtn.show();

		$.ajax({
			url: discogsImporter.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'discogs_importer_search',
				nonce: discogsImporter.nonce,
				q: currentSearchQuery,
				search_field: currentSearchField,
				page: currentPage
			},
			success: function (response) {
				if (response.success) {
					renderResults(response.data);
				} else {
					renderError(response.data || 'An error occurred while fetching search results.');
				}
			},
			error: function (xhr, status, error) {
				renderError('Connection error: ' + error);
			}
		});
	}

	function renderSkeletons() {
		$resultsGrid.empty();
		for (let i = 0; i < 4; i++) {
			const skeletonHtml = `
				<div class="discogs-shimmer-loader">
					<div class="shimmer-block shimmer-image"></div>
					<div class="shimmer-block shimmer-title"></div>
					<div class="shimmer-block shimmer-text"></div>
					<div class="shimmer-block shimmer-text"></div>
					<div class="shimmer-block shimmer-btn"></div>
				</div>
			`;
			$resultsGrid.append(skeletonHtml);
		}
	}

	function renderError(message) {
		$resultsHeader.show();
		$resultsTitle.text('No Results');
		$resultsCount.hide();
		$resultsGrid.html(`
			<div class="discogs-alert discogs-alert-warning" style="grid-column: 1 / -1; width: 100%;">
				<p>${message}</p>
			</div>
		`);
		$pagination.hide();
	}

	function renderResults(data) {
		const results = data.results || [];
		const pagination = data.pagination || {};

		$resultsGrid.empty();
		$resultsHeader.show();
		$resultsTitle.text('Search Results');
		
		if (pagination.items !== undefined) {
			$resultsCount.text(pagination.items + ' found').show();
		} else {
			$resultsCount.hide();
		}

		if (results.length === 0) {
			renderError('No releases matched your search query.');
			return;
		}

		results.forEach(function (release) {
			// Extract title and artist.
			let title = release.title || 'Untitled';
			let artist = 'Unknown Artist';
			
			// Discogs database search results return "Artist - Title" inside the title string.
			if (title.indexOf(' - ') !== -1) {
				const parts = title.split(' - ');
				artist = parts[0].trim();
				title = parts.slice(1).join(' - ').trim();
			}

			// Clean artist name.
			artist = artist.replace(/\s\(\d+\)$/, '');

			// Formats.
			const format = (release.format && release.format.length) ? release.format.join(', ') : 'Vinyl';
			const label = (release.label && release.label.length) ? release.label[0].replace(/\s\(\d+\)$/, '') : 'Unknown Label';
			const catno = release.catno || 'N/A';
			const year = release.year || 'N/A';
			const country = release.country || 'N/A';
			const thumb = release.thumb || 'dashicons-format-audio'; // Fallback
			
			let imageHtml = '';
			if (thumb.startsWith('http')) {
				imageHtml = `<img src="${thumb}" alt="${title}" loading="lazy" />`;
			} else {
				imageHtml = `<span class="dashicons dashicons-format-audio fallback-icon"></span>`;
			}

			const cardHtml = `
				<div class="discogs-card" id="release-card-${release.id}">
					<div class="discogs-card-image">
						${imageHtml}
						<span class="discogs-card-badge">${format}</span>
					</div>
					<div class="discogs-card-content">
						<h4 class="discogs-card-title" title="${title}">${title}</h4>
						<div class="discogs-card-artist">${artist}</div>
						<div class="discogs-card-details">
							<div><strong>Label:</strong> ${label}</div>
							<div><strong>Cat #:</strong> ${catno}</div>
							<div><strong>Year:</strong> ${year}</div>
							<div><strong>Country:</strong> ${country}</div>
						</div>
						<div class="discogs-card-action">
							<button class="button button-primary discogs-btn import-btn" data-id="${release.id}">
								<span class="dashicons dashicons-download"></span> Import Release
							</button>
						</div>
					</div>
				</div>
			`;
			$resultsGrid.append(cardHtml);
		});

		setupPagination(pagination);
	}

	/* ==========================================
	 * Pagination Handler
	 * ========================================== */
	function setupPagination(pag) {
		$pagination.empty();

		const totalPages = pag.pages || 1;
		if (totalPages <= 1) {
			$pagination.hide();
			return;
		}

		$pagination.show();

		// Previous Page.
		const $prevBtn = $('<button class="discogs-pagination-btn" ' + (currentPage === 1 ? 'disabled' : '') + '>Prev</button>');
		$prevBtn.on('click', function () {
			if (currentPage > 1) {
				currentPage--;
				performSearch();
			}
		});
		$pagination.append($prevBtn);

		// Page Numbers (Simple window strategy around current page).
		const startPage = Math.max(1, currentPage - 2);
		const endPage = Math.min(totalPages, currentPage + 2);

		if (startPage > 1) {
			const $firstBtn = $('<button class="discogs-pagination-btn">1</button>');
			$firstBtn.on('click', function () { currentPage = 1; performSearch(); });
			$pagination.append($firstBtn);
			if (startPage > 2) {
				$pagination.append('<span class="pagination-ellipsis">...</span>');
			}
		}

		for (let i = startPage; i <= endPage; i++) {
			const $pageBtn = $(`<button class="discogs-pagination-btn ${i === currentPage ? 'active' : ''}">${i}</button>`);
			$pageBtn.on('click', function () {
				currentPage = i;
				performSearch();
			});
			$pagination.append($pageBtn);
		}

		if (endPage < totalPages) {
			if (endPage < totalPages - 1) {
				$pagination.append('<span class="pagination-ellipsis">...</span>');
			}
			const $lastBtn = $(`<button class="discogs-pagination-btn">${totalPages}</button>`);
			$lastBtn.on('click', function () { currentPage = totalPages; performSearch(); });
			$pagination.append($lastBtn);
		}

		// Next Page.
		const $nextBtn = $('<button class="discogs-pagination-btn" ' + (currentPage === totalPages ? 'disabled' : '') + '>Next</button>');
		$nextBtn.on('click', function () {
			if (currentPage < totalPages) {
				currentPage++;
				performSearch();
			}
		});
		$pagination.append($nextBtn);
	}

	/* ==========================================
	 * Import Execution
	 * ========================================== */
	$resultsGrid.on('click', '.import-btn', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const releaseId = $btn.data('id');
		const $card = $(`#release-card-${releaseId}`);

		// Disable all buttons in grid to prevent overlapping queries.
		$('.import-btn').prop('disabled', true);
		$searchForm.find('input, select, button').prop('disabled', true);

		// Start Progress bar simulation.
		startProgressSimulation();

		$.ajax({
			url: discogsImporter.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'discogs_importer_import',
				nonce: discogsImporter.nonce,
				release_id: releaseId
			},
			success: function (response) {
				stopProgressSimulation();
				
				if (response.success) {
					// Show imported overlay on card.
					$card.addClass('already-imported');
					const successOverlayHtml = `
						<div class="import-success-overlay">
							<span class="dashicons dashicons-yes-alt success-icon"></span>
							<h4>Imported Successfully!</h4>
							<p style="font-size: 13px; color:#475569; margin: 0 0 15px 0;">${response.data.title}</p>
							<div class="import-links">
								<a href="${response.data.edit_url}" class="edit-btn" target="_blank">Edit Product</a>
								<a href="${response.data.view_url}" class="view-btn" target="_blank">View Post</a>
							</div>
						</div>
					`;
					$card.append(successOverlayHtml);
				} else {
					alert(response.data || 'Import failed.');
				}
			},
			error: function (xhr, status, error) {
				stopProgressSimulation();
				alert('Connection error during import: ' + error);
			},
			complete: function () {
				// Re-enable import buttons that are not already imported.
				$('.import-btn').each(function () {
					const $currentBtn = $(this);
					const currentId = $currentBtn.data('id');
					if (!$(`#release-card-${currentId}`).hasClass('already-imported')) {
						$currentBtn.prop('disabled', false);
					}
				});
				// Re-enable search form inputs.
				$searchForm.find('input, select, button').prop('disabled', false);
			}
		});
	});

	/* ==========================================
	 * Progress Bar Simulation
	 * ========================================== */
	function startProgressSimulation() {
		$progressPercent.text('10%');
		$progressStatus.text('Connecting to Discogs API...');
		$progressBar.css('width', '10%');
		$progressContainer.slideDown(250);

		let progress = 10;
		progressInterval = setInterval(function () {
			if (progress < 50) {
				progress += 5;
				$progressPercent.text(progress + '%');
				$progressBar.css('width', progress + '%');
			} else if (progress < 85) {
				// Slows down for database entries and image downloading.
				$progressStatus.text('Creating post and downloading high-quality cover image...');
				progress += 2;
				$progressPercent.text(progress + '%');
				$progressBar.css('width', progress + '%');
			}
		}, 300);
	}

	function stopProgressSimulation() {
		clearInterval(progressInterval);
		$progressPercent.text('100%');
		$progressBar.css('width', '100%');
		$progressStatus.text('Import complete.');
		
		setTimeout(function () {
			$progressContainer.slideUp(250);
		}, 1000);
	}
});
