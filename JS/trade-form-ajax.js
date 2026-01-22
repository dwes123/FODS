// File: wp-content/themes/twentytwentytwo-child/js/trade-form-ajax.js
jQuery(document).ready(function($) {

    // --- Configuration ---
    const leagueSelectId = '#trade_league';
    const targetManagerSelectId = '#target_manager';
    const playersOfferedSelectId = '#players_offered';
    const playersRequestedSelectId = '#players_requested';
    const submitButtonId = '#propose-trade-submit';

    const targetManagerLoadingSpanId = '#target-manager-loading';
    const playersOfferedLoadingSpanId = '#players-offered-loading';
    const playersRequestedLoadingSpanId = '#players-requested-loading';
    
    let myPlayersCache = {};
    let targetPlayersCache = {};
    let targetManagersData = {}; // Cache manager objects to get their ISBP balance
    // --- End Configuration ---

    function toggleLoading(spanId, show) {
        const spanElement = $(spanId);
        if (spanElement.length) { spanElement.toggle(show); }
    }

    function updateDropdown(selectElementId, items, placeholderIfEmpty, defaultSelectedText, cacheObj) {
        const selectElement = $(selectElementId);
        if (!selectElement.length) {
            console.error("Could not find select element:", selectElementId);
            return;
        }
        selectElement.empty();

        if (cacheObj) { Object.keys(cacheObj).forEach(key => delete cacheObj[key]); }

        if (defaultSelectedText) {
            selectElement.append($('<option>', { value: '', text: defaultSelectedText, disabled: true, selected: true }));
        }

        if (items && items.length > 0) {
            $.each(items, function(index, item) {
                selectElement.append($('<option>', { value: item.id, text: item.name }));
                if (cacheObj) { cacheObj[item.id] = item; }
            });
            selectElement.prop('disabled', false);
        } else {
            if (!defaultSelectedText || items === null || items.length === 0) {
                selectElement.append($('<option>', { value: '', text: placeholderIfEmpty, disabled: true, selected: !defaultSelectedText }));
            }
            selectElement.prop('disabled', true);
        }
    }

    function fetchTradeableManagers(selectedLeague) {
        if (!selectedLeague) {
            updateDropdown(targetManagerSelectId, [], '-- Select League First --', '-- Select League First --');
            $(targetManagerSelectId).prop('disabled', true);
            updateDropdown(playersOfferedSelectId, [], '-- Select League First --', '-- Select League First --', myPlayersCache);
            $(playersOfferedSelectId).prop('disabled', true);
            updateDropdown(playersRequestedSelectId, [], '-- Select League & Target Manager First --', '-- Select League & Target Manager First --', targetPlayersCache);
            $(playersRequestedSelectId).prop('disabled', true);
            $(submitButtonId).prop('disabled', true);
            return;
        }
        toggleLoading(targetManagerLoadingSpanId, true);
        updateDropdown(targetManagerSelectId, [], 'Loading Managers...', 'Loading Managers...');

        $.ajax({
            url: tradeFormAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_managers_for_trade',
                nonce: tradeFormAjax.nonce,
                league_id: selectedLeague
            },
            success: function(response) {
                if (response.success) {
                    updateDropdown(targetManagerSelectId, response.data, '-- No other managers in this league --', '-- Select Manager --', targetManagersData);
                } else {
                    updateDropdown(targetManagerSelectId, [], '-- Error loading managers --', '-- Error --');
                    console.error("Error fetching managers:", response.data || 'No data in response');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                updateDropdown(targetManagerSelectId, [], '-- AJAX Error loading managers --', '-- AJAX Error --');
                console.error("AJAX error fetching managers:", textStatus, errorThrown);
            },
            complete: function() {
                toggleLoading(targetManagerLoadingSpanId, false);
                updateDropdown(playersRequestedSelectId, [], '-- Select Target Manager First --', '-- Select Target Manager First --', targetPlayersCache);
                $(playersRequestedSelectId).prop('disabled', true);
                $(submitButtonId).prop('disabled', true);
            }
        });
    }

    function fetchMyPlayers(selectedLeague) {
        if (!selectedLeague) {
            updateDropdown(playersOfferedSelectId, [], '-- Select League First --', '-- Select League First --', myPlayersCache);
            $(playersOfferedSelectId).prop('disabled', true);
            $(submitButtonId).prop('disabled', true);
            return;
        }
        toggleLoading(playersOfferedLoadingSpanId, true);
        updateDropdown(playersOfferedSelectId, [], 'Loading Your Players...', 'Loading Your Players...', myPlayersCache);

        $.ajax({
            url: tradeFormAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_my_players_for_trade',
                nonce: tradeFormAjax.nonce,
                league_id: selectedLeague
            },
            success: function(response) {
                if (response.success) {
                    updateDropdown(playersOfferedSelectId, response.data.players, '-- You have no players in this league --', '-- Select Player(s) to Offer --', myPlayersCache);
                    if (response.data.isbp_balance !== undefined) {
                        $('#my-isbp-balance-display').text('Available: $' + parseInt(response.data.isbp_balance).toLocaleString());
                    }
                } else {
                    updateDropdown(playersOfferedSelectId, [], '-- Error loading your players --', '-- Error --', myPlayersCache);
                    console.error("Error fetching my players:", response.data || 'No data in response');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                updateDropdown(playersOfferedSelectId, [], '-- AJAX Error loading your players --', '-- AJAX Error --', myPlayersCache);
                console.error("AJAX error fetching my players:", textStatus, errorThrown);
            },
            complete: function() {
                toggleLoading(playersOfferedLoadingSpanId, false);
                $(submitButtonId).prop('disabled', true);
            }
        });
    }

    function fetchTargetPlayers(selectedLeague, targetManagerId) {
        if (!selectedLeague || !targetManagerId) {
            updateDropdown(playersRequestedSelectId, [], '-- Select League & Target Manager First --', '-- Select League & Target Manager First --', targetPlayersCache);
            $(playersRequestedSelectId).prop('disabled', true);
            $(submitButtonId).prop('disabled', true);
            return;
        }
        toggleLoading(playersRequestedLoadingSpanId, true);
        updateDropdown(playersRequestedSelectId, [], 'Loading Target Players...', 'Loading Target Players...', targetPlayersCache);

        // Update target ISBP balance display
        const targetMgr = targetManagersData[targetManagerId];
        if (targetMgr && targetMgr.isbp_balance !== undefined) {
            $('#target-isbp-balance-display').text('Available: $' + parseInt(targetMgr.isbp_balance).toLocaleString());
        }

        $.ajax({
            url: tradeFormAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_target_players_for_trade',
                nonce: tradeFormAjax.nonce,
                league_id: selectedLeague,
                target_manager_id: targetManagerId
            },
            success: function(response) {
                if (response.success) {
                    updateDropdown(playersRequestedSelectId, response.data, '-- Target manager has no players --', '-- Select Player(s) to Request --', targetPlayersCache);
                } else {
                    updateDropdown(playersRequestedSelectId, [], '-- Error loading target players --', '-- Error --', targetPlayersCache);
                    console.error("Error fetching target players:", response.data || 'No data in response');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                updateDropdown(playersRequestedSelectId, [], '-- AJAX Error loading target players --', '-- AJAX Error --', targetPlayersCache);
                console.error("AJAX error fetching target players:", textStatus, errorThrown);
            },
            complete: function() {
                toggleLoading(playersRequestedLoadingSpanId, false);
                validateFormState();
            }
        });
    }

    function validateFormState() {
        const isLeagueSelected = $(leagueSelectId).val();
        const isManagerSelected = $(targetManagerSelectId).val();
        const offeredIds = $(playersOfferedSelectId).val() || [];
        const requestedIds = $(playersRequestedSelectId).val() || [];
        const isbpOffered = parseInt($('#isbp_offered').val()) || 0;
        const isbpRequested = parseInt($('#isbp_requested').val()) || 0;

        const hasAssets = (offeredIds.length > 0 || isbpOffered > 0) && (requestedIds.length > 0 || isbpRequested > 0);
        
        $(submitButtonId).prop('disabled', !(isLeagueSelected && isManagerSelected && hasAssets));
        updateTradePreview();
    }

    function updateRetentionUI() {
        var container = $('#retention-checkboxes');
        var wrapper = $('#salary-retention-container');
        container.empty();
        
        var selectedIds = [];
        var selectedNames = [];

        // Get Offered Players
        $(playersOfferedSelectId + ' option:selected').each(function() {
            selectedIds.push($(this).val());
            selectedNames.push($(this).text());
        });

        // Get Requested Players
        $(playersRequestedSelectId + ' option:selected').each(function() {
            selectedIds.push($(this).val());
            selectedNames.push($(this).text());
        });

        if (selectedIds.length === 0) {
            wrapper.hide();
            $('#retained_player_ids').val('');
            return;
        }

        wrapper.show();
        
        selectedIds.forEach(function(pid, index) {
            var name = selectedNames[index];
            var checkboxId = 'retain_' + pid;
            var html = '<div style="margin-bottom: 5px;">';
            html += '<input type="checkbox" class="retention-check" id="' + checkboxId + '" value="' + pid + '">';
            html += ' <label for="' + checkboxId + '">Retain 50% for <strong>' + name + '</strong></label>';
            html += '</div>';
            container.append(html);
        });
        
        // Restore checked state if re-rendering (optional complexity, skipping for now to keep simple)
    }

    $(document).on('change', '.retention-check', function() {
        var retained = [];
        $('.retention-check:checked').each(function() {
            retained.push($(this).val());
        });
        $('#retained_player_ids').val(retained.join(','));
        updateTradePreview(); // Recalculate impact
    });

    function updateTradePreview() {
        const preview = $('#trade-summary-preview');
        const offeredList = $('#preview-offered-list');
        const requestedList = $('#preview-requested-list');
        const offeredIsbp = $('#preview-offered-isbp');
        const requestedIsbp = $('#preview-requested-isbp');
        const impactBody = $('#salary-impact-body');

        offeredList.empty();
        requestedList.empty();
        offeredIsbp.text('');
        requestedIsbp.text('');
        impactBody.empty();

        const selectedOfferedIds = $(playersOfferedSelectId).val() || [];
        const selectedRequestedIds = $(playersRequestedSelectId).val() || [];
        const isbpOfferedVal = parseInt($('#isbp_offered').val()) || 0;
        const isbpRequestedVal = parseInt($('#isbp_requested').val()) || 0;
        
        // Get Retained IDs
        const retainedVal = $('#retained_player_ids').val();
        const retainedIds = (retainedVal || '').split(',').filter(x => x);
        const currentYear = new Date().getFullYear().toString();

        // Calculate dynamic retention percentage (Date-Based Pro-Rating)
        let proRatePct = 0.0;
        const now = new Date();
        const todayStr = now.getFullYear() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
        const openingDay = tradeFormAjax.opening_day; // Ymd
        
        const april30 = currentYear + '0430';
        const may31 = currentYear + '0531';
        const june1 = currentYear + '0601';

        if (openingDay && todayStr >= openingDay && todayStr <= april30) {
            proRatePct = 0.10;
        } else if (todayStr >= (currentYear + '0501') && todayStr <= may31) {
            proRatePct = 0.25;
        } else if (todayStr >= june1) {
            proRatePct = 0.50;
        }

        console.log("Date-Based Pro-Rate %:", (proRatePct * 100) + "%");

        if (selectedOfferedIds.length === 0 && selectedRequestedIds.length === 0 && isbpOfferedVal === 0 && isbpRequestedVal === 0) {
            preview.hide();
            return;
        }

        preview.show();

        let totals = {
            [currentYear]: { out: 0, in: 0 },
            [parseInt(currentYear)+1]: { out: 0, in: 0 },
            [parseInt(currentYear)+2]: { out: 0, in: 0 }
        };

        const parseSalary = (val) => {
            if (!val) return 0;
            const clean = String(val).replace(/[$,]/g, '');
            return isNaN(clean) ? 0 : parseFloat(clean);
        };

        const calculateNetImpact = (salary, isRetained, yr) => {
            if (String(yr) !== String(currentYear)) return salary;
            
            // 1. Mandatory Pro-Rating
            let baseDeadCap = salary * proRatePct;
            let remainder = salary - baseDeadCap;
            
            // 2. Optional 50% Retention
            let extraRetention = 0;
            if (isRetained) {
                extraRetention = remainder * 0.5;
            }
            
            let totalDeadCap = baseDeadCap + extraRetention;
            let finalReceiverCost = salary - totalDeadCap;
            
            return finalReceiverCost;
        };

        selectedOfferedIds.forEach(id => {
            const p = myPlayersCache[id];
            if (p) {
                let retentionLabel = '';
                let isRetained = retainedIds.includes(String(id));
                
                if (isRetained) { retentionLabel = ' (50% Retained)'; }
                else if (proRatePct > 0) { retentionLabel = ' (Pro-Rated)'; }
                
                offeredList.append('<li>' + p.name + retentionLabel + '</li>');
                
                Object.keys(totals).forEach(yr => {
                    let salary = parseSalary(p.salaries[yr]);
                    // Amount "OUT" is the amount saved (transferred to receiver)
                    let savedAmount = calculateNetImpact(salary, isRetained, yr);
                    totals[yr].out += savedAmount;
                });
            }
        });

        selectedRequestedIds.forEach(id => {
            const p = targetPlayersCache[id];
            if (p) {
                let retentionLabel = '';
                let isRetained = retainedIds.includes(String(id));
                
                if (isRetained) { retentionLabel = ' (50% Retained)'; }
                else if (proRatePct > 0) { retentionLabel = ' (Pro-Rated)'; }

                requestedList.append('<li>' + p.name + retentionLabel + '</li>');
                
                Object.keys(totals).forEach(yr => {
                    let salary = parseSalary(p.salaries[yr]);
                    // Amount "IN" is the amount inherited
                    let costAmount = calculateNetImpact(salary, isRetained, yr);
                    totals[yr].in += costAmount;
                });
            }
        });

        if (isbpOfferedVal > 0) { offeredIsbp.text('+ $' + isbpOfferedVal.toLocaleString() + ' ISBP'); }
        if (isbpRequestedVal > 0) { requestedIsbp.text('+ $' + isbpRequestedVal.toLocaleString() + ' ISBP'); }

        // Fill Salary Impact Table
        Object.keys(totals).forEach(yr => {
            const net = totals[yr].in - totals[yr].out;
            const netColor = net > 0 ? 'red' : (net < 0 ? 'green' : 'inherit');
            const netSign = net > 0 ? '+' : '';
            
            impactBody.append(`
                <tr>
                    <td><strong>${yr}</strong></td>
                    <td>-$${totals[yr].out.toLocaleString()}</td>
                    <td>+$${totals[yr].in.toLocaleString()}</td>
                    <td style="color: ${netColor}; font-weight: bold;">${netSign}$${net.toLocaleString()}</td>
                </tr>
            `);
        });
    }

    $(leagueSelectId).on('change', function() {
        const selectedLeague = $(this).val();
        fetchTradeableManagers(selectedLeague);
        fetchMyPlayers(selectedLeague);
        updateDropdown(playersRequestedSelectId, [], '-- Select Target Manager First --', '-- Select Target Manager First --');
        validateFormState();
    });

    $(targetManagerSelectId).on('change', function() {
        const targetManagerId = $(this).val();
        const selectedLeague = $(leagueSelectId).val();
        fetchTargetPlayers(selectedLeague, targetManagerId);
        validateFormState();
    });

    $(playersOfferedSelectId).on('change', function() { 
        updateRetentionUI(); 
        validateFormState(); 
    });
    
    $(playersRequestedSelectId).on('change', function() { 
        updateRetentionUI(); 
        validateFormState(); 
    });
    
    $('#isbp_offered, #isbp_requested').on('input', function() { validateFormState(); });

    // Initial state
    $(targetManagerSelectId).prop('disabled', true);
    $(playersOfferedSelectId).prop('disabled', true);
    $(playersRequestedSelectId).prop('disabled', true);
    $(submitButtonId).prop('disabled', true);

    if ($(leagueSelectId).val()) {
        $(leagueSelectId).trigger('change');
    }
});
