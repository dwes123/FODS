jQuery(document).ready(function($) {
    // --- Configuration Constants ---
    const RATES = {
        'sp': { base: 3.3755, label: 'Starting Pitcher' },
        'rp': { base: 5.0131, label: 'Relief Pitcher' },
        'hitter': { base: 2.8354, label: 'Hitter' }
    };

    // Decay Factor Array (Years 1-8)
    const DECAY = [1.0, 0.926, 0.852, 0.796, 0.741, 0.704, 0.667, 0.667];

    let currentPos = 'sp';
    let selectedContract = null; // { years: int, aav: float }
    const modal = $('#extension-modal');
    const form = $('#fod-extension-form');

    // --- Open Modal Handler ---
    $(document).on('click', '.extend-player-button', function(e) {
        e.preventDefault();
        const btn = $(this);
        const pid = btn.data('playerid');
        const pname = btn.data('playername');
        const posRaw = btn.data('position') || '';
        
        // Reset Form
        $('#ext-player-id').val(pid);
        $('#ext-player-name-display').text(pname);
        $('#ext-war-1yr, #ext-war-2yr, #ext-war-3yr').val('');
        $('#ext-pricing-table, #ext-submit-area').addClass('hidden');
        $('#ext-message').html('');
        selectedContract = null;
        $('#ext-selected-summary').text('None');

        // Determine Tab
        let targetTab = 'hitter';
        if (posRaw === 'SP') targetTab = 'sp';
        else if (posRaw === 'RP') targetTab = 'rp';
        
        // Switch to correct tab
        $('.fod-tab').removeClass('active');
        $(`.fod-tab[data-pos="${targetTab}"]`).addClass('active');
        currentPos = targetTab;
        $('#ext-position-type').val(currentPos);

        // Show Modal
        modal.removeClass('fa-modal-hidden');
    });

    // --- Tab Switching ---
    $('.fod-tab').on('click', function() {
        $('.fod-tab').removeClass('active');
        $(this).addClass('active');
        currentPos = $(this).data('pos');
        $('#ext-position-type').val(currentPos);
        calculatePricing(); // Recalculate on tab switch
    });

    // --- Helper Inputs Logic ---
    // 1-Year WAR: Value * 3
    $('#ext-war-1yr').on('input', function() {
        const val = parseFloat($(this).val());
        if (!isNaN(val)) {
            $('#ext-war-3yr').val((val * 3).toFixed(1)).trigger('input');
        }
    });

    // 2-Year WAR: (Value / 2) * 3
    $('#ext-war-2yr').on('input', function() {
        const val = parseFloat($(this).val());
        if (!isNaN(val)) {
            $('#ext-war-3yr').val(((val / 2) * 3).toFixed(1)).trigger('input');
        }
    });

    // --- Main Calculation Logic ---
    $('#ext-war-3yr').on('input', function() {
        calculatePricing();
    });

    function calculatePricing() {
        const war3yr = parseFloat($('#ext-war-3yr').val());
        const tableBody = $('#ext-pricing-body');
        const tableContainer = $('#ext-pricing-table');
        const submitArea = $('#ext-submit-area');

        // Reset selection
        selectedContract = null;
        $('#ext-selected-summary').text('None');
        submitArea.addClass('hidden');
        $('.fod-row-select').removeClass('fod-row-selected');

        if (isNaN(war3yr) || war3yr <= 0) {
            tableContainer.addClass('hidden');
            tableBody.empty();
            return;
        }

        tableBody.empty();
        const baseRate = RATES[currentPos].base;

        // Generate rows for 1 to 8 years
        for (let i = 0; i < 8; i++) {
            const year = i + 1;
            const decay = DECAY[i];
            
            // Formula: Salary = Total_WAR * Base_Rate * Decay_Factor
            let aav = war3yr * baseRate * decay;
            
            // Rounding logic: standard money formatting
            if (aav < 0.7) aav = 0.7; // Min 700k check
            
            // Calculate Total
            const totalVal = aav * year;
            
            // Convert to Millions/Thousands for display
            // Assuming the system stores salaries as raw numbers (e.g. 1500000)
            // But the output implies "m". Let's stick to Millions for display in table.
            
            // Note: The system usually stores contracts as full integers (e.g. 1500000).
            // The prompt says "AAV for contract lengths".
            // Let's store the raw AAV as millions (float) for now based on the prompt's examples ($33.08)
            // But the pending_arb system usually expects full integers if it's salary.
            // Let's assume the prompt wants $33.08 MILLION.
            
            const displayAAV = aav.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            const displayTotal = totalVal.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // We will store the full integer value for submission
            const submitAAV = Math.round(aav * 1000000); 

            const row = `
                <tr class="fod-row-select" data-years="${year}" data-aav-raw="${submitAAV}" data-aav-display="${displayAAV}">
                    <td>${year} Year${year > 1 ? 's' : ''}</td>
                    <td>$${displayAAV}m</td>
                    <td>$${displayTotal}m</td>
                    <td><button type="button" class="button button-small select-contract-btn">Select</button></td>
                </tr>
            `;
            tableBody.append(row);
        }
        tableContainer.removeClass('hidden');
    }

    // --- Row Selection ---
    $(document).on('click', '.fod-row-select, .select-contract-btn', function(e) {
        console.log("Extension row or button clicked!");
        // Handle button click bubbling
        e.stopPropagation();
        let row = $(this).closest('tr');
        console.log("Closest TR:", row);
        
        // Visual selection
        $('.fod-row-select').removeClass('fod-row-selected');
        row.addClass('fod-row-selected');

        // Store Data
        selectedContract = {
            years: parseInt(row.data('years')),
            aavRaw: parseInt(row.data('aav-raw')),
            aavDisplay: row.data('aav-display')
        };
        console.log("Selected Contract:", selectedContract);

        // Update Summary
        const summary = `${selectedContract.years} Year${selectedContract.years > 1 ? 's' : ''} @ $${selectedContract.aavDisplay}m / yr`;
        $('#ext-selected-summary').text(summary);
        $('#ext-submit-area').removeClass('hidden');

        // Scroll to submit area for mobile users
        setTimeout(function() {
            document.getElementById('ext-submit-area').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }, 100);
    });

    // --- Submission ---
    form.on('submit', function(e) {
        e.preventDefault();

        if (!selectedContract) {
            alert('Please select a contract length from the table.');
            return;
        }
        
        const playerId = $('#ext-player-id').val();
        if (!playerId) {
            alert('Error: Player ID missing.');
            return;
        }

        const submitBtn = $('#ext-submit-btn');
        const msgDiv = $('#ext-message');

        submitBtn.prop('disabled', true).text('Submitting...');
        msgDiv.html('');

        $.ajax({
            url: fodExtData.ajax_url,
            type: 'POST',
            data: {
                action: 'submit_extension_request',
                nonce: fodExtData.nonce,
                player_id: playerId,
                years: selectedContract.years,
                aav: selectedContract.aavRaw, // Submit full integer
                war: $('#ext-war-3yr').val()
            },
            success: function(response) {
                if (response.success) {
                    msgDiv.html('<div class="notice notice-success" style="color: green; margin-bottom: 10px;">' + response.data + '</div>');
                    // Hide form parts
                    $('#ext-pricing-table, #ext-submit-area').addClass('hidden');
                    setTimeout(function() {
                        modal.addClass('fa-modal-hidden');
                        location.reload();
                    }, 2000);
                } else {
                    msgDiv.html('<div class="notice notice-error" style="color: red; margin-bottom: 10px;">Error: ' + response.data + '</div>');
                    submitBtn.prop('disabled', false).text('Submit Extension Request');
                }
            },
            error: function() {
                msgDiv.html('<div class="notice notice-error" style="color: red; margin-bottom: 10px;">Server error. Please try again.</div>');
                submitBtn.prop('disabled', false).text('Submit Extension Request');
            }
        });
    });

    // Close Modal
    modal.find('.fa-modal-close, .fa-modal-cancel').on('click', function() {
        modal.addClass('fa-modal-hidden');
    });
});