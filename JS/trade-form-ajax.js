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
    // --- End Configuration ---

    function toggleLoading(spanId, show) {
        const spanElement = $(spanId);
        if (spanElement.length) { spanElement.toggle(show); }
    }

    function updateDropdown(selectElementId, items, placeholderIfEmpty, defaultSelectedText) {
        const selectElement = $(selectElementId);
        if (!selectElement.length) {
            console.error("Could not find select element:", selectElementId);
            return;
        }
        selectElement.empty();

        if (defaultSelectedText) {
            selectElement.append($('<option>', { value: '', text: defaultSelectedText, disabled: true, selected: true }));
        }

        if (items && items.length > 0) {
            $.each(items, function(index, item) {
                selectElement.append($('<option>', { value: item.id, text: item.name }));
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
            updateDropdown(playersOfferedSelectId, [], '-- Select League First --', '-- Select League First --');
            $(playersOfferedSelectId).prop('disabled', true);
            updateDropdown(playersRequestedSelectId, [], '-- Select League & Target Manager First --', '-- Select League & Target Manager First --');
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
                    updateDropdown(targetManagerSelectId, response.data, '-- No other managers in this league --', '-- Select Manager --');
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
                updateDropdown(playersRequestedSelectId, [], '-- Select Target Manager First --', '-- Select Target Manager First --');
                $(playersRequestedSelectId).prop('disabled', true);
                $(submitButtonId).prop('disabled', true);
            }
        });
    }

    function fetchMyPlayers(selectedLeague) {
        if (!selectedLeague) {
            updateDropdown(playersOfferedSelectId, [], '-- Select League First --', '-- Select League First --');
            $(playersOfferedSelectId).prop('disabled', true);
            $(submitButtonId).prop('disabled', true);
            return;
        }
        toggleLoading(playersOfferedLoadingSpanId, true);
        updateDropdown(playersOfferedSelectId, [], 'Loading Your Players...', 'Loading Your Players...');

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
                    updateDropdown(playersOfferedSelectId, response.data, '-- You have no players in this league --', '-- Select Player(s) to Offer --');
                } else {
                    updateDropdown(playersOfferedSelectId, [], '-- Error loading your players --', '-- Error --');
                    console.error("Error fetching my players:", response.data || 'No data in response');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                updateDropdown(playersOfferedSelectId, [], '-- AJAX Error loading your players --', '-- AJAX Error --');
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
            updateDropdown(playersRequestedSelectId, [], '-- Select League & Target Manager First --', '-- Select League & Target Manager First --');
            $(playersRequestedSelectId).prop('disabled', true);
            $(submitButtonId).prop('disabled', true);
            return;
        }
        toggleLoading(playersRequestedLoadingSpanId, true);
        updateDropdown(playersRequestedSelectId, [], 'Loading Target Players...', 'Loading Target Players...');

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
                    updateDropdown(playersRequestedSelectId, response.data, '-- Target manager has no players --', '-- Select Player(s) to Request --');
                } else {
                    updateDropdown(playersRequestedSelectId, [], '-- Error loading target players --', '-- Error --');
                    console.error("Error fetching target players:", response.data || 'No data in response');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                updateDropdown(playersRequestedSelectId, [], '-- AJAX Error loading target players --', '-- AJAX Error --');
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
        const arePlayersOffered = $(playersOfferedSelectId).val() && $(playersOfferedSelectId).val().length > 0;
        const arePlayersRequested = $(playersRequestedSelectId).val() && $(playersRequestedSelectId).val().length > 0;
        $(submitButtonId).prop('disabled', !(isLeagueSelected && isManagerSelected && arePlayersOffered && arePlayersRequested));
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

    $(playersOfferedSelectId).on('change', function() { validateFormState(); });
    $(playersRequestedSelectId).on('change', function() { validateFormState(); });

    // Initial state
    $(targetManagerSelectId).prop('disabled', true);
    $(playersOfferedSelectId).prop('disabled', true);
    $(playersRequestedSelectId).prop('disabled', true);
    $(submitButtonId).prop('disabled', true);

    if ($(leagueSelectId).val()) {
        $(leagueSelectId).trigger('change');
    }
});
