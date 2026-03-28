/**
 * RPG Maps Plugin - Frontend JavaScript
 * Handles interactive map features, AJAX requests, modals, tooltips
 * 
 * @package rpgmaps
 */

(function() {
    'use strict';

    /**
     * RPG Maps Manager
     */
    const RPGMaps = {
        // Configuration
        config: {
            csrf_token: '',
            map_id: 0,
            is_logged_in: false,
            ajax_url: 'rpgmaps.php',
            lang: {},
        },

        /**
         * Initialize the plugin
         */
        init: function() {
            
            // Get configuration from page
            const container = document.querySelector('[data-csrf-token]');
            
            this.config.csrf_token = container?.getAttribute('data-csrf-token') || '';
            this.config.map_id = container?.getAttribute('data-map-id') || 0;
            this.config.ajax_url   = container?.getAttribute('data-ajax-url')   || 'rpgmaps.php';
            this.config.assets_url = container?.getAttribute('data-assets-url') || '';
            
            const loginValue = container?.getAttribute('data-logged-in');
            
            this.config.is_logged_in = (loginValue === '1' || loginValue === 1 || loginValue === true);
            
            // Load language strings
            const langJson = container?.getAttribute('data-lang');
            if (langJson) {
                try {
                    this.config.lang = JSON.parse(langJson);
                } catch(e) {
                    console.error('RPGMaps: Failed to parse language JSON:', e);
                    this.config.lang = {};
                }
            }
            

            // Setup event listeners
            this.setupEventListeners();

            // Load map data
            if (this.config.map_id > 0) {
                this.loadMapData();
                
                // Wait for map image to load before scaling
                const mapImage = document.querySelector('.rpgmaps-map-image');
                if (mapImage) {
                    if (mapImage.complete) {
                        // Image is already loaded (cached)
                        this.scalePlots();
                    } else {
                        // Wait for image to load
                        mapImage.addEventListener('load', () => {
                            this.scalePlots();
                        });
                    }
                }
                
                // Also scale on window resize
                window.addEventListener('resize', () => this.scalePlots());
            }
            
        },

        /**
         * Scale plots to match the displayed map size (responsive)
         */
        scalePlots: function() {
            
            const container = document.querySelector('[data-csrf-token]');
            if (!container) {
                console.warn('RPGMaps: Container not found');
                return;
            }
            
            const origWidth = parseInt(container.getAttribute('data-orig-width')) || 0;
            const origHeight = parseInt(container.getAttribute('data-orig-height')) || 0;
            
            
            if (origWidth <= 0 || origHeight <= 0) {
                console.warn('RPGMaps: Invalid original dimensions');
                return;
            }
            
            const mapImage = document.querySelector('.rpgmaps-map-image');
            if (!mapImage) {
                console.warn('RPGMaps: Map image not found');
                return;
            }
            
            // Get the actual displayed size of the map image
            const displayedWidth = mapImage.clientWidth;
            const displayedHeight = mapImage.clientHeight;
            
            
            if (displayedWidth <= 0 || displayedHeight <= 0) {
                console.warn('RPGMaps: Map image has no dimensions yet');
                return;
            }
            
            // Calculate scale factors
            const scaleX = displayedWidth / origWidth;
            const scaleY = displayedHeight / origHeight;
            
            
            // Scale all plots
            const plots = document.querySelectorAll('.rpgmaps-plot');
            
            plots.forEach((plot, index) => {
                const origX = parseInt(plot.getAttribute('data-orig-x')) || 0;
                const origY = parseInt(plot.getAttribute('data-orig-y')) || 0;
                const origW = parseInt(plot.getAttribute('data-orig-w')) || 0;
                const origH = parseInt(plot.getAttribute('data-orig-h')) || 0;
                const rotation = parseInt(plot.getAttribute('data-rotation')) || 0;
                
                // Calculate scaled values
                const scaledX = origX * scaleX;
                const scaledY = origY * scaleY;
                const scaledW = origW * scaleX;
                const scaledH = origH * scaleY;
                
                
                // Apply scaled values
                let newStyle = `left: ${scaledX}px; top: ${scaledY}px; width: ${scaledW}px; height: ${scaledH}px; transform-origin: center center;`;
                if (rotation != 0) {
                    newStyle += ` transform: rotate(${rotation}deg);`;
                }
                
                plot.setAttribute('style', newStyle);
            });
        },

        /**
         * Setup event listeners for plot clicks
         */
        setupEventListeners: function() {
            
            // Delegate click events on plot elements
            document.addEventListener('click', (e) => {
                const plot = e.target.closest('.rpgmaps-plot');
                if (plot) {
                    const plotId = plot.getAttribute('data-plotid');
                    const houseId = plot.getAttribute('data-houseid');
                    this.showPlotInfo(plotId, houseId);
                    return; // Stop processing other click handlers
                }

                // Close tooltip on outside click
                const tooltip = document.querySelector('.rpgmaps-tooltip.visible');
                if (tooltip && !tooltip.contains(e.target) && !e.target.closest('.rpgmaps-plot')) {
                    this.hideTooltip();
                }

                // Close modal on outside click (clicking the backdrop)
                const modal = document.querySelector('.rpgmaps-modal.visible');
                if (modal && e.target === modal) {
                    this.hideModal();
                }
            });

            // Close buttons
            document.addEventListener('click', (e) => {
                if (e.target.classList.contains('tooltip-close')) {
                    this.hideTooltip();
                    e.preventDefault();
                }
                if (e.target.classList.contains('close')) {
                    this.hideModal();
                }
            });

            // Form submissions
            document.addEventListener('submit', (e) => {
                if (e.target.id === 'form-build-house') {
                    e.preventDefault();
                    this.submitBuildRequest(e.target);
                } else if (e.target.id === 'form-move-in') {
                    e.preventDefault();
                    this.submitMoveInRequest(e.target);
                } else if (e.target.id === 'form-move-out') {
                    e.preventDefault();
                    this.submitMoveOutRequest(e.target);
                }
            }, true); // Use capture phase
        },

        /**
         * Load map data via AJAX
         */
        loadMapData: function() {
            const data = {
                sub: 'rpgmaps_get_map',
                map_id: this.config.map_id,
                token: this.config.csrf_token,
            };

            this.ajax(data, (response) => {
                if (response.success) {
                    // Store for later use
                    window.rpgmapsData = response;
                }
            });
        },

        /**
         * Show plot information (tooltip or modal)
         */
        showPlotInfo: function(plotId, houseId) {

            if (!houseId) {
                const plotEl = document.querySelector('[data-plotid="' + plotId + '"]');
                const isPending = plotEl && plotEl.getAttribute('data-pending') === '1';

                if (isPending) {
                    // Pending plot - show building info tooltip
                    const msg = this.config.lang.plot_building || 'A new house is being built here.';
                    this.showTooltip(plotId, '<p>' + this.escapeHtml(msg) + '</p>');
                } else if (this.config.is_logged_in) {
                    this.showBuildModal(plotId);
                } else {
                    alert('Du musst eingeloggt sein, um hier zu bauen.');
                }
            } else {
                // Occupied plot - show info
                this.loadHouseInfo(houseId, plotId);
            }
        },

        /**
         * Load house information via AJAX
         */
        loadHouseInfo: function(houseId, plotId) {
            const data = {
                sub: 'rpgmaps_get_house_info',
                house_id: houseId,
                token: this.config.csrf_token,
            };

            this.ajax(data, (response) => {
                if (response.success) {
                    this.displayHouseTooltip(response.house, response.house_type, response.house_types || [], response.occupants, response.plot, plotId);
                } else {
                    const errorMessage = response.message || response.error || 'Error loading house info.';
                    console.error('RPGMaps: Failed to load house info:', errorMessage);
                    this.showTooltip(plotId, '<p>' + this.escapeHtml(errorMessage) + '</p>');
                }
            });
        },

        /**
         * Display house information in tooltip
         */
        displayHouseTooltip: function(house, houseType, houseTypes, occupants, plot, plotId) {
            const currentUserId = this.config.is_logged_in
                ? parseInt(document.querySelector('[data-csrf-token]').getAttribute('data-user-id') || '0')
                : 0;
            const isOccupant    = occupants.some(occ => occ.uid == currentUserId);
            const currentHouseName = (house.house_name || '').trim();
            const displayTitle  = currentHouseName || this.escapeHtml(houseType.name);
            const plotLabelRaw  = plot && typeof plot.tooltip_text === 'string' && plot.tooltip_text.trim() !== ''
                ? plot.tooltip_text
                : (plot && plot.plot_key ? plot.plot_key : '');

            let html = '<div class="rpgmaps-house-info">';

            // === Header: thumbnail + title/type ===
            html += '<div class="rpgmaps-house-header trow1">';
            if (houseType.asset_filename && this.config.assets_url) {
                html += '<img src="' + this.config.assets_url + 'houses/' + this.escapeHtml(houseType.asset_filename) + '"'
                    + ' class="rpgmaps-house-thumb" alt="">';
            }
            html += '<div class="rpgmaps-house-title-block">';
            html += '<strong class="rpgmaps-house-title">' + this.escapeHtml(displayTitle) + '</strong>';
            if (currentHouseName) {
                html += '<span class="rpgmaps-house-subtitle">' + this.escapeHtml(houseType.name) + '</span>';
            }
            html += '</div></div>';

            // === Info table: plot + capacity ===
            html += '<table class="rpgmaps-info-table">';
            if (plotLabelRaw) {
                html += '<tr class="trow1">'
                    + '<td class="rpgmaps-info-label">' + (this.config.lang.plot_name || 'Plot') + '</td>'
                    + '<td class="rpgmaps-info-value">' + this.escapeHtml(plotLabelRaw) + '</td>'
                    + '</tr>';
            }
            html += '<tr class="trow2">'
                + '<td class="rpgmaps-info-label">' + this.config.lang.maximum_occupants + '</td>'
                + '<td class="rpgmaps-info-value">'
                + occupants.length + '&thinsp;/&thinsp;<span id="house-max-occupants-' + house.id + '">' + house.max_occupants + '</span>'
                + '</td></tr>';
            html += '</table>';

            // === Description ===
            html += '<div class="tcat rpgmaps-section-head">' + this.config.lang.description + '</div>';
            html += '<div class="trow1 rpgmaps-section-body">';
            html += '<div id="description-display-' + house.id + '">';
            html += house.description_html
                ? house.description_html
                : '<em>' + this.config.lang.no_description + '</em>';
            html += '</div>';
            if (isOccupant) {
                html += '<textarea id="description-edit-' + house.id + '" class="description-edit-textarea">'
                    + (house.description_raw ? this.escapeHtml(house.description_raw) : '') + '</textarea>';
                html += '<div class="description-edit-controls">';
                html += '<button class="btn-small" onclick="RPGMaps.toggleDescriptionEdit(' + house.id + ')" id="edit-btn-' + house.id + '">'
                    + this.config.lang.edit + '</button>';
                html += '<button class="btn-small hidden" onclick="RPGMaps.saveDescription(' + house.id + ')" id="save-btn-' + house.id + '">'
                    + this.config.lang.save + '</button>';
                html += '<button class="btn-small btn-cancel hidden" onclick="RPGMaps.cancelDescriptionEdit(' + house.id + ')" id="cancel-btn-' + house.id + '">'
                    + this.config.lang.cancel + '</button>';
                html += '</div>';
            }
            html += '</div>';

            // === Occupants ===
            html += '<div class="tcat rpgmaps-section-head">'
                + this.config.lang.occupants
                + ' <span class="rpgmaps-occupant-count">' + occupants.length + '&thinsp;/&thinsp;' + house.max_occupants + '</span>'
                + '</div>';
            html += '<div class="trow1 rpgmaps-section-body rpgmaps-occupant-list">';
            if (occupants.length > 0) {
                occupants.forEach((occ) => {
                    let roleLabel = occ.role === 'owner'
                        ? (this.config.lang.role_owner || 'Owner')
                        : occ.role === 'resident'
                            ? (this.config.lang.role_resident || 'Resident')
                            : this.escapeHtml(occ.role.toUpperCase());
                    html += '<div class="rpgmaps-occupant-item">';
                    html += '<span class="occupant-role">' + roleLabel + '</span>';
                    html += occ.username
                        ? '<a href="member.php?action=profile&uid=' + occ.uid + '">' + this.escapeHtml(occ.username) + '</a>'
                        : 'User ' + occ.uid;
                    html += '</div>';
                });
            } else {
                html += '<em>' + (this.config.lang.no_occupants || '–') + '</em>';
            }
            html += '</div>';

            // === Settings (occupants only) ===
            if (isOccupant) {
                html += '<div class="tcat rpgmaps-section-head">'
                    + (this.config.lang.house_settings || 'Einstellungen') + '</div>';
                html += '<div class="trow1 rpgmaps-section-body">';
                html += '<div class="form-group">'
                    + '<label>' + this.config.lang.type + '</label>'
                    + '<select id="house-type-edit-' + house.id + '">';
                (houseTypes || []).forEach((type) => {
                    const selected = parseInt(type.id, 10) === parseInt(house.type_id, 10) ? ' selected' : '';
                    html += '<option value="' + parseInt(type.id, 10) + '"' + selected + '>' + this.escapeHtml(type.name) + '</option>';
                });
                html += '</select></div>';
                html += '<div class="form-group">'
                    + '<label>' + this.config.lang.maximum_occupants + '</label>'
                    + '<input type="number" id="max-occupants-edit-' + house.id + '" min="1" max="20" value="' + parseInt(house.max_occupants, 10) + '">'
                    + '</div>';
                html += '<div class="form-group">'
                    + '<label>' + this.escapeHtml(this.config.lang.house_name_optional || '') + '</label>'
                    + '<input type="text" id="house-name-edit-' + house.id + '" maxlength="255" value="' + (currentHouseName ? this.escapeHtml(currentHouseName) : '') + '">'
                    + '</div>';
                html += '<button class="btn-small" onclick="RPGMaps.saveHouseSettings(' + house.id + ')">'
                    + this.config.lang.save + '</button>';
                html += '</div>';
            }

            // === Action buttons ===
            if (this.config.is_logged_in) {
                html += '<div class="rpgmaps-tooltip-actions trow2">';
                if (occupants.length < house.max_occupants) {
                    html += '<button class="button rpgmaps-btn-movein" onclick="RPGMaps.showMoveInModal(' + house.id + ')">'
                        + this.config.lang.move_in + '</button>';
                }
                html += '<button class="button rpgmaps-btn-moveout" onclick="RPGMaps.showMoveOutModal(' + house.id + ')">'
                    + this.config.lang.move_out + '</button>';
                html += '</div>';
            }

            html += '</div>';
            this.showTooltip(plotId, html);
        },

        /**
         * Save house settings (house type + max occupants)
         */
        saveHouseSettings: function(houseId) {
            const typeSelect = document.getElementById('house-type-edit-' + houseId);
            const maxInput = document.getElementById('max-occupants-edit-' + houseId);
            const houseNameInput = document.getElementById('house-name-edit-' + houseId);

            if (!typeSelect || !maxInput || !houseNameInput) {
                return;
            }

            const houseTypeId = parseInt(typeSelect.value, 10);
            const maxOccupants = parseInt(maxInput.value, 10);
            const houseName = houseNameInput.value.trim();

            if (!houseTypeId || houseTypeId <= 0) {
                this.showMessage('error', 'Bitte einen gültigen Grundstücks-Typ wählen.');
                return;
            }

            if (!maxOccupants || maxOccupants < 1 || maxOccupants > 20) {
                this.showMessage('error', 'Bitte eine gültige maximale Bewohnerzahl (1-20) eingeben.');
                return;
            }

            const data = {
                sub: 'rpgmaps_update_house_settings',
                house_id: houseId,
                house_type_id: houseTypeId,
                max_occupants: maxOccupants,
                house_name: houseName,
                token: this.config.csrf_token,
            };

            this.ajax(data, (response) => {
                if (response.success) {
                    const maxDisplay = document.getElementById('house-max-occupants-' + houseId);
                    if (maxDisplay) {
                        maxDisplay.textContent = String(response.max_occupants);
                    }

                    const typeDisplay = document.getElementById('house-type-name-' + houseId);
                    if (typeDisplay && response.type_name) {
                        typeDisplay.textContent = response.type_name;
                    }

                    const houseNameDisplay = document.getElementById('house-name-name-' + houseId);
                    if (houseNameDisplay) {
                        houseNameDisplay.textContent = response.house_name ? response.house_name : '-';
                    }

                    const houseElement = document.querySelector('[data-houseid="' + houseId + '"]');
                    if (houseElement) {
                        const houseImage = houseElement.querySelector('.rpgmaps-house-image');
                        if (houseImage && response.type_asset) {
                            const currentSrc = houseImage.getAttribute('src') || '';
                            const newSrc = currentSrc.replace(/[^\/]+$/, response.type_asset);
                            if (newSrc) {
                                houseImage.setAttribute('src', newSrc);
                            }
                        }

                        const plotInfo = houseElement.querySelector('.rpgmaps-plot-info');
                        if (plotInfo && response.type_name) {
                            const parts = plotInfo.innerHTML.split('<br>');
                            if (parts.length > 1) {
                                plotInfo.innerHTML = this.escapeHtml(this.formatHouseLabel(response.type_name, response.house_name || '')) + '<br>' + parts[1];
                            }
                        }
                    }

                    this.showMessage('success', response.message || 'Haus-Einstellungen gespeichert.');
                } else {
                    const errorMessage = response.message || response.error || 'Fehler beim Speichern der Haus-Einstellungen';
                    this.showMessage('error', errorMessage);
                }
            });
        },

        /**
         * Show build house modal
         */
        showBuildModal: function(plotId) {
            const modal = document.getElementById('rpgmaps-modal-build');
            const plotIdInput = document.getElementById('build-plot-id');
            if (modal && plotIdInput) {
                plotIdInput.value = plotId;
                modal.classList.remove('hidden-modal');
                modal.classList.add('visible');
            } else {
                console.error('RPGMaps: Modal or input field not found!');
                if (!modal) {
                    console.error('RPGMaps: Modal element with ID "rpgmaps-modal-build" not found');
                }
                if (!plotIdInput) {
                    console.error('RPGMaps: Input element with ID "build-plot-id" not found');
                }
                alert('Modal nicht gefunden! Bitte Seite neu laden.');
            }
        },

        /**
         * Show move-in modal
         */
        showMoveInModal: function(houseId) {
            // Hide current tooltip
            this.hideTooltip();

            // Create modal content
            const modal = document.createElement('div');
            modal.className = 'rpgmaps-modal visible';
            modal.innerHTML = `
                <div class="modal-content tborder trow1">
                    <div class="rpgmaps-modal-header thead">
                        <strong>${this.config.lang.move_in}</strong>
                        <span class="close" onclick="RPGMaps.hideModal()">&times;</span>
                    </div>
                    <div class="rpgmaps-modal-body">
                        <p>${this.config.lang.confirm_move_in}</p>
                        <form id="form-move-in">
                            <input type="hidden" name="house_id" value="${houseId}">
                            <button type="submit" class="btn btn-primary">${this.config.lang.yes}</button>
                            <button type="button" class="btn btn-secondary" onclick="RPGMaps.hideModal()">${this.config.lang.cancel}</button>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        },

        /**
         * Show move-out modal
         */
        showMoveOutModal: function(houseId) {
            // Hide current tooltip
            this.hideTooltip();

            // Create modal content
            const modal = document.createElement('div');
            modal.className = 'rpgmaps-modal visible';
            modal.innerHTML = `
                <div class="modal-content tborder trow1">
                    <div class="rpgmaps-modal-header thead">
                        <strong>${this.config.lang.move_out}</strong>
                        <span class="close" onclick="RPGMaps.hideModal()">&times;</span>
                    </div>
                    <div class="rpgmaps-modal-body">
                        <p>${this.config.lang.confirm_move_out}</p>
                        <form id="form-move-out">
                            <input type="hidden" name="house_id" value="${houseId}">
                            <button type="submit" class="btn btn-primary">${this.config.lang.yes}</button>
                            <button type="button" class="btn btn-secondary" onclick="RPGMaps.hideModal()">${this.config.lang.cancel}</button>
                        </form>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        },

        /**
         * Show tooltip
         */
        showTooltip: function(plotId, content) {
            let tooltip = document.getElementById('rpgmaps-tooltip-' + plotId);

            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.id = 'rpgmaps-tooltip-' + plotId;
                tooltip.className = 'rpgmaps-tooltip tborder trow1';
                tooltip.innerHTML = `
                    <div class="tooltip-header thead">
                        <h3>${this.config.lang.house_information || 'House Information'}</h3>
                        <a href="javascript:void(0);" class="tooltip-close">&times;</a>
                    </div>
                    <div class="tooltip-body"></div>
                `;
                document.body.appendChild(tooltip);
            }

            tooltip.querySelector('.tooltip-body').innerHTML = content;

            // Measure dimensions while invisible to get reliable values
            tooltip.style.visibility = 'hidden';
            tooltip.style.display = 'block';
            const tw = tooltip.offsetWidth || 400;
            const th = tooltip.offsetHeight || 200;
            tooltip.style.display = '';
            tooltip.style.visibility = '';

            tooltip.classList.add('visible');

            // Position tooltip near the plot, keeping it fully inside the viewport
            const plot = document.querySelector('[data-plotid="' + plotId + '"]');
            if (plot) {
                const rect = plot.getBoundingClientRect();
                const padding = 8;
                let left = rect.right + 10;
                let top = rect.top;

                const maxLeft = window.innerWidth - tw - padding;
                const maxTop = window.innerHeight - th - padding;

                if (left > maxLeft) {
                    left = rect.left - tw - 10;
                }
                if (left < padding) {
                    left = padding;
                }
                if (top > maxTop) {
                    top = Math.max(padding, maxTop);
                }
                if (top < padding) {
                    top = padding;
                }

                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
            } else {
                console.error('RPGMaps: Plot element not found for positioning');
            }
        },

        /**
         * Hide tooltip
         */
        hideTooltip: function() {
            const tooltip = document.querySelector('.rpgmaps-tooltip.visible');
            if (tooltip) {
                tooltip.classList.remove('visible');
            }
        },

        /**
         * Hide modal
         */
        hideModal: function() {
            const modal = document.querySelector('.rpgmaps-modal.visible');
            if (modal) {
                // Check if this is a static modal (has an ID) or dynamic modal
                if (modal.id) {
                    // Static modal - remove the visible class and add back hidden-modal if it's the build modal
                    modal.classList.remove('visible');
                    if (modal.id === 'rpgmaps-modal-build') {
                        modal.classList.add('hidden-modal');
                    }
                } else {
                    // Dynamic modal - remove from DOM
                    modal.remove();
                }
            }
        },

        /**
         * Submit build request
         */
        submitBuildRequest: function(form) {
            
            // Get form elements with error checking
            const plotIdInput = document.getElementById('build-plot-id');
            const typeIdSelect = document.getElementById('house-type-select');
            const maxOccupantsInput = document.getElementById('max-occupants-input');
            const descriptionInput = document.getElementById('house-description-input');
            const houseNameInput = document.getElementById('house-name-input');
            
            
            // Check if all required elements exist
            if (!plotIdInput || !typeIdSelect || !maxOccupantsInput) {
                console.error('RPGMaps: Form elements not found!');
                if (!plotIdInput) console.error('RPGMaps: build-plot-id not found');
                if (!typeIdSelect) console.error('RPGMaps: house-type-select not found');
                if (!maxOccupantsInput) console.error('RPGMaps: max-occupants-input not found');
                alert('Formularfelder nicht gefunden. Bitte Seite neu laden.');
                return;
            }
            
            const plotId = plotIdInput.value;
            const typeId = typeIdSelect.value;
            const maxOccupants = maxOccupantsInput.value;
            const description = descriptionInput ? descriptionInput.value.trim() : '';
            const houseName = houseNameInput ? houseNameInput.value.trim() : '';

            if (!typeId) {
                alert('Please select a house type');
                return;
            }
            
            if (!maxOccupants || maxOccupants < 1 || maxOccupants > 20) {
                alert('Please enter a valid number of occupants (1-20)');
                return;
            }

            const data = {
                sub: 'rpgmaps_build_request',
                plot_id: plotId,
                house_type_id: typeId,
                max_occupants: maxOccupants,
                description: description,
                house_name: houseName,
                token: this.config.csrf_token,
            };

            this.ajax(data, (response) => {
                if (response.success) {
                    // Change plot to pending state (yellow) instead of removing it
                    const plot = document.querySelector('[data-plotid="' + plotId + '"]');
                    if (plot) {
                        plot.classList.remove('rpgmaps-plot-free');
                        plot.classList.add('rpgmaps-plot-pending');
                        plot.setAttribute('data-pending', '1');
                        const plotInfo = plot.querySelector('.rpgmaps-plot-info');
                        if (plotInfo) {
                            plotInfo.textContent = this.config.lang.plot_building || 'A new house is being built here.';
                        }
                    }

                    // Replace modal content with confirmation instead of closing
                    const modal = document.getElementById('rpgmaps-modal-build');
                    const modalBody = modal ? modal.querySelector('.rpgmaps-modal-body') : null;
                    if (modalBody) {
                        modalBody.innerHTML =
                            '<div class="rpgmaps-message success">' +
                                '<strong>' + this.escapeHtml(this.config.lang.build_success || 'Build request submitted!') + '</strong>' +
                                '<br>' +
                                this.escapeHtml(this.config.lang.build_pending_info || 'Your build request is awaiting approval from a team member.') +
                            '</div>' +
                            '<button type="button" class="button" onclick="RPGMaps.hideModal()">' +
                                this.escapeHtml(this.config.lang.close || 'Close') +
                            '</button>';
                    } else {
                        this.hideModal();
                    }
                } else {
                    const errorMessage = response.message || response.error || 'Error submitting request';
                    console.error('RPGMaps: Build request failed:', errorMessage);
                    this.showMessage('error', errorMessage);
                }
            });
        },

        /**
         * Submit move-in request
         */
        submitMoveInRequest: function(form) {
            const houseId = form.querySelector('input[name="house_id"]').value;

            const data = {
                sub: 'rpgmaps_move_in_request',
                house_id: houseId,
                token: this.config.csrf_token,
            };

            this.ajax(data, (response) => {
                if (response.success) {
                    this.showMessage('success', 'Move-in request submitted. Awaiting admin approval.');
                    this.hideModal();
                } else {
                    const errorMessage = response.message || response.error || 'Error submitting request';
                    this.showMessage('error', errorMessage);
                }
            });
        },

        /**
         * Submit move-out request
         */
        submitMoveOutRequest: function(form) {
            const houseId = form.querySelector('input[name="house_id"]').value;

            const data = {
                sub: 'rpgmaps_move_out_request',
                house_id: houseId,
                token: this.config.csrf_token,
            };

            this.ajax(data, (response) => {
                if (response.success) {
                    this.showMessage('success', response.message || 'You have successfully moved out!');
                    this.hideModal();
                    this.hideTooltip();
                    
                    // If house was deleted (no occupants left), convert back to free plot
                    if (response.house_deleted && response.plot_id) {
                        const houseElement = document.querySelector('[data-houseid="' + houseId + '"]');
                        if (houseElement) {
                            const plotId = houseElement.getAttribute('data-plotid');
                            const x = houseElement.style.left;
                            const y = houseElement.style.top;
                            const w = houseElement.style.width;
                            const h = houseElement.style.height;
                            
                            // Remove house element
                            houseElement.remove();
                            
                            // Add free plot element
                            const overlay = document.querySelector('.rpgmaps-overlay');
                            if (overlay) {
                                const freePlot = document.createElement('div');
                                freePlot.className = 'rpgmaps-plot rpgmaps-plot-free';
                                freePlot.setAttribute('data-plotid', plotId);
                                freePlot.style.left = x;
                                freePlot.style.top = y;
                                freePlot.style.width = w;
                                freePlot.style.height = h;
                                overlay.appendChild(freePlot);
                            }
                        }
                    }
                } else {
                    const errorMessage = response.message || response.error || 'Error submitting request';
                    this.showMessage('error', errorMessage);
                }
            });
        },

        /**
         * Toggle description edit mode
         */
        toggleDescriptionEdit: function(houseId) {
            const displayDiv = document.getElementById('description-display-' + houseId);
            const editTextarea = document.getElementById('description-edit-' + houseId);
            const editBtn = document.getElementById('edit-btn-' + houseId);
            const saveBtn = document.getElementById('save-btn-' + houseId);
            const cancelBtn = document.getElementById('cancel-btn-' + houseId);
            
            if (displayDiv && editTextarea && editBtn && saveBtn && cancelBtn) {
                displayDiv.classList.add('hidden');
                editTextarea.classList.add('visible');
                editBtn.classList.add('hidden');
                saveBtn.classList.add('visible');
                cancelBtn.classList.add('visible');
            }
        },
        
        /**
         * Cancel description edit
         */
        cancelDescriptionEdit: function(houseId) {
            const displayDiv = document.getElementById('description-display-' + houseId);
            const editTextarea = document.getElementById('description-edit-' + houseId);
            const editBtn = document.getElementById('edit-btn-' + houseId);
            const saveBtn = document.getElementById('save-btn-' + houseId);
            const cancelBtn = document.getElementById('cancel-btn-' + houseId);
            
            if (displayDiv && editTextarea && editBtn && saveBtn && cancelBtn) {
                displayDiv.classList.remove('hidden');
                editTextarea.classList.remove('visible');
                editBtn.classList.remove('hidden');
                saveBtn.classList.remove('visible');
                cancelBtn.classList.remove('visible');
            }
        },
        
        /**
         * Save house description
         */
        saveDescription: function(houseId) {
            const editTextarea = document.getElementById('description-edit-' + houseId);
            if (!editTextarea) return;
            
            const description = editTextarea.value;
            
            const data = {
                sub: 'rpgmaps_update_description',
                house_id: houseId,
                description: description,
                token: this.config.csrf_token,
            };
            
            this.ajax(data, (response) => {
                if (response.success) {
                    // Update display with server-parsed BBCode
                    const displayDiv = document.getElementById('description-display-' + houseId);
                    if (displayDiv) {
                        if (response.description_html) {
                            displayDiv.innerHTML = response.description_html;
                        } else if (description.trim()) {
                            displayDiv.innerHTML = this.escapeHtml(description);
                        } else {
                            const emptyLabel = this.config.lang?.no_description || 'No description yet';
                            displayDiv.innerHTML = '<em>' + this.escapeHtml(emptyLabel) + '</em>';
                        }
                    }

                    this.cancelDescriptionEdit(houseId);
                    this.showMessage('success', 'Description updated successfully');
                } else {
                    const errorMessage = response.message || response.error || 'Error updating description';
                    this.showMessage('error', errorMessage);
                }
            });
        },
        
        /**
         * Make AJAX request
         */
        ajax: function(data, callback) {
            const form = new FormData();
            form.append('action', 'ajax_rpgmaps');
            Object.keys(data).forEach((key) => {
                form.append(key, data[key]);
            });

            fetch(this.config.ajax_url || 'rpgmaps.php', {
                method: 'POST',
                credentials: 'same-origin',
                body: form,
            })
            .then((response) => response.text())
            .then((text) => {
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('AJAX Error: Invalid JSON response:', text);
                    if (callback) {
                        callback({ success: false, error: 'Invalid JSON response' });
                    }
                    return;
                }
                if (callback) {
                    callback(data);
                }
            })
            .catch((error) => {
                console.error('AJAX Error:', error);
                if (callback) {
                    callback({ success: false, error: 'Network error' });
                }
            });
        },

        /**
         * Show message to user
         */
        showMessage: function(type, message) {
            const msg = document.createElement('div');
            msg.className = 'rpgmaps-message ' + type;
            msg.textContent = message;
            document.body.insertBefore(msg, document.body.firstChild);

            setTimeout(() => {
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 300);
            }, 3000);
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            };
            return text.replace(/[&<>"']/g, (m) => map[m]);
        },

        /**
         * Format first line label for built houses in map overview
         */
        formatHouseLabel: function(typeName, houseName) {
            const safeType = (typeName || '').trim();
            const safeHouseName = (houseName || '').trim();

            if (!safeHouseName) {
                return safeType;
            }

            return safeType + ' (' + safeHouseName + ')';
        },
    };

    // Global reference for inline event handlers
    window.RPGMaps = RPGMaps;

    // Initialize on document ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            RPGMaps.init();
        });
    } else {
        RPGMaps.init();
    }
})();
