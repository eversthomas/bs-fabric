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

    document.addEventListener('DOMContentLoaded', function () {
        initMediaFields();
        initCodeEditors();
        initRelationFields();
        initRelationSortable();
    });

    function initMediaFields() {
        document.addEventListener('click', function (event) {
            var target = event.target;

            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.classList.contains('bs-fabric-field-image-select') || target.classList.contains('bs-fabric-field-file-select')) {
                event.preventDefault();
                openMediaFrame(target);
                return;
            }

            if (target.classList.contains('bs-fabric-field-image-remove') || target.classList.contains('bs-fabric-field-file-remove')) {
                event.preventDefault();
                clearMediaField(target);
            }
        });
    }

    function openMediaFrame(button) {
        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }

        var container = button.closest('[data-bs-fabric-image-field], [data-bs-fabric-file-field]');

        if (!container) {
            return;
        }

        var isImage = container.hasAttribute('data-bs-fabric-image-field');
        var frame = wp.media({
            title: isImage ? 'Bild wählen' : 'Datei wählen',
            button: { text: 'Auswählen' },
            multiple: false,
            library: isImage ? { type: 'image' } : undefined,
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first().toJSON();
            var input = container.querySelector('input[type="hidden"]');

            if (input) {
                input.value = String(attachment.id);
            }

            if (isImage) {
                var preview = container.querySelector('.bs-fabric-field-image-preview');

                if (preview && attachment.url) {
                    preview.outerHTML = '<img src="' + attachment.url + '" class="bs-fabric-field-image-preview" alt="" />';
                }
            } else {
                var nameEl = container.querySelector('.bs-fabric-field-file-name');

                if (nameEl) {
                    nameEl.textContent = attachment.filename || attachment.title || ('#' + attachment.id);
                }
            }
        });

        frame.open();
    }

    function clearMediaField(button) {
        var container = button.closest('[data-bs-fabric-image-field], [data-bs-fabric-file-field]');

        if (!container) {
            return;
        }

        var input = container.querySelector('input[type="hidden"]');

        if (input) {
            input.value = '0';
        }

        var preview = container.querySelector('.bs-fabric-field-image-preview');

        if (preview) {
            preview.remove();
        }

        var nameEl = container.querySelector('.bs-fabric-field-file-name');

        if (nameEl) {
            nameEl.textContent = '';
        }
    }

    function initCodeEditors() {
        if (typeof wp === 'undefined' || !wp.codeEditor || !window.bsFabricEntryAdmin) {
            return;
        }

        document.querySelectorAll('.bs-fabric-field-code').forEach(function (textarea) {
            if (!(textarea instanceof HTMLTextAreaElement)) {
                return;
            }

            var language = textarea.getAttribute('data-language') || 'html';
            var settings = window.bsFabricEntryAdmin.codeEditor || {};
            var typeMap = {
                css: 'text/css',
                html: 'text/html',
                javascript: 'text/javascript',
                json: 'application/json',
                php: 'application/x-httpd-php',
                xml: 'application/xml',
            };

            wp.codeEditor.initialize(textarea, {
                codemirror: Object.assign({}, settings.codemirror || {}, {
                    mode: typeMap[language] || 'text/html',
                    lineNumbers: textarea.getAttribute('data-line-numbers') === '1',
                }),
            });
        });
    }

    function initRelationFields() {
        document.querySelectorAll('.bs-fabric-relation-field').forEach(function (field) {
            var searchInput = field.querySelector('.bs-fabric-relation-search');
            var results = field.querySelector('.bs-fabric-relation-results');
            var selected = field.querySelector('.bs-fabric-relation-selected');

            if (!searchInput || !results || !selected) {
                return;
            }

            var debounceTimer = null;

            searchInput.addEventListener('input', function () {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(function () {
                    searchRelations(field, searchInput.value, results);
                }, 300);
            });

            results.addEventListener('click', function (event) {
                var target = event.target;

                if (!(target instanceof HTMLElement) || !target.classList.contains('bs-fabric-relation-result')) {
                    return;
                }

                event.preventDefault();
                addRelationItem(field, selected, target);
                searchInput.value = '';
                results.innerHTML = '';
            });

            selected.addEventListener('click', function (event) {
                var target = event.target;

                if (!(target instanceof HTMLElement) || !target.classList.contains('bs-fabric-relation-remove')) {
                    return;
                }

                event.preventDefault();
                var item = target.closest('.bs-fabric-relation-item');

                if (item) {
                    item.remove();
                }
            });
        });
    }

    function searchRelations(field, query, resultsContainer) {
        var template = field.getAttribute('data-target-template') || '';
        var editRoot = document.querySelector('.bs-fabric-entry-edit');
        var entryId = editRoot ? parseInt(editRoot.getAttribute('data-entry-id') || '0', 10) : 0;

        postAjax('bs_fabric_search_relation_entries', {
            nonce: window.bsFabricAdmin.entryNonce,
            template: template,
            search: query,
            exclude_entry_id: entryId,
        })
            .then(function (payload) {
                resultsContainer.innerHTML = '';

                if (!payload || !payload.success || !payload.data || !payload.data.results) {
                    return;
                }

                payload.data.results.forEach(function (result) {
                    var item = document.createElement('li');
                    item.className = 'bs-fabric-relation-result';
                    item.setAttribute('data-entry-id', String(result.id));
                    item.setAttribute('data-entry-title', result.title);
                    item.textContent = result.title + ' (#' + result.id + ')';
                    resultsContainer.appendChild(item);
                });
            })
            .catch(function () {
                window.alert(window.bsFabricAdmin.strings.relationSearchError);
            });
    }

    function addRelationItem(field, selectedList, resultButton) {
        var entryId = resultButton.getAttribute('data-entry-id');
        var title = resultButton.getAttribute('data-entry-title') || entryId;
        var multiple = field.getAttribute('data-multiple') === '1';
        var fieldName = field.getAttribute('data-field-name') || '';
        var inputName = 'bs_fabric_fields[' + fieldName + ']' + (multiple ? '[]' : '');

        if (!entryId) {
            return;
        }

        var existing = selectedList.querySelector('.bs-fabric-relation-item[data-entry-id="' + entryId + '"]');

        if (existing) {
            window.alert(window.bsFabricAdmin.strings.relationExists);
            return;
        }

        if (!multiple) {
            selectedList.innerHTML = '';
        }

        var item = document.createElement('li');
        item.className = 'bs-fabric-relation-item';
        item.setAttribute('data-entry-id', entryId);
        item.innerHTML =
            '<span class="bs-fabric-relation-handle">⋮⋮</span>' +
            '<span class="bs-fabric-relation-label"></span>' +
            '<button type="button" class="button-link bs-fabric-relation-remove">Entfernen</button>' +
            '<input type="hidden" name="' + inputName + '" value="' + entryId + '" />';

        var label = item.querySelector('.bs-fabric-relation-label');

        if (label) {
            label.textContent = title;
        }

        selectedList.appendChild(item);
    }

    function initRelationSortable() {
        if (typeof window.Sortable === 'undefined') {
            return;
        }

        document.querySelectorAll('.bs-fabric-relation-selected').forEach(function (list) {
            window.Sortable.create(list, {
                handle: '.bs-fabric-relation-handle',
                animation: 150,
            });
        });
    }
}());
