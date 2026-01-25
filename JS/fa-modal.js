jQuery(document).ready(function($) {
    console.log('FOD JS Initialized (v1.9)');

    // Define the calculation function in a shared scope
    function calculateBidPoints(yearsSelect, aavInput, resultSpan) {
        if (!yearsSelect.length || !aavInput.length || !resultSpan.length) return;
        const aav = parseFloat(aavInput.val()) || 0;
        const selectedOption = yearsSelect.find('option:selected');
        if (!selectedOption.length) {
            resultSpan.text('0.00');
            return;
        }
        const years = parseInt(selectedOption.val(), 10);
        const multiplier = parseFloat(selectedOption.data('multiplier'));
        if (!isNaN(aav) && aav > 0 && !isNaN(years) && !isNaN(multiplier)) {
            const points = (years * aav * multiplier) / 1000000;
            resultSpan.text(points.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        } else {
            resultSpan.text('0.00');
        }
    }

    // --- 1. Free Agent Offer Modal ---
    var faModal = $('#fa-offer-modal');
    if (faModal.length) {
        var faForm = faModal.find('#fa-offer-form');
        var yearsSelect = faModal.find('#fa-bid-years');
        var aavInput = faModal.find('#fa-bid-aav');
        var resultSpan = faModal.find('#fa-bid-points-value');

        $(document).on('click', '.fa-offer-button', function(e) {
            e.preventDefault();
            var btn = $(this);
            faForm.find('#fa-modal-playerid').val(btn.data('playerid'));
            faForm.find('#fa-modal-leagueid').val(btn.data('leagueid'));
            faForm.find('#fa-modal-teamid').val(btn.data('teamid'));
            faModal.find('#fa-modal-title').text('Place Bid for ' + btn.data('playername'));
            faForm.find('select, input[type="number"]').val('');
            faModal.find('#fa-modal-message').html('');
            calculateBidPoints(yearsSelect, aavInput, resultSpan);

            $.ajax({
                url: faModalData.ajax_url,
                type: 'POST',
                data: { action: 'get_fa_sign_nonce', player_id: btn.data('playerid'), nonce: faModalData.get_fa_sign_nonce },
                success: function(response) {
                    if (response.success) {
                        faForm.find('#fa-modal-nonce').val(response.data.nonce);
                        faModal.removeClass('fa-modal-hidden');
                    } else {
                        alert('Error initializing form: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function() { alert('A server error occurred while preparing the bid form.'); }
            });
        });

        yearsSelect.on('change', function() { calculateBidPoints(yearsSelect, aavInput, resultSpan); });
        aavInput.on('input', function() { calculateBidPoints(yearsSelect, aavInput, resultSpan); });
        faModal.find('.fa-modal-close, .fa-modal-cancel').on('click', function() { faModal.addClass('fa-modal-hidden'); });
    }

    // --- 2. Standalone Bid Calculator ---
    var calculatorForm = $('#bid-calculator-form');
    if (calculatorForm.length) {
        var calcYears = calculatorForm.find('#bid-years'), calcAav = calculatorForm.find('#fa-bid-aav'), calcResult = calculatorForm.find('#bid-points-value');
        calculateBidPoints(calcYears, calcAav, calcResult);
        calcYears.on('change', function() { calculateBidPoints(calcYears, calcAav, calcResult); });
        calcAav.on('input', function() { calculateBidPoints(calcYears, calcAav, calcResult); });
    }

    // --- 3. IL Modal ---
    var ilModal = $('#il-modal');
    if (ilModal.length) {
        $(document).on('click', '.move-to-il-button', function(e) {
            e.preventDefault();
            var btn = $(this);
            $('#il-modal-playerid').val(btn.data('playerid'));
            $('#il-modal-title').text('Place ' + btn.data('playername') + ' on IL');
            $('#il-modal-message').html('');
            ilModal.removeClass('fa-modal-hidden');
        });

        $('#il-submit-button').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var data = {
                action: 'move_player_to_il',
                player_id: $('#il-modal-playerid').val(),
                il_duration: $('input[name="il_duration"]:checked').val(),
                nonce: faModalData.move_to_il_nonce
            };
            handleRosterAction(btn, data, function(success) {
                if (success) ilModal.addClass('fa-modal-hidden');
            });
        });
        ilModal.find('.fa-modal-close, .fa-modal-cancel').on('click', function() { ilModal.addClass('fa-modal-hidden'); });
    }

    // --- 4. DFA Modal ---
    var dfaModal = $('#dfa-modal');
    if (dfaModal.length) {
        $(document).on('click', '.dfa-player-button', function(e) {
            e.preventDefault();
            var btn = $(this);
            $('#dfa-modal-playerid').val(btn.data('playerid'));
            $('#dfa-modal-title').text('Designate ' + btn.data('playername') + ' for Assignment');
            $('#dfa-modal-message').html('');
            dfaModal.removeClass('fa-modal-hidden');
        });

        $('#dfa-submit-button').on('click', function(e) {
            e.preventDefault();
            var btn = $(this);
            var data = {
                action: 'dfa_player',
                player_id: $('#dfa-modal-playerid').val(),
                dfa_action: $('input[name="dfa_action"]:checked').val(),
                nonce: faModalData.dfa_player_nonce 
            };
            handleRosterAction(btn, data, function(success) {
                if (success) dfaModal.addClass('fa-modal-hidden');
            });
        });
        dfaModal.find('.fa-modal-close, .fa-modal-cancel').on('click', function() { dfaModal.addClass('fa-modal-hidden'); });
    }

    // --- 5. Generic Roster Action Handler ---
    function handleRosterAction(btn, data, onComplete) {
        var originalText = btn.text();
        btn.text('Processing...').prop('disabled', true);

        $.ajax({
            url: faModalData.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || response.data);
                    if (onComplete) onComplete(true);
                    location.reload();
                } else {
                    alert('Error: ' + (response.data || 'Unknown error'));
                    btn.text(originalText).prop('disabled', false);
                    if (onComplete) onComplete(false);
                }
            },
            error: function() {
                alert('Server error. Please try again.');
                btn.text(originalText).prop('disabled', false);
                if (onComplete) onComplete(false);
            }
        });
    }

    // --- 6. Roster Button Click Delegator ---
    $(document).on('click', '.promote-26-button, .option-minors-button, .promote-40-button, .activate-from-il-button, .claim-player-button', function(e) {
        e.preventDefault();
        var btn = $(this);
        var actions = {
            'promote-26-button': { action: 'promote_to_26man', nonce: 'promote_to_26man_nonce', msg: 'Promote this player to the active 26-man roster?' },
            'option-minors-button': { action: 'option_to_minors', nonce: 'option_to_minors_nonce', msg: 'Option this player to the minors? This will use an option year.' },
            'promote-40-button': { action: 'promote_to_40man', nonce: 'promote_to_40man_nonce', msg: 'Recall this player to the 40-man roster?' },
            'activate-from-il-button': { action: 'activate_from_il', nonce: 'activate_from_il_nonce', msg: 'Activate this player from the IL?' },
            'claim-player-button': { action: 'claim_player', nonce: 'claim_player_nonce', msg: 'Submit a waiver claim for this player?' }
        };
        
        var actionConfig = null;
        for (var cssClass in actions) {
            if (btn.hasClass(cssClass)) {
                actionConfig = actions[cssClass];
                break;
            }
        }

        if (!actionConfig || !confirm(actionConfig.msg)) return;

        var nonce = faModalData[actionConfig.nonce];
        if (!nonce) {
            alert('Security nonce is missing for this action. Please refresh the page.');
            return;
        }

        var data = {
            action: actionConfig.action,
            player_id: btn.data('playerid'),
            league_id: btn.data('leagueid'),
            team_id: btn.data('teamid'),
            nonce: nonce
        };
        
        handleRosterAction(btn, data);
    });

    // --- 7. Contract Restructure Modal ---
    var resModal = $('#restructure-modal');
    if (resModal.length) {
        var resForm = resModal.find('#restructure-form');
        var resLoading = resModal.find('#restructure-loading');
        var fromSelect = $('#restructure-from-year');
        var toSelect = $('#restructure-to-year');
        var amountInput = $('#restructure-amount');
        var playerContracts = {}; // Store for calculations

        $(document).on('click', '.restructure-player-button', function(e) {
            e.preventDefault();
            var btn = $(this);
            var pid = btn.data('playerid');
            
            resModal.find('h3').text('Restructure: ' + btn.data('playername'));
            resModal.find('#restructure-player-id').val(pid);
            resModal.find('#restructure-modal-message').html('');
            resForm.hide();
            resLoading.show();
            resModal.removeClass('fa-modal-hidden');

            $.ajax({
                url: faModalData.ajax_url,
                type: 'POST',
                data: { action: 'get_restructure_data', player_id: pid },
                success: function(response) {
                    resLoading.hide();
                    if (response.success) {
                        playerContracts = response.data.contracts;
                        populateRestructureFields(playerContracts);
                        resForm.show();
                    } else {
                        resModal.find('#restructure-modal-message').html('<div class="notice notice-error" style="color:red; margin-bottom:10px;">'+response.data+'</div>');
                    }
                }
            });
        });

        function populateRestructureFields(contracts) {
            fromSelect.empty().append('<option value="">-- Select Source Year --</option>');
            toSelect.empty().append('<option value="">-- Select Target Year --</option>');
            
            for (var yr in contracts) {
                var opt = $('<option>', { value: yr, text: yr + ' ($' + parseInt(contracts[yr]).toLocaleString() + ')' });
                fromSelect.append(opt.clone());
                toSelect.append(opt.clone());
            }
            amountInput.val('');
            $('#restructure-preview').hide();
        }

        // Live Preview & Validation
        resForm.on('change input', 'select, input', function() {
            var fromYr = fromSelect.val();
            var toYr = toSelect.val();
            var amount = parseFloat(amountInput.val()) || 0;

            if (fromYr) {
                var max = Math.floor(playerContracts[fromYr] * 0.5);
                $('#restructure-max-hint').text('Max move: $' + max.toLocaleString() + ' (50%)');
                amountInput.attr('max', max);
            }

            if (fromYr && toYr && amount > 0 && fromYr !== toYr) {
                var fromRem = playerContracts[fromYr] - amount;
                var toNew = playerContracts[toYr] + amount;
                
                $('#preview-from-yr').text(fromYr);
                $('#preview-from-amt').text('$' + fromRem.toLocaleString());
                $('#preview-to-yr').text(toYr);
                $('#preview-to-amt').text('$' + toNew.toLocaleString());
                $('#restructure-preview').show();
            } else {
                $('#restructure-preview').hide();
            }
        });

        resForm.on('submit', function(e) {
            e.preventDefault();
            if (fromSelect.val() === toSelect.val()) { alert('Source and Target years must be different.'); return; }
            
            var maxAllowed = Math.floor(playerContracts[fromSelect.val()] * 0.5);
            if (parseFloat(amountInput.val()) > maxAllowed) {
                alert('You cannot move more than 50% of the year\'s salary.');
                return;
            }

            if (!confirm('Are you sure you want to execute this restructure? This cannot be undone and counts as your 1 restructure for the year.')) return;

            var data = {
                action: 'process_restructure',
                player_id: $('#restructure-player-id').val(),
                from_year: fromSelect.val(),
                to_year: toSelect.val(),
                amount: amountInput.val(),
                nonce: faModalData.roster_move_nonce
            };

            handleRosterAction($('#restructure-submit-button'), data);
        });

        resModal.find('.fa-modal-close, .fa-modal-cancel').on('click', function() { resModal.addClass('fa-modal-hidden'); });
    }

    // --- 8. MiLB Offer Modal ---
    var milbModal = $('#fa-milb-modal');
    if (milbModal.length) {
        var milbForm = $('#fa-milb-form');
        var balanceDisplay = $('#milb-balance-display');
        var currentMilbBalance = 0;

        $(document).on('click', '.fa-milb-offer-button', function(e) {
            e.preventDefault();
            var btn = $(this);
            
            $('#milb-player-id').val(btn.data('playerid'));
            $('#milb-league-id').val(btn.data('leagueid'));
            $('#milb-team-id').val(btn.data('teamid'));
            $('#milb-player-name').text(btn.data('playername'));
            $('#milb-stat-value').val('');
            $('#milb-bid-amount').val('');
            $('#milb-modal-message').html('');
            balanceDisplay.text('Loading...');
            
            milbModal.removeClass('fa-modal-hidden');

            // Fetch Balance
            $.ajax({
                url: faModalData.ajax_url,
                type: 'POST',
                data: { 
                    action: 'get_team_financials', 
                    league_id: btn.data('leagueid'), 
                    team_id: btn.data('teamid') 
                },
                success: function(res) {
                    if (res.success) {
                        currentMilbBalance = parseInt(res.data.milb);
                        balanceDisplay.text('Available: $' + currentMilbBalance.toLocaleString());
                        $('#milb-bid-amount').attr('max', currentMilbBalance);
                    } else {
                        balanceDisplay.text('Error loading balance.');
                    }
                }
            });
        });

        milbForm.on('submit', function(e) {
            e.preventDefault();
            var statType = $('#milb-stat-type').val();
            var statVal = parseFloat($('#milb-stat-value').val());
            var bidAmt = parseFloat($('#milb-bid-amount').val());

            // 1. Validation
            if (isNaN(statVal) || isNaN(bidAmt)) { alert('Please enter valid numbers.'); return; }
            
            if (statType === 'IP' && statVal > 30) {
                alert('Ineligible: Pitchers must have 30 IP or less.'); return;
            }
            if (statType === 'AB' && statVal > 150) {
                alert('Ineligible: Hitters must have 150 ABs or less.'); return;
            }

            if (bidAmt > currentMilbBalance) {
                alert('Insufficient MiLB Funds. You only have $' + currentMilbBalance.toLocaleString()); return;
            }

            // 2. Submission
            var btn = $('#milb-submit-btn');
            var originalText = btn.text();
            btn.text('Processing...').prop('disabled', true);

            $.ajax({
                url: faModalData.ajax_url,
                type: 'POST',
                data: {
                    action: 'sign_milb_free_agent',
                    nonce: faModalData.sign_fa_nonce_milb,
                    player_id: $('#milb-player-id').val(),
                    league_id: $('#milb-league-id').val(),
                    team_id: $('#milb-team-id').val(),
                    stat_type: statType,
                    stat_value: statVal,
                    bid_amount: bidAmt
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Server error.');
                    btn.text(originalText).prop('disabled', false);
                }
            });
        });

        milbModal.find('.fa-modal-close, .fa-modal-cancel').on('click', function() { milbModal.addClass('fa-modal-hidden'); });
    }

    // --- 9. ISBP Offer Modal ---
    var isbpModal = $('#fa-isbp-modal');
    if (isbpModal.length) {
        var isbpForm = $('#fa-isbp-form');
        var isbpBalanceDisplay = $('#isbp-balance-display');
        var currentIsbpBalance = 0;

        $(document).on('click', '.fa-isbp-offer-button', function(e) {
            e.preventDefault();
            var btn = $(this);
            
            $('#isbp-player-id').val(btn.data('playerid'));
            $('#isbp-league-id').val(btn.data('leagueid'));
            $('#isbp-team-id').val(btn.data('teamid'));
            $('#isbp-player-name').text(btn.data('playername'));
            $('#isbp-bid-amount').val('');
            $('#isbp-modal-message').html('');
            isbpBalanceDisplay.text('Loading...');
            
            isbpModal.removeClass('fa-modal-hidden');

            // Fetch Balance
            $.ajax({
                url: faModalData.ajax_url,
                type: 'POST',
                data: { 
                    action: 'get_team_financials', 
                    league_id: btn.data('leagueid'), 
                    team_id: btn.data('teamid') 
                },
                success: function(res) {
                    if (res.success) {
                        currentIsbpBalance = parseInt(res.data.isbp);
                        isbpBalanceDisplay.text('Available: $' + currentIsbpBalance.toLocaleString());
                        $('#isbp-bid-amount').attr('max', currentIsbpBalance);
                    } else {
                        isbpBalanceDisplay.text('Error loading balance.');
                    }
                }
            });
        });

        isbpForm.on('submit', function(e) {
            e.preventDefault();
            var bidAmt = parseFloat($('#isbp-bid-amount').val());

            if (isNaN(bidAmt) || bidAmt <= 0) { alert('Please enter a valid bid amount.'); return; }
            
            if (bidAmt > currentIsbpBalance) {
                alert('Insufficient ISBP Funds. You only have $' + currentIsbpBalance.toLocaleString()); return;
            }

            var btn = $('#isbp-submit-btn');
            var originalText = btn.text();
            btn.text('Processing...').prop('disabled', true);

            $.ajax({
                url: faModalData.ajax_url,
                type: 'POST',
                data: {
                    action: 'sign_isbp_free_agent',
                    nonce: faModalData.sign_fa_nonce_milb, // Reusing generic nonce
                    player_id: $('#isbp-player-id').val(),
                    league_id: $('#isbp-league-id').val(),
                    team_id: $('#isbp-team-id').val(),
                    bid_amount: bidAmt
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data);
                        location.reload();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                        btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Server error.');
                    btn.text(originalText).prop('disabled', false);
                }
            });
        });

        isbpModal.find('.fa-modal-close, .fa-modal-cancel').on('click', function() { isbpModal.addClass('fa-modal-hidden'); });
    }

    // --- 10. Trade Block Modal ---
    var tbModal = $('#trade-block-modal');
    if (tbModal.length) {
        $(document).on('click', '.open-trade-block-modal', function(e) {
            e.preventDefault();
            var btn = $(this);
            $('#tb-modal-playerid').val(btn.data('playerid'));
            $('#tb-player-name-display').text(btn.data('playername'));
            $('#tb-on-block').prop('checked', btn.data('onblock') == 1);
            $('#tb-notes').val(btn.data('notes'));
            $('#trade-block-modal-message').html('');
            tbModal.removeClass('fa-modal-hidden');
        });

        $('#trade-block-form').on('submit', function(e) {
            e.preventDefault();
            var btn = $('#tb-submit-btn');
            var originalText = btn.text();
            btn.text('Saving...').prop('disabled', true);

            $.ajax({
                url: faModalData.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_trade_block',
                    nonce: faModalData.roster_move_nonce,
                    player_id: $('#tb-modal-playerid').val(),
                    on_block: $('#tb-on-block').is(':checked') ? 1 : 0,
                    notes: $('#tb-notes').val()
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        btn.text(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Server error.');
                    btn.text(originalText).prop('disabled', false);
                }
            });
        });

        tbModal.find('.fa-modal-close, .fa-modal-cancel').on('click', function() {
            tbModal.addClass('fa-modal-hidden');
        });
    }

    // --- 11. Quick Remove from Trade Block ---
    $(document).on('click', '.quick-remove-trade-block', function(e) {
        e.preventDefault();
        var btn = $(this);
        var pid = btn.data('playerid');

        if (!confirm('Remove this player from the Trade Block?')) return;

        var originalText = btn.text();
        btn.text('Removing...').prop('disabled', true);

        $.ajax({
            url: faModalData.ajax_url,
            type: 'POST',
            data: {
                action: 'update_trade_block',
                nonce: faModalData.roster_move_nonce,
                player_id: pid,
                on_block: 0,
                notes: ''
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                    btn.text(originalText).prop('disabled', false);
                }
            },
            error: function() {
                alert('Server error.');
                btn.text(originalText).prop('disabled', false);
            }
        });
    });
});