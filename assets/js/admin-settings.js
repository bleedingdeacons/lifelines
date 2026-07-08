/* global jQuery */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var dirty = false;
        function markDirty() { dirty = true; }

        // --- Drag-to-reorder the displayed columns ---------------------------
        var list = document.getElementById('lifelines-display-columns');
        if (list && window.jQuery && jQuery.fn.sortable) {
            jQuery(list)
                .sortable({
                    handle: '.lifelines-sortable__handle',
                    axis: 'y',
                    cursor: 'grabbing'
                })
                // 'sortupdate' fires after a drag that changes the order.
                .on('sortupdate', markDirty);
        }

        // --- Warn before leaving with unsaved changes ------------------------
        var form = document.getElementById('lifelines-settings-form');
        if (!form) {
            return;
        }

        // Any edit in the settings form (checkboxes, number inputs) marks it dirty.
        form.addEventListener('change', markDirty);
        form.addEventListener('input', markDirty);

        // Saving is an intentional navigation — don't warn on it.
        form.addEventListener('submit', function () { dirty = false; });

        window.addEventListener('beforeunload', function (e) {
            if (!dirty) {
                return undefined;
            }
            // Triggers the browser's native "unsaved changes" confirmation.
            e.preventDefault();
            e.returnValue = '';
            return '';
        });
    });
})();
