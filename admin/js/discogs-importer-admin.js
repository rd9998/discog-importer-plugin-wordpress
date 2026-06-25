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

	// Collection elements.
	const $loadCollectionBtn  = $('#discogs-load-collection');
	const $collectionGrid     = $('#discogs-collection-results');
	const $collectionPagination = $('#discogs-collection-pagination');
	const $collectionHeader   = $('.discogs-collection-header');
	const $collectionTitle    = $('#discogs-collection-title');
	const $collectionCount    = $('#discogs-collection-count');
	const $importAllCollectionBtn = $('#discogs-import-all-collection');

	let currentSearchQuery = '';
	let currentSearchField = 'q';
	let currentPage        = 1;
	let collectionPage     = 1;
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
	$(document).on('click', '.import-btn', function (e) {
		e.preventDefault();
		const $btn = $(this);
		const releaseId = $btn.data('id');
		const $card = $(`#release-card-${releaseId}`);

		// Disable all buttons in grids to prevent overlapping queries.
		$('.import-btn').prop('disabled', true);
		$searchForm.find('input, select, button').prop('disabled', true);
		$loadCollectionBtn.prop('disabled', true);

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
					// Show imported overlay on cards matching this release ID in any grid.
					$(`[id="release-card-${releaseId}"]`).addClass('already-imported');
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
					$(`[id="release-card-${releaseId}"]`).append(successOverlayHtml);
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
				// Re-enable search form inputs and load collection button.
				$searchForm.find('input, select, button').prop('disabled', false);
				$loadCollectionBtn.prop('disabled', false);
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

	/* ==========================================
	 * Collection Operations
	 * ========================================== */
	$loadCollectionBtn.on('click', function (e) {
		e.preventDefault();
		collectionPage = 1;
		loadCollection();
	});

	function loadCollection() {
		// Show skeleton loaders in collection grid.
		renderCollectionSkeletons();
		$collectionHeader.show();
		$collectionTitle.text('Loading Collection...');
		$collectionCount.hide();
		$collectionPagination.hide();
		$importAllCollectionBtn.hide();

		$.ajax({
			url: discogsImporter.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: {
				action: 'discogs_importer_collection',
				nonce: discogsImporter.nonce,
				page: collectionPage
			},
			success: function (response) {
				if (response.success) {
					renderCollectionResults(response.data);
				} else {
					renderCollectionError(response.data || 'An error occurred while fetching collection.');
				}
			},
			error: function (xhr, status, error) {
				renderCollectionError('Connection error: ' + error);
			}
		});
	}

	function renderCollectionSkeletons() {
		$collectionGrid.empty();
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
			$collectionGrid.append(skeletonHtml);
		}
	}

	function renderCollectionError(message) {
		$collectionHeader.show();
		$collectionTitle.text('No Items');
		$collectionCount.hide();
		$collectionGrid.html(`
			<div class="discogs-alert discogs-alert-warning" style="grid-column: 1 / -1; width: 100%;">
				<p>${message}</p>
			</div>
		`);
		$collectionPagination.hide();
		$importAllCollectionBtn.hide();
	}

	function renderCollectionResults(data) {
		const results = data.results || [];
		const pagination = data.pagination || {};

		$collectionGrid.empty();
		$collectionHeader.show();
		$collectionTitle.text('My Discogs Collection');
		
		if (pagination.items !== undefined) {
			$collectionCount.text(pagination.items + ' items').show();
		} else {
			$collectionCount.hide();
		}

		if (results.length === 0) {
			renderCollectionError('Your collection is empty or could not be retrieved.');
			return;
		}

		results.forEach(function (release) {
			// Extract title and artist.
			let title = release.title || 'Untitled';
			let artist = 'Unknown Artist';
			
			if (title.indexOf(' - ') !== -1) {
				const parts = title.split(' - ');
				artist = parts[0].trim();
				title = parts.slice(1).join(' - ').trim();
			}

			artist = artist.replace(/\s\(\d+\)$/, '');

			const format = (release.format && release.format.length) ? release.format.join(', ') : 'Vinyl';
			const label = (release.label && release.label.length) ? release.label[0].replace(/\s\(\d+\)$/, '') : 'Unknown Label';
			const catno = release.catno || 'N/A';
			const year = release.year || 'N/A';
			const country = release.country || 'N/A';
			const thumb = release.thumb || '';
			
			let imageHtml = '';
			if (thumb && thumb.startsWith('http')) {
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
			$collectionGrid.append(cardHtml);
		});

		$importAllCollectionBtn.show();
		setupCollectionPagination(pagination);
	}

	function setupCollectionPagination(pag) {
		$collectionPagination.empty();

		const totalPages = pag.pages || 1;
		if (totalPages <= 1) {
			$collectionPagination.hide();
			return;
		}

		$collectionPagination.show();

		// Previous Page.
		const $prevBtn = $('<button class="discogs-pagination-btn" ' + (collectionPage === 1 ? 'disabled' : '') + '>Prev</button>');
		$prevBtn.on('click', function () {
			if (collectionPage > 1) {
				collectionPage--;
				loadCollection();
			}
		});
		$collectionPagination.append($prevBtn);

		// Page Numbers.
		const startPage = Math.max(1, collectionPage - 2);
		const endPage = Math.min(totalPages, collectionPage + 2);

		if (startPage > 1) {
			const $firstBtn = $('<button class="discogs-pagination-btn">1</button>');
			$firstBtn.on('click', function () { collectionPage = 1; loadCollection(); });
			$collectionPagination.append($firstBtn);
			if (startPage > 2) {
				$collectionPagination.append('<span class="pagination-ellipsis">...</span>');
			}
		}

		for (let i = startPage; i <= endPage; i++) {
			const $pageBtn = $(`<button class="discogs-pagination-btn ${i === collectionPage ? 'active' : ''}">${i}</button>`);
			$pageBtn.on('click', function () {
				collectionPage = i;
				loadCollection();
			});
			$collectionPagination.append($pageBtn);
		}

		if (endPage < totalPages) {
			if (endPage < totalPages - 1) {
				$collectionPagination.append('<span class="pagination-ellipsis">...</span>');
			}
			const $lastBtn = $(`<button class="discogs-pagination-btn">${totalPages}</button>`);
			$lastBtn.on('click', function () { collectionPage = totalPages; loadCollection(); });
			$collectionPagination.append($lastBtn);
		}

		// Next Page.
		const $nextBtn = $('<button class="discogs-pagination-btn" ' + (collectionPage === totalPages ? 'disabled' : '') + '>Next</button>');
		$nextBtn.on('click', function () {
			if (collectionPage < totalPages) {
				collectionPage++;
				loadCollection();
			}
		});
		$collectionPagination.append($nextBtn);
	}

	/* ==========================================
	 * Bulk Import Operations
	 * ========================================== */
	$importAllCollectionBtn.on('click', function (e) {
		e.preventDefault();

		let toImport = [];
		$('#discogs-collection-results .import-btn').each(function() {
			const $btn = $(this);
			const releaseId = $btn.data('id');
			const $card = $(`#release-card-${releaseId}`);
			if (!$card.hasClass('already-imported')) {
				toImport.push({ 
					id: releaseId, 
					title: $card.find('.discogs-card-title').text() 
				});
			}
		});

		if (toImport.length === 0) {
			alert('All items on this page are already imported!');
			return;
		}

		if (!confirm(`Are you sure you want to import all ${toImport.length} unimported items on this page?`)) {
			return;
		}

		let currentIndex = 0;
		const total = toImport.length;

		// Disable all controls to prevent parallel requests.
		$('.import-btn').prop('disabled', true);
		$searchForm.find('input, select, button').prop('disabled', true);
		$loadCollectionBtn.prop('disabled', true);
		$importAllCollectionBtn.prop('disabled', true);

		// Initialize progress bar.
		$progressPercent.text('0%');
		$progressStatus.text(`Starting bulk import of ${total} items...`);
		$progressBar.css('width', '0%');
		$progressContainer.slideDown(250);

		function importNext() {
			if (currentIndex >= total) {
				// Finished bulk import.
				$progressPercent.text('100%');
				$progressBar.css('width', '100%');
				$progressStatus.text(`Bulk import completed! Successfully imported ${total} items.`);
				
				setTimeout(function() {
					$progressContainer.slideUp(250);
					// Re-enable inputs.
					$searchForm.find('input, select, button').prop('disabled', false);
					$loadCollectionBtn.prop('disabled', false);
					$importAllCollectionBtn.prop('disabled', false);
					// Re-enable import buttons that are not already imported.
					$('.import-btn').each(function () {
						const $currentBtn = $(this);
						const currentId = $currentBtn.data('id');
						if (!$(`#release-card-${currentId}`).hasClass('already-imported')) {
							$currentBtn.prop('disabled', false);
						}
					});
				}, 2000);
				return;
			}

			const currentItem = toImport[currentIndex];
			const releaseId = currentItem.id;

			// Update progress bar status.
			const progressVal = Math.round((currentIndex / total) * 100);
			$progressPercent.text(progressVal + '%');
			$progressBar.css('width', progressVal + '%');
			$progressStatus.text(`Importing item ${currentIndex + 1} of ${total}: "${currentItem.title}"...`);

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
					if (response.success) {
						// Apply success overlay to all instances of this release card.
						$(`[id="release-card-${releaseId}"]`).addClass('already-imported');
						const successOverlayHtml = `
							<div class="import-success-overlay">
								<span class="dashicons dashicons-yes-alt success-icon"></span>
								<h4>Imported!</h4>
								<p style="font-size: 11px; color:#475569; margin: 0 0 10px 0; max-height: 24px; overflow: hidden;">${response.data.title}</p>
								<div class="import-links">
									<a href="${response.data.edit_url}" class="edit-btn" target="_blank" style="padding: 4px 8px; font-size:11px;">Edit</a>
									<a href="${response.data.view_url}" class="view-btn" target="_blank" style="padding: 4px 8px; font-size:11px;">View</a>
								</div>
							</div>
						`;
						$(`[id="release-card-${releaseId}"]`).append(successOverlayHtml);
					} else {
						console.error(`Import failed for release ID ${releaseId}: ${response.data}`);
						const $card = $(`#release-card-${releaseId}`);
						$card.find('.import-btn').text('Failed').addClass('button-secondary').removeClass('button-primary');
					}
				},
				error: function (xhr, status, error) {
					console.error(`Connection error for release ID ${releaseId}: ${error}`);
				},
				complete: function() {
					currentIndex++;
					// Short delay to avoid hitting API limits.
					setTimeout(importNext, 1200);
				}
			});
		}

		importNext();
	});
});
