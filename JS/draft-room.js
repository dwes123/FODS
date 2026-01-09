jQuery(document).ready(function($) {
    const leagueSelect = $('#draft-league-select');
    const teamSelect = $('#draft-team-select');
    const teamLoading = $('#draft-team-loading');
    const actionArea = $('#draft-action-area');
    const currentDraftingTeam = $('#current-drafting-team');
    const playerSearchInput = $('#draft-player-search-input');
    const playerResultsDiv = $('#draft-player-results');
    const selectedPlayerId = $('#selected-player-id');
    const selectedPlayerDisplay = $('#selected-player-display');
    const selectedPlayerName = $('#selected-player-name');
    const selectedPlayerPos = $('#selected-player-pos');
    const clearSelectedPlayerBtn = $('#clear-selected-player');
    const submitButton = $('#submit-draft-pick');
    const messageArea = $('#draft-message-area');
    const processingSpan = $('#draft-processing');

    // 1. Handle League Selection
    leagueSelect.on('change', function() {
        const leagueId = $(this).val();
        teamSelect.prop('disabled', true).html('<option value="">-- Select League First --</option>');
        actionArea.hide();
        currentDraftingTeam.text('None');

        if (!leagueId) return;

        teamLoading.show();
        $.ajax({
            url: draftRoomAjax.ajax_url,
            type: 'POST',
            data: {
                action: 'get_draft_teams',
                nonce: draftRoomAjax.nonce,
                league_id: leagueId
            },
            success: function(response) {
                if (response.success) {
                    teamSelect.html('<option value="">-- Select Team --</option>');
                    response.data.forEach(function(teamId) {
                        teamSelect.append($('<option>', { value: teamId, text: teamId }));
                    });
                    teamSelect.prop('disabled', false);
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('AJAX error fetching teams.');
            },
            complete: function() {
                teamLoading.hide();
            }
        });
    });

    // 2. Handle Team Selection
    teamSelect.on('change', function() {
        const teamId = $(this).val();
        if (teamId) {
            actionArea.show();
            currentDraftingTeam.text(teamId);
            validateCanSubmit();
        } else {
            actionArea.hide();
            currentDraftingTeam.text('None');
        }
    });

    // 3. Player Search
    let searchTimeout;
    playerSearchInput.on('keyup', function() {
        const searchTerm = $(this).val();
        const leagueId = leagueSelect.val();

        clearTimeout(searchTimeout);
        if (searchTerm.length < 2) {
            playerResultsDiv.hide();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: draftRoomAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'search_draft_players',
                    nonce: draftRoomAjax.nonce,
                    league_id: leagueId,
                    term: searchTerm
                },
                success: function(response) {
                    playerResultsDiv.empty().show();
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(function(player) {
                            const item = $('<div>', { class: 'draft-search-result-item' })
                                .text(player.name + ' (' + player.info + ')')
                                .attr('data-player-id', player.id)
                                .attr('data-player-name', player.name)
                                .attr('data-player-info', player.info);
                            playerResultsDiv.append(item);
                        });
                    } else {
                        playerResultsDiv.html('<div class="draft-search-result-item">No available players found.</div>');
                    }
                }
            });
        }, 300);
    });

    // 4. Select a Player from Results
    $(document).on('click', '.draft-search-result-item', function() {
        const playerId = $(this).data('player-id');
        if (!playerId) return;

        selectedPlayerId.val(playerId);
        selectedPlayerName.text($(this).data('player-name'));
        selectedPlayerPos.text('(' + $(this).data('player-info') + ')');
        
        playerSearchInput.hide();
        playerResultsDiv.hide().empty();
        selectedPlayerDisplay.show();
        validateCanSubmit();
    });

    // 5. Clear Selected Player
    clearSelectedPlayerBtn.on('click', function() {
        selectedPlayerId.val('');
        playerSearchInput.val('').show();
        selectedPlayerDisplay.hide();
        validateCanSubmit();
    });

    // 6. Submit Draft Pick
    submitButton.on('click', function() {
        if ($(this).prop('disabled')) return;

        const pickData = {
            action: 'submit_draft_pick',
            nonce: draftRoomAjax.nonce,
            player_id: selectedPlayerId.val(),
            team_id: teamSelect.val(),
            league_id: leagueSelect.val(),
            contracts: {}
        };

        $('.draft-contract-input').each(function() {
            const year = $(this).data('year');
            const amount = $(this).val();
            if (amount && parseFloat(amount) > 0) {
                pickData.contracts[year] = amount;
            }
        });

        processingSpan.show();
        submitButton.prop('disabled', true);
        messageArea.hide();

        $.ajax({
            url: draftRoomAjax.ajax_url,
            type: 'POST',
            data: pickData,
            success: function(response) {
                if (response.success) {
                    messageArea.html('<div class="notice notice-success"><p>' + response.data + '</p></div>').show();
                    // Reset form for next pick
                    clearSelectedPlayerBtn.click();
                    $('.draft-contract-input').val('');
                } else {
                    messageArea.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>').show();
                }
            },
            error: function() {
                messageArea.html('<div class="notice notice-error"><p>AJAX error submitting pick.</p></div>').show();
            },
            complete: function() {
                processingSpan.hide();
                validateCanSubmit();
            }
        });
    });

    function validateCanSubmit() {
        const canSubmit = leagueSelect.val() && teamSelect.val() && selectedPlayerId.val();
        submitButton.prop('disabled', !canSubmit);
    }

    // Hide search results if clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.draft-player-search').length) {
            playerResultsDiv.hide();
        }
    });
});
