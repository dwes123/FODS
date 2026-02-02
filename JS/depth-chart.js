jQuery(document).ready(function($) {
    const editBtn = $('#depth-chart-edit-mode-btn');
    const saveBtn = $('#depth-chart-save-btn');
    const pods = $('.depth-player-list');
    let isEditMode = false;

    // 1. Toggle Edit Mode
    editBtn.on('click', function() {
        isEditMode = !isEditMode;
        
        if (isEditMode) {
            $(this).text('Cancel Edit').addClass('is-active');
            $('.depth-chart-wrapper').addClass('is-editing');
            enableSortable();
        } else {
            $(this).text('Edit Depth Chart').removeClass('is-active');
            $('.depth-chart-wrapper').removeClass('is-editing');
            disableSortable();
            saveBtn.hide(); // Hide save if canceling
        }
    });

    // 2. Enable jQuery UI Sortable
    function enableSortable() {
        pods.sortable({
            connectWith: '.depth-player-list', // Allow moving between pods (optional, usually kept within pos)
            cursor: 'grabbing',
            placeholder: 'depth-sortable-placeholder',
            opacity: 0.8,
            update: function(event, ui) {
                // Show save button when a change happens
                saveBtn.fadeIn();
            }
        }).disableSelection();
    }

    function disableSortable() {
        pods.sortable('destroy');
    }

    // 3. Save Changes
    saveBtn.on('click', function() {
        const btn = $(this);
        const ranks = {};
        
        // Loop through all lists to get new order
        $('.depth-player-list').each(function() {
            $(this).find('.depth-player-card').each(function(index) {
                const pid = $(this).data('player-id');
                // Rank = index + 1 (1-based)
                if (pid) {
                    ranks[pid] = index + 1;
                }
            });
        });

        btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: faModalData.ajax_url, // Using existing localized object
            type: 'POST',
            data: {
                action: 'save_depth_order',
                nonce: faModalData.roster_move_nonce,
                ranks: ranks
            },
            success: function(response) {
                if (response.success) {
                    btn.text('Saved!').css('background-color', '#28a745');
                    setTimeout(function() {
                        btn.hide().text('Save Changes').prop('disabled', false).css('background-color', '');
                        // Optional: Reload to refresh strict ordering? 
                        // For now, let's just keep them in the new visual order.
                    }, 1500);
                } else {
                    alert('Error saving: ' + (response.data || 'Unknown error'));
                    btn.prop('disabled', false).text('Save Changes');
                }
            },
            error: function() {
                alert('Server error.');
                btn.prop('disabled', false).text('Save Changes');
            }
        });
    });
});
