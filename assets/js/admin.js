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

    function getTemplateIdFromPage() {
        var root = document.querySelector('.bs-fabric-template-edit');

        if (!root) {
            return 0;
        }

        return parseInt(root.getAttribute('data-template-id') || '0', 10);
    }

    document.addEventListener('click', function (event) {
        var target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.classList.contains('bs-fabric-delete-field')) {
            event.preventDefault();
            handleDeleteField(target);
            return;
        }

        if (target.classList.contains('bs-fabric-delete-template')) {
            event.preventDefault();
            handleDeleteTemplate(target);
            return;
        }

        if (target.classList.contains('bs-fabric-assign-field')) {
            event.preventDefault();
            handleAssignField();
            return;
        }

        if (target.classList.contains('bs-fabric-remove-template-field')) {
            event.preventDefault();
            handleRemoveTemplateField(target);
            return;
        }

        if (target.classList.contains('bs-fabric-delete-entry')) {
            event.preventDefault();
            handleDeleteEntry(target);
        }
    });

    document.addEventListener('change', function (event) {
        var target = event.target;

        if (!(target instanceof HTMLInputElement) || !target.classList.contains('bs-fabric-field-required-toggle')) {
            return;
        }

        handleRequiredToggle(target);
    });

    document.addEventListener('DOMContentLoaded', function () {
        initTemplateFieldSortable();
    });

    function handleDeleteField(button) {
        if (!window.confirm(window.bsFabricAdmin.strings.confirmDeleteField)) {
            return;
        }

        var fieldId = button.getAttribute('data-field-id');

        if (!fieldId) {
            return;
        }

        postAjax('bs_fabric_delete_field', {
            nonce: window.bsFabricAdmin.fieldNonce,
            field_id: fieldId,
        })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    window.alert(getErrorMessage(payload, window.bsFabricAdmin.strings.deleteError));
                    return;
                }

                var row = button.closest('tr');

                if (row) {
                    row.remove();
                }
            })
            .catch(function () {
                window.alert(window.bsFabricAdmin.strings.deleteError);
            });
    }

    function handleDeleteTemplate(button) {
        if (!window.confirm(window.bsFabricAdmin.strings.confirmDeleteTemplate)) {
            return;
        }

        var templateId = button.getAttribute('data-template-id');

        if (!templateId) {
            return;
        }

        postAjax('bs_fabric_delete_template', {
            nonce: window.bsFabricAdmin.templateNonce,
            template_id: templateId,
        })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    window.alert(getErrorMessage(payload, window.bsFabricAdmin.strings.deleteError));
                    return;
                }

                var row = button.closest('tr');

                if (row) {
                    row.remove();
                }
            })
            .catch(function () {
                window.alert(window.bsFabricAdmin.strings.deleteError);
            });
    }

    function handleAssignField() {
        var templateId = getTemplateIdFromPage();
        var select = document.querySelector('.bs-fabric-available-field');

        if (!select || !(select instanceof HTMLSelectElement) || !templateId) {
            return;
        }

        var fieldId = select.value;

        if (!fieldId) {
            return;
        }

        postAjax('bs_fabric_assign_template_field', {
            nonce: window.bsFabricAdmin.templateNonce,
            template_id: templateId,
            field_id: fieldId,
            is_required: '0',
        })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    window.alert(getErrorMessage(payload, window.bsFabricAdmin.strings.assignError));
                    return;
                }

                window.location.reload();
            })
            .catch(function () {
                window.alert(window.bsFabricAdmin.strings.assignError);
            });
    }

    function handleDeleteEntry(button) {
        if (!window.confirm(window.bsFabricAdmin.strings.confirmDeleteEntry)) {
            return;
        }

        var entryId = button.getAttribute('data-entry-id');

        if (!entryId) {
            return;
        }

        postAjax('bs_fabric_delete_entry', {
            nonce: window.bsFabricAdmin.entryNonce,
            entry_id: entryId,
        })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    window.alert(getErrorMessage(payload, window.bsFabricAdmin.strings.deleteError));
                    return;
                }

                var row = button.closest('tr');

                if (row) {
                    row.remove();
                }
            })
            .catch(function () {
                window.alert(window.bsFabricAdmin.strings.deleteError);
            });
    }

    function handleRemoveTemplateField(button) {
        var templateId = getTemplateIdFromPage();
        var fieldId = button.getAttribute('data-field-id');

        if (!templateId || !fieldId) {
            return;
        }

        postAjax('bs_fabric_remove_template_field', {
            nonce: window.bsFabricAdmin.templateNonce,
            template_id: templateId,
            field_id: fieldId,
        })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    window.alert(getErrorMessage(payload, window.bsFabricAdmin.strings.removeError));
                    return;
                }

                var item = button.closest('.bs-fabric-template-field-item');

                if (item) {
                    item.remove();
                }
            })
            .catch(function () {
                window.alert(window.bsFabricAdmin.strings.removeError);
            });
    }

    function handleRequiredToggle(input) {
        var templateId = getTemplateIdFromPage();
        var fieldId = input.getAttribute('data-field-id');

        if (!templateId || !fieldId) {
            return;
        }

        postAjax('bs_fabric_update_template_field_required', {
            nonce: window.bsFabricAdmin.templateNonce,
            template_id: templateId,
            field_id: fieldId,
            is_required: input.checked ? '1' : '0',
        }).then(function (payload) {
            if (!payload || !payload.success) {
                input.checked = !input.checked;
                window.alert(getErrorMessage(payload, window.bsFabricAdmin.strings.deleteError));
            }
        }).catch(function () {
            input.checked = !input.checked;
        });
    }

    function initTemplateFieldSortable() {
        var list = document.getElementById('bs-fabric-template-fields');

        if (!list || typeof window.Sortable === 'undefined') {
            return;
        }

        var templateId = getTemplateIdFromPage();

        if (!templateId) {
            return;
        }

        window.Sortable.create(list, {
            handle: '.bs-fabric-template-field-handle',
            animation: 150,
            onEnd: function () {
                var fieldIds = Array.prototype.slice.call(list.querySelectorAll('.bs-fabric-template-field-item'))
                    .map(function (item) {
                        return item.getAttribute('data-field-id');
                    })
                    .filter(Boolean);

                postAjax('bs_fabric_update_template_field_order', {
                    nonce: window.bsFabricAdmin.templateNonce,
                    template_id: templateId,
                    field_ids: fieldIds,
                }).then(function (payload) {
                    if (!payload || !payload.success) {
                        window.alert(window.bsFabricAdmin.strings.orderError);
                    }
                }).catch(function () {
                    window.alert(window.bsFabricAdmin.strings.orderError);
                });
            },
        });
    }

    function getErrorMessage(payload, fallback) {
        if (payload && payload.data && payload.data.message) {
            return payload.data.message;
        }

        return fallback;
    }
}());
