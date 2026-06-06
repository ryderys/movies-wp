import { get } from "../utilities/ajax";

export default class ArchiveAppend {
  
  constructor() {
    this.isFetching = false;
    this.currentRequestId = 0; // Track the latest request ID
    this.filters = {};
    this.isClearingFilters = false;
    this.setupEventHandlers();
    // Ensure DOM is ready before reading filters from URL
    this.readFiltersFromUrl();
    this.updateAppliedFilters();
    this.updateFilterCount();
    this.handlePerPageChange();
    
    // Ensure results count is visible on initial page load
    this.ensureResultsCountVisibility();

    this.setupGenreInfiniteScroll();
  }

  setupEventHandlers() {
    jQuery(document.body).on('click', 'button.nav-link', this.handleTabChange.bind(this));
    jQuery(document.body).on("click", "#css_prefix_post_load_more", this.loadMore.bind(this));
    jQuery(window).on("scroll", this.handleScroll.bind(this));
    jQuery("#archive-loader").hide();

    // Filter change
    jQuery(document.body).on('change', '.streamit-filter', this.handleFilterChange.bind(this));

    // Clear All - Fixed selector
    jQuery(document.body).on('click', '#streamit-clear-filters', this.clearFilters.bind(this));
    
    // Applied filter removal
    jQuery(document.body).on('click', '.applied-filter-remove', this.removeAppliedFilter.bind(this));

     // Load more tags - NEW
     jQuery(document.body).on('click', '.streamit-load-more-tags', this.loadMoreTags.bind(this));
  }

  handleTabChange(e) {
    const targetTabContentId = jQuery(e.target).attr('data-bs-target');
    const activeTabContent = jQuery(targetTabContentId);
    const loadMoreButton = activeTabContent.find('#css_prefix_post_load_more');

    if (loadMoreButton.length > 0) {
      loadMoreButton.text(loadMoreButton.data('original-text'));
      loadMoreButton.prop('disabled', false);
      loadMoreButton.show();
    }
  }

  handlePerPageChange() {
    const listing = jQuery(".data-listing");
    const initialItemsCount = listing.children().length;
    const loadMoreButton = jQuery("#css_prefix_post_load_more");
    const initialResultsCountEl = jQuery('.results-count');

    if (Object.keys(this.filters).length > 0) {
      this.reloadFilteredContent();
    } else if (initialItemsCount === 0 && loadMoreButton.length > 0) {
        loadMoreButton.data("current-page", 0); // Start from page 0 so loadMore fetches page 1
        this.loadMore(null, loadMoreButton);
    } else {
        if (initialResultsCountEl.length > 0) {
            const initialTotal = parseInt(initialResultsCountEl.data('total-results')) || 0;
            const initialPerPage = parseInt(initialResultsCountEl.data('per-page')) || 10;
            const initialCurrentPage = parseInt(initialResultsCountEl.data('current-page')) || 1;
            this.updateResultsCount(initialCurrentPage, initialPerPage, initialTotal);
            // Ensure results count is visible on initial load
            initialResultsCountEl.show();
        }
    }
  }

  handleScroll() {
    if (this.isFetching) return;

    let loader = jQuery(".tab-pane.active #css_prefix_loader-wheel-container");
    loader = loader.length ? loader : jQuery("#css_prefix_loader-wheel-container");

    if (!loader.length) return;

    const scrollTop = jQuery(window).scrollTop();
    const windowHeight = jQuery(window).height();
    const documentHeight = jQuery(document).height();

    if (scrollTop + windowHeight >= documentHeight - 100) {
      loader.css("display", "block");
      this.loadMore(null, loader);
    }
  }

