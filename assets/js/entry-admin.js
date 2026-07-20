(function () {
    'use strict';

    var mediaFrames = {};

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
        initReferenceFields();
        initReferenceSortable();
    });

    function initMediaFields() {
        document.addEventListener('click', function (event) {
            var target = event.target;

            if (!(target instanceof HTMLElement)) {
                return;
            }

            if (target.classList.contains('bs-fabric-field-image-select') || target.classList.contains('bs-fabric-field-file-select')) {
                event.preventDefault();
                event.stopPropagation();
                openMediaFrame(target);
                return;
            }

            if (target.classList.contains('bs-fabric-field-image-remove') || target.classList.contains('bs-fabric-field-file-remove')) {
                event.preventDefault();
                event.stopPropagation();
                clearMediaField(target);
            }
        });
    }

    function getFieldId(container) {
        return container.getAttribute('data-field-id') || '';
    }

    function blurActiveEditor() {
        if (typeof window.tinymce !== 'undefined' && window.tinymce.activeEditor) {
            window.tinymce.activeEditor.focus(false);
        }
    }

    function detachMediaEditor() {
        if (typeof wp === 'undefined' || !wp.media || !wp.media.editor) {
            return;
        }

        if (typeof wp.media.editor.remove === 'function') {
            wp.media.editor.remove();
        }
    }

    function getOrCreateMediaFrame(container, isImage) {
        if (typeof wp === 'undefined' || !wp.media) {
            return null;
        }

        var fieldId = getFieldId(container);

        if (!fieldId) {
            return null;
        }

        if (mediaFrames[fieldId]) {
            return mediaFrames[fieldId];
        }

        var frame = wp.media({
            id: 'bs-fabric-media-' + fieldId,
            frame: 'select',
            title: isImage ? 'Bild wählen' : 'Datei wählen',
            button: { text: 'Auswählen' },
            multiple: false,
            library: isImage ? { type: 'image' } : undefined,
        });

        frame.on('open', function () {
            detachMediaEditor();
            syncFrameSelection(frame, container);
        });

        frame.on('select', function () {
            var attachment = frame.state().get('selection').first();

            if (!attachment) {
                return;
            }

            applyMediaSelection(container, isImage, attachment.toJSON());
        });

        mediaFrames[fieldId] = frame;

        return frame;
    }

    function syncFrameSelection(frame, container) {
        var input = container.querySelector('input[type="hidden"]');
        var attachmentId = input ? parseInt(input.value, 10) : 0;
        var selection = frame.state().get('selection');

        selection.reset();

        if (attachmentId > 0) {
            var attachment = wp.media.attachment(attachmentId);
            attachment.fetch();
            selection.add(attachment);
        }
    }

    function openMediaFrame(button) {
        var container = button.closest('[data-bs-fabric-image-field], [data-bs-fabric-file-field]');

        if (!container) {
            return;
        }

        blurActiveEditor();

        var isImage = container.hasAttribute('data-bs-fabric-image-field');
        var frame = getOrCreateMediaFrame(container, isImage);

        if (!frame) {
            return;
        }

        frame.open();
    }

    function applyMediaSelection(container, isImage, attachment) {
        var input = container.querySelector('input[type="hidden"]');

        if (input) {
            input.value = String(attachment.id || 0);
        }

        if (isImage) {
            updateImagePreview(container, attachment);
            return;
        }

        var nameEl = container.querySelector('.bs-fabric-field-file-name');

        if (nameEl) {
            nameEl.textContent = attachment.filename || attachment.title || ('#' + attachment.id);
        }
    }

    function updateImagePreview(container, attachment) {
        var wrap = container.querySelector('.bs-fabric-field-image-preview-wrap');

        if (!wrap || !attachment.url) {
            return;
        }

        var thumbUrl = attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : attachment.url;

        wrap.innerHTML = '';

        var img = document.createElement('img');
        img.src = thumbUrl;
        img.alt = attachment.alt || '';
        img.className = 'bs-fabric-field-image-preview';
        wrap.appendChild(img);
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

        var wrap = container.querySelector('.bs-fabric-field-image-preview-wrap');

        if (wrap) {
            wrap.innerHTML = '';
        }

        var nameEl = container.querySelector('.bs-fabric-field-file-name');

        if (nameEl) {
            nameEl.textContent = '';
        }

        var fieldId = getFieldId(container);

        if (fieldId && mediaFrames[fieldId]) {
            mediaFrames[fieldId].state().get('selection').reset();
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

    function initReferenceFields() {
        document.querySelectorAll('.bs-fabric-reference-field').forEach(function (field) {
            var searchInput = field.querySelector('.bs-fabric-reference-search');
            var results = field.querySelector('.bs-fabric-reference-results');
            var selected = field.querySelector('.bs-fabric-reference-selected');

            if (!searchInput || !results || !selected) {
                return;
            }

            var debounceTimer = null;

            searchInput.addEventListener('input', function () {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(function () {
                    searchReferences(field, searchInput.value, results);
                }, 300);
            });

            results.addEventListener('click', function (event) {
                var target = event.target;

                if (!(target instanceof HTMLElement) || !target.classList.contains('bs-fabric-reference-result')) {
                    return;
                }

                event.preventDefault();
                addReferenceItem(field, selected, target);
                searchInput.value = '';
                results.innerHTML = '';
            });

            selected.addEventListener('click', function (event) {
                var target = event.target;

                if (!(target instanceof HTMLElement) || !target.classList.contains('bs-fabric-reference-remove')) {
                    return;
                }

                event.preventDefault();
                var item = target.closest('.bs-fabric-reference-item');

                if (item) {
                    item.remove();
                }

                ensureReferenceEmptyInput(field, selected);
            });
        });
    }

    function searchReferences(field, query, resultsContainer) {
        var postTypes = field.getAttribute('data-post-types') || 'post,page';

        postAjax('bs_fabric_search_reference_posts', {
            nonce: window.bsFabricAdmin.entryNonce,
            post_types: postTypes,
            search: query,
        })
            .then(function (payload) {
                resultsContainer.innerHTML = '';

                if (!payload || !payload.success || !payload.data || !payload.data.results) {
                    return;
                }

                payload.data.results.forEach(function (result) {
                    var item = document.createElement('li');
                    item.className = 'bs-fabric-reference-result';
                    item.setAttribute('data-post-id', String(result.id));
                    item.setAttribute('data-post-title', result.title);
                    item.textContent = result.title + ' (#' + result.id + ')';
                    resultsContainer.appendChild(item);
                });
            })
            .catch(function () {
                window.alert(window.bsFabricAdmin.strings.referenceSearchError || window.bsFabricAdmin.strings.relationSearchError);
            });
    }

    function addReferenceItem(field, selectedList, resultButton) {
        var postId = resultButton.getAttribute('data-post-id');
        var title = resultButton.getAttribute('data-post-title') || postId;
        var multiple = field.getAttribute('data-multiple') === '1';
        var fieldName = field.getAttribute('data-field-name') || '';
        var inputName = 'bs_fabric_fields[' + fieldName + ']' + (multiple ? '[]' : '');
        var removeLabel = (window.bsFabricAdmin.strings && window.bsFabricAdmin.strings.referenceRemove) || 'Entfernen';

        if (!postId) {
            return;
        }

        var existing = selectedList.querySelector('.bs-fabric-reference-item[data-post-id="' + postId + '"]');

        if (existing) {
            window.alert(window.bsFabricAdmin.strings.referenceExists || window.bsFabricAdmin.strings.relationExists);
            return;
        }

        if (!multiple) {
            selectedList.innerHTML = '';
        } else {
            var empty = selectedList.querySelector('.bs-fabric-reference-empty');

            if (empty) {
                empty.remove();
            }
        }

        var item = document.createElement('li');
        item.className = 'bs-fabric-reference-item';
        item.setAttribute('data-post-id', postId);
        item.innerHTML =
            '<span class="bs-fabric-reference-handle">⋮⋮</span>' +
            '<span class="bs-fabric-reference-label"></span>' +
            '<button type="button" class="button-link bs-fabric-reference-remove"></button>' +
            '<input type="hidden" name="' + inputName + '" value="' + postId + '" />';

        var label = item.querySelector('.bs-fabric-reference-label');
        var removeBtn = item.querySelector('.bs-fabric-reference-remove');

        if (label) {
            label.textContent = title;
        }

        if (removeBtn) {
            removeBtn.textContent = removeLabel;
        }

        selectedList.appendChild(item);
    }

    function ensureReferenceEmptyInput(field, selectedList) {
        var multiple = field.getAttribute('data-multiple') === '1';

        if (multiple) {
            return;
        }

        if (selectedList.querySelector('.bs-fabric-reference-item')) {
            return;
        }

        if (selectedList.querySelector('.bs-fabric-reference-empty')) {
            return;
        }

        var fieldName = field.getAttribute('data-field-name') || '';
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'bs_fabric_fields[' + fieldName + ']';
        input.value = '0';
        input.className = 'bs-fabric-reference-empty';
        selectedList.appendChild(input);
    }

    function initReferenceSortable() {
        if (typeof window.Sortable === 'undefined') {
            return;
        }

        document.querySelectorAll('.bs-fabric-reference-selected').forEach(function (list) {
            var field = list.closest('.bs-fabric-reference-field');

            if (!field || field.getAttribute('data-multiple') !== '1') {
                return;
            }

            window.Sortable.create(list, {
                handle: '.bs-fabric-reference-handle',
                animation: 150,
            });
        });
    }
}());
