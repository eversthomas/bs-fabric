(function () {
    'use strict';

    function postAjax(action, data) {
        if (!window.bsFabricAdmin) {
            return Promise.reject(new Error('bsFabricAdmin missing'));
        }

        var body = new URLSearchParams();
        body.set('action', action);

        Object.keys(data).forEach(function (key) {
            var value = data[key];

            if (Array.isArray(value)) {
                value.forEach(function (item) {
                    body.append(key + '[]', String(item));
                });
                return;
            }

            body.set(key, String(value));
        });

        return fetch(window.bsFabricAdmin.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: body.toString(),
        }).then(function (response) {
            return response.json();
        });
    }

    function getErrorMessage(payload, fallback) {
        if (payload && payload.data && payload.data.message) {
            return payload.data.message;
        }

        return fallback;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('.bs-fabric-entry-list');

        if (!root) {
            return;
        }

        var templateId = parseInt(root.getAttribute('data-template-id') || '0', 10);
        var bulkBar = root.querySelector('#bs-fabric-entry-bulk-bar');
        var countEl = root.querySelector('.bsf-bulk-count');
        var selectAll = root.querySelector('.bs-fabric-entry-select-all');
        var checkboxes = Array.prototype.slice.call(root.querySelectorAll('.bs-fabric-entry-select'));
        var strings = window.bsFabricAdmin.strings || {};

        function selectedIds() {
            return checkboxes
                .filter(function (input) {
                    return input.checked;
                })
                .map(function (input) {
                    return input.value;
                });
        }

        function updateBulkBar() {
            var ids = selectedIds();
            var count = ids.length;

            if (bulkBar) {
                bulkBar.hidden = count === 0;
            }

            if (countEl) {
                countEl.textContent = count === 1
                    ? strings.bulkSelectedOne || '1 ausgewählt'
                    : (count + ' ' + (strings.bulkSelectedMany || 'ausgewählt'));
            }

            if (selectAll) {
                selectAll.checked = count > 0 && count === checkboxes.length;
                selectAll.indeterminate = count > 0 && count < checkboxes.length;
            }
        }

        function clearSelection() {
            checkboxes.forEach(function (input) {
                input.checked = false;
            });
            updateBulkBar();
        }

        checkboxes.forEach(function (input) {
            input.addEventListener('change', updateBulkBar);
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(function (input) {
                    input.checked = selectAll.checked;
                });
                updateBulkBar();
            });
        }

        root.querySelectorAll('[data-bulk-action]').forEach(function (button) {
            button.addEventListener('click', function () {
                var action = button.getAttribute('data-bulk-action');
                var ids = selectedIds();

                if (!action || ids.length === 0 || !templateId) {
                    return;
                }

                var confirmMessage = null;

                if (action === 'delete') {
                    confirmMessage = strings.confirmBulkDelete;
                } else if (action === 'publish') {
                    confirmMessage = strings.confirmBulkPublish;
                } else if (action === 'duplicate') {
                    confirmMessage = strings.confirmBulkDuplicate;
                }

                if (confirmMessage && !window.confirm(confirmMessage)) {
                    return;
                }

                postAjax('bs_fabric_bulk_entries', {
                    nonce: window.bsFabricAdmin.entryNonce,
                    template_id: templateId,
                    bulk_action: action,
                    entry_ids: ids,
                })
                    .then(function (payload) {
                        if (!payload || !payload.success) {
                            window.alert(getErrorMessage(payload, strings.bulkError));
                            return;
                        }

                        window.location.reload();
                    })
                    .catch(function () {
                        window.alert(strings.bulkError);
                    });
            });
        });

        var clearButton = root.querySelector('[data-bulk-clear]');

        if (clearButton) {
            clearButton.addEventListener('click', clearSelection);
        }

        root.querySelectorAll('.bsf-row-link[data-href]').forEach(function (row) {
            row.addEventListener('click', function (event) {
                if (event.target.closest('[data-no-row-nav]')) {
                    return;
                }

                window.location.href = row.getAttribute('data-href');
            });
        });

        root.querySelectorAll('.bsf-entry-card[data-href]').forEach(function (card) {
            card.addEventListener('click', function (event) {
                if (event.target.closest('[data-no-row-nav]')) {
                    return;
                }

                window.location.href = card.getAttribute('data-href');
            });
        });

        updateBulkBar();
    });
}());