  handleFilterChange(e) {
    
    const el = jQuery(e.target);
    const originalName = el.attr("name"); // Get the original name with or without []
    const name = originalName.replace("[]", ""); // Remove [] for the filter key
    let value = el.val();

    // Reset the clearing filters flag when applying new filters
    this.isClearingFilters = false;

    if (el.is(":checkbox")) {
      const selected = [];
      
      // First, completely remove the existing filter to ensure clean state
      delete this.filters[name];
      
      // Get all checked checkboxes for this filter name using the original name
      jQuery(`input[name="${originalName}"]:checked`).each(function () {
        selected.push(this.value);
      });
      
      // Only add the filter if there are selected values
      if (selected.length > 0) {
          this.filters[name] = selected;
      }
      
    } else if (el.is(":radio") || el.is("select")) { 
        if (value === "" || value === null) { 
            delete this.filters[name]; 
        } else {
            this.filters[name] = value;
        }
    } else {
        if (value === "" || value === null) {
            delete this.filters[name];
        } else {
            this.filters[name] = value;
        }
    }
    
    // Always reload content when filters change (including unchecking)
    this.updateAppliedFilters();
    this.updateFilterCount();
    this.reloadFilteredContent();
    this.updateUrlParameters();
  }
  
  setupGenreInfiniteScroll() {
    const $base = jQuery('#genre-list-container');
    if (!$base.length) return;
  
    const $box    = this.getScrollableContainer ? this.getScrollableContainer($base) : $base;
    const $list   = jQuery('#genre-list');
    const $loader = $base.find('.genre-loader');
  
    let taxonomy = $base.attr('data-taxonomy') || 'movie_genre';
    let page     = parseInt($base.attr('data-page')) || 2;
    let perPage  = parseInt($base.attr('data-per-page')) || 6;
    // initial guess from rendered HTML (but client will rely on server.total_terms)
    let hasMore  = ($base.attr('data-has-more') === '1');
    let busy     = false;
    let probedOnce = false;
  
    // set of already-rendered IDs (avoid duplicates)
    const seenIds = new Set();
    $list.find('input[id^="genre-"]').each(function () {
      const id = parseInt((this.id || '').replace('genre-',''), 10);
      if (!isNaN(id) && id > 0) seenIds.add(id);
    });
  
    const appendItems = (items) => {
      let html = '';
      let appended = 0;
      items.forEach((t) => {
        const id = parseInt(t.id, 10) || 0;
        if (!id) return;
        if (seenIds.has(id)) return;
        seenIds.add(id);
        appended++;
        html += `
          <div class="form-check">
            <input class="form-check-input streamit-filter" type="checkbox"
                   value="${t.slug}" id="genre-${id}" name="genres[]">
            <label class="form-check-label" for="genre-${id}">
              <span class="filter-text">${t.name}</span>
            </label>
          </div>`;
      });
      if (html) $list.append(html);
      return appended;
    };
  
    const ajaxFetch = (onDone) => {
      $loader.show();
  
      get('streamit_load_genres_scroll', {
        page: page,
        taxonomy: taxonomy,
        per_page: perPage
      })
      .then((res) => {
        if (!res || !res.success) {
          hasMore = false;
          return onDone();
        }
  
        let appendedCount = 0;
        if (Array.isArray(res.items) && res.items.length) {
          appendedCount = appendItems(res.items);
        }
  
        // Prefer server-provided total_terms to decide stopping
        const totalTerms = (typeof res.total_terms === 'number') ? res.total_terms : null;
        if (totalTerms !== null) {
          // compute remaining by comparing seenIds vs total
          const remaining = Math.max(0, totalTerms - seenIds.size);
          hasMore = remaining > 0;
        }
  
        // Advance the page cursor when we appended items OR server gave next_page
        if (res.next_page) {
          page = res.next_page;
        } else if (appendedCount > 0) {
          page = page + 1;
        } else {
          // nothing appended and no next page -> stop
          hasMore = false;
        }
  
        return onDone();
      })
      .catch((err) => {
        hasMore = false;
        onDone();
      });
    };
  
    const fetchNext = () => {
      if (busy || !hasMore) {
        return;
      }
      busy = true;
      ajaxFetch(() => { busy = false; $loader.hide(); });
    };
  
    const THRESHOLD = 20;
    let ticking = false;
  
    const onScroll = () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        const node = $box.get(0);
        if (!node) { ticking = false; return; }
        const distance = node.scrollHeight - (node.scrollTop + node.clientHeight);
  
  
        if (distance <= THRESHOLD) {
          if (hasMore) {
            fetchNext();
          } else if (!probedOnce) {
            probedOnce = true;
            busy = false;
            hasMore = true;
            fetchNext();
          }
        }
        ticking = false;
      });
    };
  
    // start listening
    $box.off('scroll.genre').on('scroll.genre', onScroll);
    // run once to react if the box starts near-bottom
    onScroll();
  }

  /**
   * Finds the nearest scrollable ancestor (including the element itself)
   */
  getScrollableContainer($el) {
    const isScrollable = (node) => {
      if (!node) return false;
      const style = window.getComputedStyle(node);
      const overflowY = style.overflowY;
      const canScroll = (overflowY === 'auto' || overflowY === 'scroll');
      return canScroll && node.scrollHeight > node.clientHeight;
    };

    // 1) try the element itself
    let node = $el.get(0);
    if (isScrollable(node)) return jQuery(node);

    // 2) walk up ancestors
    while (node && node.parentElement) {
      node = node.parentElement;
      if (isScrollable(node)) return jQuery(node);
    }

    // 3) fallback to the original element (we'll force overflow)
    return $el;
  }

 
  loadMoreTags(e) {
    e.preventDefault();
  
    const button = jQuery(e.target).closest('.streamit-load-more-tags');
    if (!button.length) return;
  
    const container = jQuery('#movie-tags-container');
    if (!container.length) return;
  
    if (button.hasClass('loading')) return;
  
    const currentPage = parseInt(button.attr('data-page')) || 1;
    const taxonomy = button.attr('data-taxonomy') || 'movie_tag';
    const loadingText = button.data('loading-text') || 'در حال بارگذاری...';
    const originalHtml = button.data('original-html') || button.html();
    const perPage = parseInt(button.data('per-page')) || 6;
  
    // persist original html once
    if (!button.data('original-html')) {
      button.data('original-html', originalHtml);
    }
  
    const existingIds = new Set();
    container.find('input[id^="tag-"]').each(function () {
      const id = parseInt((this.id || '').replace('tag-', ''), 10);
      if (!isNaN(id) && id > 0) existingIds.add(id);
    });
  
    // Show loading
    button.addClass('loading').prop('disabled', true).html(`
      <span class="d-flex align-items-center">
        <i class="icon-loader"></i>
        <span>${loadingText}</span>
      </span>
    `);
  
    // Do request
    get("streamit_load_filter_term", {
      page: currentPage,
      taxonomy
    })
    .then((response) => {
      // Basic validation
      if (!response || !response.success) {
        button.removeClass('loading').prop('disabled', false).html(originalHtml).hide();
        return;
      }
  
      // Append items, skipping duplicates
      let appended = 0;
      if (Array.isArray(response.items) && response.items.length) {
        const htmlParts = [];
        response.items.forEach(function (t) {
          const id = parseInt(t.id, 10) || 0;
          if (!id) return;
          if (existingIds.has(id)) return;
          existingIds.add(id);
          appended++;
  
          const slug = String(t.slug || '').replace(/"/g, '&quot;');
          const name = String(t.name || '');
          const safeId = id;
  
          htmlParts.push(`
            <div class="form-check">
              <input class="form-check-input streamit-filter" type="checkbox" value="${slug}" id="tag-${safeId}" name="tags[]">
              <label class="form-check-label" for="tag-${safeId}">
                <span class="filter-text">${name}</span>
              </label>
            </div>
          `);
        });
  
        if (htmlParts.length) container.append(htmlParts.join(''));
      }
  
      const serverHasMore = !!response.has_more;
      const serverNextPage = (response.next_page === null || response.next_page === undefined) ? null : response.next_page;
  
      let shouldShowMore = false;
      let nextPage = null;
  
      if (serverHasMore) {
        shouldShowMore = true;
        nextPage = serverNextPage || (currentPage + 1);
      } else {
        if (appended >= (perPage || 6)) {
          shouldShowMore = true;
          nextPage = serverNextPage || (currentPage + 1);
        } else {
          shouldShowMore = false;
          nextPage = null;
        }
      }
  
      if (shouldShowMore && nextPage) {
        button.attr('data-page', nextPage);
        button.removeClass('loading').prop('disabled', false).html(originalHtml).show();
      } else {
        button.removeClass('loading').prop('disabled', false).html(originalHtml).hide();
      }
    })
    .catch((err) => {
      button.removeClass('loading').prop('disabled', false).html(originalHtml);
    });
  }
  

  removeAppliedFilter(e) {
    e.preventDefault();
    const filterType = jQuery(e.target).closest('.applied-filter-item').data('filter-type');
    const filterValue = jQuery(e.target).closest('.applied-filter-item').data('filter-value');
    
    // Reset the clearing filters flag when removing filters
    this.isClearingFilters = false;
    
    // Remove from filters object
    if (Array.isArray(this.filters[filterType])) {
      this.filters[filterType] = this.filters[filterType].filter(val => val !== filterValue);
      if (this.filters[filterType].length === 0) {
        delete this.filters[filterType];
      }
    } else {
      delete this.filters[filterType];
    }
    
    // Uncheck the corresponding input in the filter sidebar
    let inputToUncheck;
    
    // Try both with and without array notation for ALL filter types
    inputToUncheck = jQuery(`input[name="${filterType}[]"][value="${filterValue}"], input[name="${filterType}"][value="${filterValue}"]`);
    
    if (inputToUncheck.length > 0) {
        if (inputToUncheck.is(':radio')) {
            // For radio buttons, uncheck all radios with the same name
            jQuery(`input[name="${filterType}[]"], input[name="${filterType}"]`).prop('checked', false);
        } else if (inputToUncheck.is(':checkbox')) {
            // For checkboxes, uncheck the specific one
            inputToUncheck.prop('checked', false);
        }
    }
    
    // Also handle select elements
    const selectElement = jQuery(`select[name="${filterType}[]"], select[name="${filterType}"]`);
    if (selectElement.length > 0) {
        selectElement.val(''); 
    }
    
    this.updateAppliedFilters();
    this.updateFilterCount();
    this.reloadFilteredContent();
    this.updateUrlParameters(); 
  }

  updateAppliedFilters() {
    const container = jQuery('.applied-filters-container');
    const appliedFiltersSection = jQuery('.applied-filters-list');
    
    container.empty(); 

    let hasActiveFilters = false;
    
    Object.keys(this.filters).forEach(filterType => {
        if (filterType === 'sort_by') {
            return; 
        }
        const filterValue = this.filters[filterType];
        if ((Array.isArray(filterValue) && filterValue.length > 0) || (filterValue && filterValue !== "")) {
            hasActiveFilters = true; 
            
            if (Array.isArray(filterValue)) {
                filterValue.forEach(value => {
                    const filterText = this.getFilterDisplayText(filterType, value);
                    container.append(`
                        <li>
                            <button class="applied-filters-btn applied-filter-item" data-filter-type="${filterType}" data-filter-value="${value}">
                                <span class="d-flex align-items-center gap-2">
                                    <span>${filterText}</span>
                                    <i class="icon-cross applied-filter-remove"></i>
                                </span>
                            </button>
                        </li>
                    `);
                });
            } else {
                const filterText = this.getFilterDisplayText(filterType, filterValue);
                container.append(`
                    <li>
                        <button class="applied-filters-btn applied-filter-item" data-filter-type="${filterType}" data-filter-value="${filterValue}">
                            <span class="d-flex align-items-center gap-2">
                                <span>${filterText}</span>
                                <i class="icon-cross applied-filter-remove"></i>
                            </span>
                        </button>
                    </li>
                `);
            }
        }
    });
    
    if (hasActiveFilters) {
      appliedFiltersSection.show();
    } else {
      appliedFiltersSection.hide();
    }
  }

  getFilterDisplayText(filterType, value) {
    const element = jQuery(`input[name="${filterType}"][value="${value}"], input[name="${filterType}[]"][value="${value}"]`);
    const label = element.closest('.form-check').find('label .filter-text, label span:not(.d-flex)');
    
    if (label.length > 0) {
      return label.first().text().trim();
    }
    
    const selectOption = jQuery(`select[name="${filterType}"] option[value="${value}"]`);
    if (selectOption.length > 0) {
        return selectOption.text().trim();
    }

    if (filterType === 'release_year' && value.includes('-')) {
        return value.replace('-', ' - ');
    }

    if (filterType === 'access_type') {
      return {
          'free': 'Free',
          'plan': 'Premium',
          'ppv': 'Rent',
          'anyone': 'Premium or Rent'
      }[value] || value;
    }

    if (filterType === 'duration') {
      return {
          'under_1_hour': 'Under 1 Hour',
          '1_2_hours': '1-2 Hours',
          '2_3_hours': '2-3 Hours',
          '3_up': '3+ Hours'
      }[value] || value;
    }

    if (filterType === 'categories') {
      return value;
    }

    return value; 
  }

  updateFilterCount() {
    let count = 0;
    Object.keys(this.filters).forEach(filterType => {
      if (filterType === 'sort_by') {
        return; 
      }
      const filterValue = this.filters[filterType];
      if (Array.isArray(filterValue)) {
        count += filterValue.length;
      } else if (filterValue && filterValue !== "") { 
        count += 1;
      }
    });
    
    // Update sidebar filter count
    jQuery('.filter-offcanvas-title .filter-count').text(count);
    
    // Update mobile filter button count
    jQuery('.filter-btn .filter-count').text(count);
  }

  clearFilters() {
    this.filters = {}; 
    this.isClearingFilters = true; // Set flag to indicate we're clearing filters

    // Uncheck all checkboxes and radios with more specific selectors
    jQuery("input[type='checkbox'], input[type='radio']").each(function () {
      this.checked = false;
    });

    // Clear all select elements
    jQuery("select").val(''); 

    // Clear any other input elements
    jQuery("input[type='text'], input[type='number'], input[type='search']").val('');

    this.updateAppliedFilters();
    this.updateFilterCount();
    
    // Show loader immediately when clearing filters
    jQuery("#archive-loader").show();
    
    this.reloadFilteredContent();
    this.updateUrlParameters(); 
  }

  updateResultsCount(currentPage, perPage, totalResults) {
    const resultsCountElement = jQuery('.results-count');
    if (resultsCountElement.length === 0) {
      return;
    }

    if (totalResults === 0) {
      resultsCountElement.text(''); 
      resultsCountElement.attr("data-total-results", 0);
      resultsCountElement.attr("data-per-page", perPage);
      resultsCountElement.attr("data-current-page", currentPage);
      return;
    }

    const currentDisplayedItems = jQuery('.data-listing').children().length; 
    const startResult = 1; 
    const endResult = currentDisplayedItems;

    resultsCountElement
      .text(`نمایش ${startResult}-${endResult} از ${totalResults} نتیجه`)
      .attr("data-total-results", totalResults)
      .attr("data-per-page", perPage);
  }

  reloadFilteredContent() {
    const listing = jQuery(".data-listing");
    const loadMoreButton = jQuery("#css_prefix_post_load_more");

    listing.empty(); 
    jQuery('.no-results').remove(); 

    // Hide results count during loading
    jQuery('.streamit-results-count').hide();

    if (loadMoreButton.length > 0) {
      loadMoreButton.hide();
    }

    // Show the archive loader for all filter operations
    jQuery("#archive-loader").show();

    // Always trigger AJAX call when filters change (including when unchecking)
    if (loadMoreButton.length > 0) {
      loadMoreButton.data("current-page", 0); 
      loadMoreButton.data("total-pages", 1); 
      this.loadMore(null, loadMoreButton); 
    }
  }

  loadMore(event, buttonElement = null) {
    if (event && event.preventDefault) {
      event.preventDefault();
    }
  
    // Determine the element that triggered the load
    const triggeringElement = event?.currentTarget || buttonElement;
  
    // Safety check: if still no element found, stop
    if (!triggeringElement) {
        return;
    }
  
    const $current = jQuery(triggeringElement);
    let $listing = $current.closest('.css_prefix-card-wrapper').find('.data-listing');

    // If not found, fallback to ANY .data-listing on the page
    if (!$listing || $listing.length === 0) {
        $listing = jQuery('.data-listing').first();
    }

    
    if (this.isFetching) return;
    this.isFetching = true;

    const totalPages = $current.data("total-pages");
    let currentPage = $current.data("current-page");

    if (totalPages > 0 && currentPage >= totalPages) {
      this.noMorePages($current);
      this.isFetching = false;
      return;
    }

    currentPage += 1;

    const data = {
      current_page: currentPage,
      post_type: $current.data("post-type"),
      per_page: $current.data("per-page"),
      post_id: $current.data("post-id"),
      extra_setting: $current.data("extra-settings"),
      filters: this.filters,
    };

    if ($current.attr && $current.attr("id") === "css_prefix_post_load_more") {
      $current.text($current.data("loading-text"));
      $current.prop("disabled", true);
    } else {
      jQuery("#archive-loader").show();
    }

    this.fetchData(data, $current, $listing);
  }


  noMorePages(element) {
    if (element?.length) {
      if (element.attr("id") === "css_prefix_post_load_more") {
        element.hide(); 
      }
    }
    jQuery("#css_prefix_post_load_more").hide();
    jQuery("#css_prefix_loader-wheel-container").hide();
    jQuery("#archive-loader").hide();
  }

  fetchData(data, currentElement, currentListingElement) {

    currentListingElement.find('.no-results').remove(); 

    // Increment request ID for this request
    this.currentRequestId++;
    const requestId = this.currentRequestId;

    // Show the archive loader during filtering (not during load more) OR when clearing filters
    if (data.current_page === 1 && (Object.keys(this.filters).length > 0 || this.isClearingFilters)) {
      jQuery("#archive-loader").show();
    }

    get("st_load_more_content", {
      data: data,
      _ajax_nonce: window.stAjax, 
    })
      .then((res) => {
        // Check if this is the latest request
        if (requestId !== this.currentRequestId) {
          return;
        }

        if (res.status) {
          if (res.result && res.result.trim()) {
            this.appendContent(res.result, currentListingElement);
            currentElement.data("current-page", res.current_page);
            currentElement.data("total-pages", res.total_pages);
            this.updateResultsCount(res.current_page, data.per_page, res.total_results);
            
            // Show results count after content is loaded
            jQuery('.streamit-results-count').show();
            
            this.updatePaginationState(currentElement, res.current_page, res.total_pages);
          } else {
            if (data.current_page === 1) { 
              currentListingElement.empty().append(`<div class="no-results">هیچ داده‌ای پیدا نشد.</div>`); 
              this.updateResultsCount(0, 0, 0); 
              // Show results count even when no results
              jQuery('.streamit-results-count').show();
            }
            this.noMorePages(currentElement);
          }
        } else {
          if (data.current_page === 1) {
            currentListingElement.empty().append(`<div class="no-results">هیچ داده‌ای پیدا نشد.</div>`);
            this.updateResultsCount(0, 0, 0);
            // Show results count even when no results
            jQuery('.streamit-results-count').show();
          }
          this.noMorePages(currentElement);
        }

        this.isFetching = false;
        this.isClearingFilters = false; // Reset the clearing filters flag
        jQuery("#css_prefix_loader-wheel-container").hide();
        jQuery("#archive-loader").hide();
        
        const loadMoreButton = jQuery("#css_prefix_post_load_more");
        if (loadMoreButton.length > 0 && res.total_pages > res.current_page) {
          loadMoreButton.show();
        }
        
        if (currentElement.attr("id") === "css_prefix_post_load_more") {
          currentElement.text(currentElement.data("original-text"));
          currentElement.prop('disabled', false);
        } 
      })
      .catch((error) => {
        // Check if this is the latest request
        if (requestId !== this.currentRequestId) {
          return;
        }

        this.isFetching = false;
        this.isClearingFilters = false; // Reset the clearing filters flag
        jQuery("#archive-loader").hide();

        if (currentElement.attr("id") === "css_prefix_post_load_more") {
          currentElement.text(currentElement.data("original-text"));
          currentElement.prop('disabled', false);
        } 
        currentListingElement.empty().append(`<div class="no-results">خرابی در بارگذاری محتوا. لطفاً دوباره سعی کنید.</div>`);
        this.updateResultsCount(0, 0, 0);
        // Show results count even when there's an error
        jQuery('.streamit-results-count').show();
      });
  }

  updatePaginationState(element, currentPage, totalPages) {
    if (currentPage >= totalPages) {
      this.noMorePages(element);
    } else if (element.attr("id") === "css_prefix_post_load_more") {
      element.text(element.data("original-text"));
      element.prop('disabled', false);
    }
  }

  appendContent(content, targetListingElement) {
    targetListingElement?.length
      ? targetListingElement.append(content)
      : console.warn("WARNING: Target listing element not found.");
  }

  /**
   * Reads filter parameters from the URL and populates this.filters object and updates UI.
   */
  readFiltersFromUrl() {
    const urlParams = new URLSearchParams(window.location.search);
    this.filters = {};
    this.isClearingFilters = false; // Reset the clearing filters flag


    for (const [key, value] of urlParams.entries()) {
        // Check if the value contains commas or ampersands (multiple values)
        if (value.includes(',') || value.includes('&')) {
            // Split by both comma and ampersand, then filter out empty values
            this.filters[key] = value.split(/[,&]/).filter(val => val.trim() !== '');
        } else {
            // Single value filter
            this.filters[key] = value;
        }
    }
    
    
    // Update the UI after a small delay to ensure DOM is ready
    setTimeout(() => {
        this.updateUIFromFilters();
        this.updateAppliedFilters();
        this.updateFilterCount();
    }, 100);
  }

  /**
   * Updates the UI elements based on the current filters object
   */
  updateUIFromFilters() {
    
    // Check if filter elements exist
    const totalFilterElements = jQuery('.streamit-filter').length;
    
    for (const [key, value] of Object.entries(this.filters)) {
        
        if (Array.isArray(value)) {
            // Handle array-based filters (genres[], tags[], etc.)
            value.forEach(val => {
                
                // Try both with and without array notation
                let inputElement = jQuery(`input[name="${key}[]"][value="${val}"]`);
                if (inputElement.length === 0) {
                    inputElement = jQuery(`input[name="${key}"][value="${val}"]`);
                }
                
                if (inputElement.length > 0) {
                    inputElement.prop('checked', true);
                }
            });
        } else {
            // Try both with and without array notation for checkboxes/radios
            let inputElement = jQuery(`input[name="${key}[]"][value="${value}"], input[name="${key}"][value="${value}"]`);
            if (inputElement.is(':radio') || inputElement.is(':checkbox')) {
                inputElement.prop('checked', true);
            } else {
                // Handle select elements
                const selectElement = jQuery(`select[name="${key}"]`);
                if (selectElement.length > 0) {
                    selectElement.val(value);
                }
            }
        }
    }
  }

  /**
   * Updates the URL's query parameters based on the current this.filters object.
   */
  updateUrlParameters() {
    const newUrl = new URL(window.location.origin + window.location.pathname);
    for (const key in this.filters) {
      if (Object.hasOwnProperty.call(this.filters, key)) {
        const value = this.filters[key];
        if (Array.isArray(value) && value.length > 0) {
          newUrl.searchParams.set(key, value.join(','));
        } else if (value !== "" && value !== null) {
          newUrl.searchParams.set(key, value);
        }
      }
    }
    // Use replaceState to avoid cluttering browser history with every filter change
    history.replaceState(null, '', newUrl.toString());
  }

  /**
   * Ensures the results count is visible on initial page load
   */
  ensureResultsCountVisibility() {
    // If there are no filters and we have initial content, show the results count
    if (Object.keys(this.filters).length === 0) {
      const initialResultsCountEl = jQuery('.streamit-results-count');
      if (initialResultsCountEl.length > 0) {
        initialResultsCountEl.show();
      }
    }
  }
}
