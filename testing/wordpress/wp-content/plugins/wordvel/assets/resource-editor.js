(function bootWordVelResourceEditor() {
    var config = window.wordvelResourceEditor;

    if (!config || !config.schema || !config.apiBaseUrl) {
        return;
    }

    var root = document.getElementById('wordvel-resource-editor-root');

    if (!root) {
        return;
    }

    var state = {
        records: [],
        active: null,
        values: {},
        options: {},
        optionSearch: {},
        optionLabels: {},
        relationSearch: {},
        relationOpen: null,
        relationTimers: {},
        focusedRelation: null,
        modal: null,
        drag: null,
        dragOver: null,
        notice: null,
        isLoading: false,
        isSaving: false
    };

    document.addEventListener('mousedown', function (event) {
        if (!state.relationOpen || event.target.closest('.wordvel-combobox')) {
            return;
        }

        closeRelationshipPicker(null, false);
    });

    function request(path, options) {
        return window.fetch(config.apiBaseUrl + path, Object.assign({
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json'
            },
            credentials: 'omit'
        }, options || {})).then(function (response) {
            return response.json().then(function (json) {
                if (!response.ok || json.status === false) {
                    throw new Error((json.error && json.error.message) || json.message || 'WordVel request failed.');
                }

                return json.data;
            });
        });
    }

    function loadRecords() {
        state.isLoading = true;
        render();

        return request('/' + config.resourceKey + '?summary=1')
            .then(function (records) {
                state.records = Array.isArray(records) ? records : [];
                state.notice = null;

                if (!state.active && config.initialId) {
                    return loadRecord(config.initialId);
                }

                if (!state.active && state.records[0]) {
                    return loadRecord(state.records[0].id);
                }

                if (state.active && state.active.id) {
                    var updatedActive = state.records.find(function (record) {
                        return String(record.id) === String(state.active.id);
                    });
                    state.active = updatedActive || state.active;
                }

                state.notice = null;
            })
            .catch(function (error) {
                state.notice = { type: 'error', message: error.message };
            })
            .finally(function () {
                state.isLoading = false;
                render();
            });
    }

    function loadRecord(id) {
        state.isLoading = true;
        render();

        return request('/' + config.resourceKey + '/' + encodeURIComponent(id))
            .then(function (record) {
                state.active = record;
                state.values = Object.assign({}, record.values || {});
                state.notice = null;
            })
            .catch(function (error) {
                state.notice = { type: 'error', message: error.message };
            })
            .finally(function () {
                state.isLoading = false;
                render();
            });
    }

    function loadRelationshipOptions(field, ids) {
        var resource = field.options && field.options.resource;

        if (!resource) {
            return Promise.resolve();
        }

        ids = Array.isArray(ids) ? ids.filter(Boolean).map(String) : [];

        if (!ids.length && Array.isArray(state.options[field.key])) {
            return Promise.resolve();
        }

        var search = state.relationSearch[field.key] || '';
        var query = new URLSearchParams();

        if (ids.length) {
            ids.forEach(function (id) {
                query.append('ids[]', id);
            });
        } else if (search) {
            query.set('search', search);
        }

        return request('/' + resource + '/options' + (query.toString() ? '?' + query.toString() : ''))
            .then(function (options) {
                options = Array.isArray(options) ? options : [];
                state.optionLabels[resource] = state.optionLabels[resource] || {};
                options.forEach(function (option) {
                    state.optionLabels[resource][String(option.id)] = option.label;
                });

                if (!ids.length) {
                    state.options[field.key] = options;
                    state.optionSearch[field.key] = search;
                }

                return options;
            })
            .catch(function () {
                if (!ids.length) {
                    state.options[field.key] = [];
                    state.optionSearch[field.key] = search;
                }
            });
    }

    function loadAllRelationshipOptions() {
        return Promise.all((config.schema.fields || [])
            .filter(function (field) {
                return field.type === 'relationship';
            })
            .map(loadRelationshipOptions));
    }

    function openRelationshipPicker(field) {
        state.relationOpen = field.key;
        state.focusedRelation = field.key;
        setRelationshipMenuOpen(field.key, true);

        if (Array.isArray(state.options[field.key])) {
            return;
        }

        loadRelationshipOptions(field).then(render);
    }

    function searchRelationshipPicker(field, value) {
        state.relationSearch[field.key] = value;
        state.relationOpen = field.key;
        state.focusedRelation = field.key;
        setRelationshipMenuOpen(field.key, true);

        if (state.relationTimers[field.key]) {
            window.clearTimeout(state.relationTimers[field.key]);
        }

        state.relationTimers[field.key] = window.setTimeout(function () {
            state.options[field.key] = null;
            loadRelationshipOptions(field).then(render);
        }, 160);
    }

    function closeRelationshipPicker(fieldKey, shouldRender) {
        var key = fieldKey || state.relationOpen;

        state.relationOpen = null;
        state.focusedRelation = null;

        if (key) {
            setRelationshipMenuOpen(key, false);
        } else {
            root.querySelectorAll('.wordvel-combobox').forEach(function (combobox) {
                combobox.classList.remove('is-open');
                var menu = combobox.querySelector('.wordvel-combobox-menu');

                if (menu) {
                    menu.setAttribute('hidden', 'hidden');
                }

                var input = combobox.querySelector('[data-wordvel-relation-key]');

                if (input) {
                    input.setAttribute('aria-expanded', 'false');
                }
            });
        }

        if (shouldRender) {
            render();
        }
    }

    function setRelationshipMenuOpen(fieldKey, isOpen) {
        var input = Array.prototype.find.call(root.querySelectorAll('[data-wordvel-relation-key]'), function (candidate) {
            return candidate.getAttribute('data-wordvel-relation-key') === fieldKey;
        });

        if (!input) {
            return;
        }

        var combobox = input.closest('.wordvel-combobox');
        var menu = combobox ? combobox.querySelector('.wordvel-combobox-menu') : null;

        input.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (combobox) {
            combobox.classList.toggle('is-open', isOpen);
        }

        if (!menu) {
            return;
        }

        if (isOpen) {
            menu.removeAttribute('hidden');
        } else {
            menu.setAttribute('hidden', 'hidden');
        }
    }

    function visibleRelationshipOptions(fieldKey, options) {
        var search = normalizeSearch(state.relationSearch[fieldKey] || '');

        if (!search || normalizeSearch(state.optionSearch[fieldKey] || '') === search) {
            return options;
        }

        var tokens = search.split(' ').filter(Boolean);

        return options.filter(function (option) {
            var label = normalizeSearch(option.label || option.title || String(option.id || ''));

            return tokens.every(function (token) {
                return label.indexOf(token) !== -1;
            });
        });
    }

    function normalizeSearch(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, ' ')
            .trim();
    }

    function restoreRelationshipFocus() {
        if (!state.focusedRelation) {
            return;
        }

        window.setTimeout(function () {
            var inputs = root.querySelectorAll('[data-wordvel-relation-key]');
            var input = Array.prototype.find.call(inputs, function (candidate) {
                return candidate.getAttribute('data-wordvel-relation-key') === state.focusedRelation;
            });

            if (!input) {
                return;
            }

            input.focus({ preventScroll: true });

            if (typeof input.setSelectionRange === 'function') {
                var length = input.value.length;
                input.setSelectionRange(length, length);
            }
        }, 0);
    }

    function emptyValues() {
        return (config.schema.fields || []).reduce(function (values, field) {
            if (field.type === 'media') {
                values[field.key] = field.options && field.options.multiple ? [] : null;
            } else if (field.type === 'relationship') {
                values[field.key] = field.options && field.options.multiple ? [] : '';
            } else if (field.type === 'boolean') {
                values[field.key] = false;
            } else {
                values[field.key] = field.default == null ? '' : field.default;
            }

            return values;
        }, {});
    }

    function setValue(key, value) {
        state.values = Object.assign({}, state.values);
        state.values[key] = value;
    }

    function selectRecord(record) {
        loadRecord(record.id);
    }

    function newRecord() {
        state.active = null;
        state.values = emptyValues();
        state.notice = null;
        render();
    }

    function saveRecord() {
        state.isSaving = true;
        state.notice = null;
        syncRichTextEditors();
        render();

        var isUpdate = state.active && state.active.id;
        var path = '/' + config.resourceKey + (isUpdate ? '/' + encodeURIComponent(state.active.id) : '');

        request(path, {
            method: isUpdate ? 'PUT' : 'POST',
            body: JSON.stringify({ values: state.values })
        })
            .then(function (record) {
                state.notice = { type: 'success', message: config.schema.label + ' saved.' };
                state.active = record;
                state.values = Object.assign({}, record.values || {});

                return loadRecords();
            })
            .catch(function (error) {
                state.notice = { type: 'error', message: error.message };
            })
            .finally(function () {
                state.isSaving = false;
                render();
            });
    }

    function deleteRecord() {
        if (!state.active || !state.active.id || !window.confirm('Delete this ' + config.schema.label + '?')) {
            return;
        }

        state.isSaving = true;
        render();

        request('/' + config.resourceKey + '/' + encodeURIComponent(state.active.id), {
            method: 'DELETE'
        })
            .then(function () {
                state.active = null;
                state.values = emptyValues();
                state.notice = { type: 'success', message: config.schema.label + ' deleted.' };

                return loadRecords();
            })
            .catch(function (error) {
                state.notice = { type: 'error', message: error.message };
            })
            .finally(function () {
                state.isSaving = false;
                render();
            });
    }

    function render() {
        root.innerHTML = '';
        root.appendChild(element('div', {}, [
            element('div', { className: 'wordvel-resource-app' }, [
                renderList(),
                renderForm()
            ]),
            renderModal()
        ]));

        initializeRichTextEditors();
        restoreRelationshipFocus();
    }

    function renderList() {
        return element('aside', { className: 'wordvel-resource-list' }, [
            element('div', { className: 'wordvel-resource-list-header' }, [
                element('h2', {}, [config.schema.plural_label || config.schema.label]),
                button('New', 'secondary', newRecord)
            ]),
            state.isLoading
                ? element('p', { style: 'padding: 14px;' }, ['Loading...'])
                : element('ul', { className: 'wordvel-resource-records' }, state.records.map(function (record) {
                    return element('li', {}, [
                        element('button', {
                            type: 'button',
                            className: state.active && state.active.id === record.id ? 'is-active' : '',
                            onclick: function () {
                                selectRecord(record);
                            }
                        }, [record.title || '#' + record.id])
                    ]);
                }))
        ]);
    }

    function renderForm() {
        return element('section', { className: 'wordvel-resource-form' }, [
            element('div', { className: 'wordvel-resource-form-header' }, [
                element('h2', {}, [state.active ? state.active.title : 'New ' + config.schema.label])
            ]),
            state.notice ? element('div', {
                className: 'notice notice-' + (state.notice.type === 'error' ? 'error' : 'success') + ' wordvel-resource-notice'
            }, [element('p', {}, [state.notice.message])]) : null,
            element('div', { className: 'wordvel-resource-fields' }, (config.schema.fields || []).map(renderField)),
            element('div', { className: 'wordvel-resource-actions' }, [
                button(state.isSaving ? 'Saving...' : 'Save ' + config.schema.label, 'primary', saveRecord, state.isSaving),
                state.active ? button('Delete', 'secondary', deleteRecord, state.isSaving, true) : null
            ])
        ]);
    }

    function renderField(field) {
        var value = state.values[field.key];

        if (field.type === 'rich_text') {
            return fieldWrap(field, [
                element('textarea', {
                    id: editorId(field),
                    rows: '8',
                    value: value || '',
                    onchange: function (event) {
                        setValue(field.key, event.target.value);
                    }
                })
            ]);
        }

        if (field.type === 'number') {
            return fieldWrap(field, [
                element('input', {
                    type: 'number',
                    value: value == null ? '' : String(value),
                    disabled: field.options && field.options.readonly ? 'disabled' : null,
                    oninput: function (event) {
                        setValue(field.key, event.target.value);
                    }
                })
            ]);
        }

        if (field.type === 'boolean') {
            return fieldWrap(field, [
                element('label', {}, [
                    element('input', {
                        type: 'checkbox',
                        checked: value ? 'checked' : null,
                        onchange: function (event) {
                            setValue(field.key, event.target.checked);
                        }
                    }),
                    ' Enabled'
                ])
            ]);
        }

        if (field.type === 'select') {
            var options = (field.options && field.options.options) || {};

            return fieldWrap(field, [
                element('select', {
                    value: value || '',
                    onchange: function (event) {
                        setValue(field.key, event.target.value);
                    }
                }, [element('option', { value: '' }, ['Select one'])].concat(Object.keys(options).map(function (optionValue) {
                    return element('option', { value: optionValue }, [options[optionValue]]);
                })))
            ]);
        }

        if (field.type === 'media') {
            return renderMediaField(field, value);
        }

        if (field.type === 'relationship') {
            return renderRelationshipField(field, value);
        }

        return fieldWrap(field, [
            element('input', {
                type: field.type === 'url' ? 'url' : 'text',
                value: value || '',
                oninput: function (event) {
                    setValue(field.key, event.target.value);
                }
            })
        ]);
    }

    function renderMediaField(field, value) {
        var multiple = field.options && field.options.multiple;
        var items = multiple ? (Array.isArray(value) ? value : []) : (value ? [value] : []);

        return fieldWrap(field, [
            element('div', { className: 'wordvel-resource-media' }, [
                element('div', { className: 'wordvel-resource-media-preview' }, items.map(function (item) {
                    if (!item || !item.url) {
                        return null;
                    }

                    if (String(item.mime_type || '').indexOf('audio/') === 0) {
                        return element('audio', { src: item.url, controls: 'controls' }, []);
                    }

                    if (String(item.mime_type || '').indexOf('video/') === 0) {
                        return element('video', { src: item.url, controls: 'controls', playsinline: 'playsinline' }, []);
                    }

                    return element('img', { src: item.url, alt: item.alt || '' }, []);
                })),
                button(items.length ? 'Change media' : 'Select media', 'secondary', function () {
                    openMediaFrame(field, multiple);
                })
            ])
        ]);
    }

    function renderRelationshipField(field, value) {
        var multiple = field.options && field.options.multiple;
        var resource = field.options && field.options.resource;
        var schema = resourceSchema(resource);
        var options = visibleRelationshipOptions(field.key, state.options[field.key] || []);
        var labels = state.optionLabels[resource] || {};
        var sortable = multiple && field.options && field.options.sortable;
        var selectedIds = multiple ? (Array.isArray(value) ? value.map(String) : []) : (value ? [String(value)] : []);
        var selected = selectedIds.map(function (id) {
            return { id: id, label: labels[id] || '#' + id };
        });

        if (selectedIds.some(function (id) { return !labels[id]; })) {
            window.setTimeout(function () {
                loadRelationshipOptions(field, selectedIds).then(render);
            }, 0);
        }

        return fieldWrap(field, [
            element('div', { className: state.relationOpen === field.key ? 'wordvel-combobox is-open' : 'wordvel-combobox' }, [
                element('div', { className: 'wordvel-combobox-input' }, [
                    element('input', {
                        type: 'search',
                        role: 'combobox',
                        'aria-expanded': state.relationOpen === field.key ? 'true' : 'false',
                        'data-wordvel-relation-key': field.key,
                        autocomplete: 'off',
                        placeholder: multiple ? 'Search and attach' : 'Search or select',
                        value: state.relationOpen === field.key || multiple
                            ? (state.relationSearch[field.key] || '')
                            : (selectedIds[0] ? (labels[selectedIds[0]] || '') : ''),
                        onfocus: function () {
                            openRelationshipPicker(field);
                        },
                        oninput: function (event) {
                            searchRelationshipPicker(field, event.target.value);
                        },
                        onkeydown: function (event) {
                            if (event.key === 'Escape') {
                                event.preventDefault();
                                closeRelationshipPicker(field.key, false);
                            }
                        }
                    }),
                    element('button', {
                        type: 'button',
                        className: 'wordvel-combobox-toggle',
                        title: state.relationOpen === field.key ? 'Hide options' : 'Show options',
                        'aria-label': state.relationOpen === field.key ? 'Hide options' : 'Show options',
                        onclick: function () {
                            if (state.relationOpen === field.key) {
                                closeRelationshipPicker(field.key, false);
                                return;
                            }

                            openRelationshipPicker(field);
                        }
                    }, [element('span', { className: 'dashicons dashicons-arrow-down-alt2' }, [])])
                ]),
                element('div', { className: 'wordvel-combobox-menu', hidden: state.relationOpen === field.key ? null : 'hidden' }, [
                    options.length
                        ? element('div', { className: 'wordvel-combobox-options' }, options.map(function (option) {
                            var id = String(option.id);
                            var selectedAlready = selectedIds.indexOf(id) !== -1;

                            return element('button', {
                                type: 'button',
                                className: selectedAlready ? 'is-selected' : '',
                                onclick: function () {
                                    if (multiple) {
                                        if (!selectedAlready) {
                                            setValue(field.key, selectedIds.concat([id]));
                                        }
                                    } else {
                                        setValue(field.key, id);
                                    }

                                    state.optionLabels[resource] = state.optionLabels[resource] || {};
                                    state.optionLabels[resource][id] = option.label;
                                    state.relationSearch[field.key] = '';
                                    state.relationOpen = multiple ? field.key : null;
                                    state.focusedRelation = multiple ? field.key : null;
                                    state.options[field.key] = null;
                                    loadRelationshipOptions(field).then(render);
                                }
                            }, [option.label]);
                        }))
                        : element('p', { className: 'wordvel-combobox-empty' }, ['No results']),
                    element('button', {
                        type: 'button',
                        className: 'wordvel-combobox-create',
                        onclick: function () {
                            openRelationshipCreateModal(field, selectedIds);
                        }
                    }, ['+ Create new'])
                ])
            ]),
            element('div', { className: 'wordvel-relation-tools' }, [
                schema ? button('New ' + (schema.label || 'item'), 'small', function () {
                    openRelationshipCreateModal(field, selectedIds);
                }) : null,
                field.options && field.options.bulk_create_from_media && state.active
                    ? button('Upload ' + (schema ? String(schema.plural_label || 'items').toLowerCase() : 'items'), 'small', function () {
                        openRelationshipMediaCreator(field);
                    })
                    : null
            ]),
            multiple ? element('div', { className: sortable ? 'wordvel-resource-relation-selected is-sortable' : 'wordvel-resource-relation-selected' }, selected.map(function (option, index) {
                var isDragging = state.drag && state.drag.fieldKey === field.key && state.drag.index === index;
                var dropClass = state.dragOver && state.dragOver.fieldKey === field.key && state.dragOver.index === index
                    ? ' drop-' + state.dragOver.position
                    : '';

                return element('div', {
                    className: 'wordvel-resource-relation-row' + (isDragging ? ' is-dragging' : '') + dropClass,
                    'data-wordvel-relation-field': field.key,
                    onmousedown: sortable ? function (event) {
                        if (event.target.closest('.wordvel-resource-relation-actions')) {
                            return;
                        }

                        startRelationshipDrag(event, field.key, index, selectedIds);
                    } : null
                }, [
                    sortable ? element('span', { className: 'wordvel-drag-handle', title: 'Drag to reorder' }, ['⋮⋮']) : null,
                    element('span', { className: 'wordvel-resource-relation-title' }, [option.label]),
                    element('span', { className: 'wordvel-resource-relation-actions' }, [
                        iconButton('dashicons-trash', 'Remove', function () {
                            setValue(field.key, selectedIds.filter(function (_, selectedIndex) {
                                return selectedIndex !== index;
                            }));
                            render();
                        }, false, true)
                    ])
                ]);
            })) : null
        ]);
    }

    function openRelationshipCreateModal(field, selectedIds) {
        var resource = field.options && field.options.resource;
        var schema = resourceSchema(resource);

        if (!resource || !schema) {
            return;
        }

        state.modal = {
            type: 'relationship-create',
            field: field,
            resource: resource,
            schema: schema,
            selectedIds: selectedIds,
            values: emptyValuesForSchema(schema)
        };
        render();
    }

    function createRelationshipRecord(field, selectedIds, values) {
        var resource = field.options && field.options.resource;
        var multiple = field.options && field.options.multiple;

        if (!resource) {
            return;
        }

        state.isSaving = true;
        render();

        var path = field.options && field.options.bulk_create_from_media && state.active && state.active.id
            ? '/' + config.resourceKey + '/' + encodeURIComponent(state.active.id) + '/relationships/' + field.key
            : '/' + encodeURIComponent(resource);

        request(path, {
            method: 'POST',
            body: JSON.stringify({ values: values })
        }).then(function (record) {
            var id = record && record.id ? String(record.id) : '';

            if (record && record.values && Array.isArray(record.values[field.key])) {
                state.active = record;
                state.values = Object.assign({}, record.values || {});
                setValue(field.key, record.values[field.key].map(String));
                id = '';
            }

            if (id) {
                setValue(field.key, multiple ? selectedIds.concat([id]) : id);
                state.optionLabels[resource] = state.optionLabels[resource] || {};
                state.optionLabels[resource][id] = record.title || '#' + id;
            }

            state.modal = null;
            state.relationSearch[field.key] = '';
            state.focusedRelation = null;
            state.options[field.key] = null;
            return loadRelationshipOptions(field);
        }).catch(function (error) {
            state.notice = { type: 'error', message: error.message };
        }).finally(function () {
            state.isSaving = false;
            render();
        });
    }

    function resourceSchema(resource) {
        return (config.resources || []).find(function (candidate) {
            return candidate && candidate.key === resource;
        });
    }

    function emptyValuesForSchema(schema) {
        var fields = schema && Array.isArray(schema.fields) ? schema.fields : [];

        return fields.reduce(function (values, field) {
            if (field.type === 'media') {
                values[field.key] = field.options && field.options.multiple ? [] : null;
            } else if (field.type === 'relationship') {
                values[field.key] = field.options && field.options.multiple ? [] : '';
            } else if (field.type === 'boolean') {
                values[field.key] = false;
            } else {
                values[field.key] = field.default == null ? '' : field.default;
            }

            return values;
        }, {});
    }

    function openRelationshipMediaCreator(field) {
        var frame = window.wp.media({
            title: 'Create ' + (field.label || field.key),
            multiple: true,
            library: {
                type: field.options && field.options.bulk_media_type ? field.options.bulk_media_type : undefined
            }
        });

        frame.on('select', function () {
            var media = frame.state().get('selection').map(function (attachment) {
                return mediaValue(attachment.toJSON());
            });

            state.isSaving = true;
            render();

            request('/' + config.resourceKey + '/' + encodeURIComponent(state.active.id) + '/relationships/' + field.key + '/media', {
                method: 'POST',
                body: JSON.stringify({ media: media })
            }).then(function (record) {
                state.active = record;
                state.values = Object.assign({}, record.values || {});
                state.notice = { type: 'success', message: 'Created ' + media.length + ' item' + (media.length === 1 ? '' : 's') + '.' };
                state.options[field.key] = null;
                return loadRelationshipOptions(field);
            }).catch(function (error) {
                state.notice = { type: 'error', message: error.message };
            }).finally(function () {
                state.isSaving = false;
                render();
            });
        });

        frame.open();
    }

    function renderModal() {
        if (!state.modal) {
            return null;
        }

        var modal = state.modal;
        var title = 'Create ' + (modal.schema.label || modal.resource);

        return element('div', { className: 'wordvel-modal-backdrop' }, [
            element('div', { className: 'wordvel-modal', role: 'dialog', 'aria-modal': 'true' }, [
                element('div', { className: 'wordvel-modal-header' }, [
                    element('h2', {}, [title]),
                    iconButton('dashicons-no-alt', 'Close', function () {
                        state.modal = null;
                        state.focusedRelation = null;
                        render();
                    })
                ]),
                element('div', { className: 'wordvel-modal-body' }, (modal.schema.fields || []).map(function (field) {
                    if (isModalManagedField(field)) {
                        return null;
                    }

                    return renderModalField(field);
                })),
                element('div', { className: 'wordvel-modal-actions' }, [
                    button('Cancel', 'secondary', function () {
                        state.modal = null;
                        state.focusedRelation = null;
                        render();
                    }),
                    button('Create', 'primary', function () {
                        createRelationshipRecord(modal.field, modal.selectedIds, modal.values);
                    }, state.isSaving)
                ])
            ])
        ]);
    }

    function isModalManagedField(field) {
        var modal = state.modal;
        var sourceField = modal && modal.field;

        if (!sourceField || !sourceField.options) {
            return false;
        }

        var fieldOptions = field.options || {};
        var column = fieldOptions.column || field.key;

        if (fieldOptions.readonly) {
            return true;
        }

        if (field.type === 'relationship' && fieldOptions.resource === config.resourceKey) {
            return true;
        }

        if (Array.isArray(sourceField.options.bulk_copy_columns) && sourceField.options.bulk_copy_columns.indexOf(column) !== -1) {
            return true;
        }

        return column === sourceField.options.bulk_order_column || column === sourceField.options.order_column;
    }

    function renderModalField(field) {
        var modal = state.modal;
        var value = modal.values[field.key];
        var setModalValue = function (nextValue) {
            modal.values = Object.assign({}, modal.values);
            modal.values[field.key] = nextValue;
            render();
        };

        if (field.type === 'number') {
            return fieldWrap(field, [
                element('input', {
                    type: 'number',
                    value: value == null ? '' : String(value),
                    disabled: field.options && field.options.readonly ? 'disabled' : null,
                    oninput: function (event) {
                        modal.values[field.key] = event.target.value;
                    }
                })
            ]);
        }

        if (field.type === 'boolean') {
            return fieldWrap(field, [
                element('label', {}, [
                    element('input', {
                        type: 'checkbox',
                        checked: value ? 'checked' : null,
                        onchange: function (event) {
                            setModalValue(event.target.checked);
                        }
                    }),
                    ' Enabled'
                ])
            ]);
        }

        if (field.type === 'textarea' || field.type === 'rich_text') {
            return fieldWrap(field, [
                element('textarea', {
                    rows: '4',
                    value: value || '',
                    oninput: function (event) {
                        modal.values[field.key] = event.target.value;
                    }
                })
            ]);
        }

        if (field.type === 'select') {
            var options = (field.options && field.options.options) || {};

            return fieldWrap(field, [
                element('select', {
                    value: value || '',
                    onchange: function (event) {
                        setModalValue(event.target.value);
                    }
                }, [element('option', { value: '' }, ['Select one'])].concat(Object.keys(options).map(function (optionValue) {
                    return element('option', { value: optionValue }, [options[optionValue]]);
                })))
            ]);
        }

        if (field.type === 'media') {
            return renderModalMediaField(field, value, setModalValue);
        }

        if (field.type === 'relationship') {
            return renderModalRelationshipField(field, value, setModalValue);
        }

        return fieldWrap(field, [
            element('input', {
                type: field.type === 'url' ? 'url' : 'text',
                value: value || '',
                oninput: function (event) {
                    modal.values[field.key] = event.target.value;
                }
            })
        ]);
    }

    function renderModalMediaField(field, value, setModalValue) {
        var multiple = field.options && field.options.multiple;
        var items = multiple ? (Array.isArray(value) ? value : []) : (value ? [value] : []);

        return fieldWrap(field, [
            element('div', { className: 'wordvel-resource-media' }, [
                element('div', { className: 'wordvel-resource-media-preview' }, items.map(function (item) {
                    if (!item || !item.url) {
                        return null;
                    }

                    if (String(item.mime_type || '').indexOf('audio/') === 0) {
                        return element('audio', { src: item.url, controls: 'controls' }, []);
                    }

                    if (String(item.mime_type || '').indexOf('video/') === 0) {
                        return element('video', { src: item.url, controls: 'controls', playsinline: 'playsinline' }, []);
                    }

                    return element('img', { src: item.url, alt: item.alt || '' }, []);
                })),
                button(items.length ? 'Change media' : 'Select media', 'secondary', function () {
                    var frame = window.wp.media({
                        title: field.label,
                        multiple: multiple,
                        library: {
                            type: field.options && field.options.media_type ? field.options.media_type : undefined
                        }
                    });

                    frame.on('select', function () {
                        var values = frame.state().get('selection').map(function (attachment) {
                            return mediaValue(attachment.toJSON());
                        });

                        setModalValue(multiple ? values : (values[0] || null));
                    });

                    frame.open();
                })
            ])
        ]);
    }

    function renderModalRelationshipField(field, value, setModalValue) {
        var modalKey = 'modal_' + field.key;
        var modalField = Object.assign({}, field, { key: modalKey });
        var multiple = field.options && field.options.multiple;
        var resource = field.options && field.options.resource;
        var options = visibleRelationshipOptions(modalKey, state.options[modalKey] || []);
        var labels = state.optionLabels[resource] || {};
        var selectedIds = multiple ? (Array.isArray(value) ? value.map(String) : []) : (value ? [String(value)] : []);

        if (selectedIds.some(function (id) { return !labels[id]; })) {
            window.setTimeout(function () {
                loadRelationshipOptions(modalField, selectedIds).then(render);
            }, 0);
        }

        return fieldWrap(field, [
            element('div', { className: state.relationOpen === modalKey ? 'wordvel-combobox is-open' : 'wordvel-combobox' }, [
                element('div', { className: 'wordvel-combobox-input' }, [
                    element('input', {
                        type: 'search',
                        role: 'combobox',
                        'aria-expanded': state.relationOpen === modalKey ? 'true' : 'false',
                        'data-wordvel-relation-key': modalKey,
                        autocomplete: 'off',
                        placeholder: multiple ? 'Search and attach' : 'Search or select',
                        value: state.relationOpen === modalKey || multiple
                            ? (state.relationSearch[modalKey] || '')
                            : (selectedIds[0] ? (labels[selectedIds[0]] || '') : ''),
                        onfocus: function () {
                            openRelationshipPicker(modalField);
                        },
                        oninput: function (event) {
                            searchRelationshipPicker(modalField, event.target.value);
                        },
                        onkeydown: function (event) {
                            if (event.key === 'Escape') {
                                event.preventDefault();
                                closeRelationshipPicker(modalKey, false);
                            }
                        }
                    }),
                    element('button', {
                        type: 'button',
                        className: 'wordvel-combobox-toggle',
                        title: state.relationOpen === modalKey ? 'Hide options' : 'Show options',
                        'aria-label': state.relationOpen === modalKey ? 'Hide options' : 'Show options',
                        onclick: function () {
                            if (state.relationOpen === modalKey) {
                                closeRelationshipPicker(modalKey, false);
                                return;
                            }

                            openRelationshipPicker(modalField);
                        }
                    }, [element('span', { className: 'dashicons dashicons-arrow-down-alt2' }, [])])
                ]),
                element('div', { className: 'wordvel-combobox-menu', hidden: state.relationOpen === modalKey ? null : 'hidden' }, [
                    options.length
                        ? element('div', { className: 'wordvel-combobox-options' }, options.map(function (option) {
                            var id = String(option.id);
                            var selectedAlready = selectedIds.indexOf(id) !== -1;

                            return element('button', {
                                type: 'button',
                                className: selectedAlready ? 'is-selected' : '',
                                onclick: function () {
                                    state.optionLabels[resource] = state.optionLabels[resource] || {};
                                    state.optionLabels[resource][id] = option.label;

                                    if (multiple) {
                                        if (!selectedAlready) {
                                            setModalValue(selectedIds.concat([id]));
                                        }
                                    } else {
                                        setModalValue(id);
                                    }

                                    state.relationSearch[modalKey] = '';
                                    state.relationOpen = multiple ? modalKey : null;
                                    state.focusedRelation = multiple ? modalKey : null;
                                    state.options[modalKey] = null;
                                    loadRelationshipOptions(modalField).then(render);
                                }
                            }, [option.label]);
                        }))
                        : element('p', { className: 'wordvel-combobox-empty' }, ['No results'])
                ])
            ]),
            multiple ? element('div', { className: 'wordvel-resource-relation-selected' }, selectedIds.map(function (id, index) {
                return element('div', { className: 'wordvel-resource-relation-row' }, [
                    element('span', { className: 'wordvel-resource-relation-title' }, [labels[id] || '#' + id]),
                    iconButton('dashicons-trash', 'Remove', function () {
                        setModalValue(selectedIds.filter(function (_, selectedIndex) {
                            return selectedIndex !== index;
                        }));
                    }, false, true)
                ]);
            })) : null
        ]);
    }

    function startRelationshipDrag(event, fieldKey, index, ids) {
        if (event.button != null && event.button !== 0) {
            return;
        }

        event.preventDefault();
        state.drag = {
            fieldKey: fieldKey,
            index: index,
            ids: ids.slice()
        };
        state.dragOver = null;

        var row = event.currentTarget.closest('.wordvel-resource-relation-row');

        if (row) {
            row.classList.add('is-dragging');
        }

        document.addEventListener('mousemove', handleRelationshipDragMove);
        document.addEventListener('mouseup', finishRelationshipDrag);
    }

    function handleRelationshipDragMove(event) {
        if (!state.drag) {
            return;
        }

        updateDragPreviewFromPoint(event.clientY, state.drag.fieldKey);
    }

    function finishRelationshipDrag() {
        document.removeEventListener('mousemove', handleRelationshipDragMove);
        document.removeEventListener('mouseup', finishRelationshipDrag);

        var drag = state.drag;
        var dragOver = state.dragOver;

        if (drag && dragOver) {
            moveSelectedTo(drag.fieldKey, drag.ids || [], drag.index, dragOver.index, dragOver.position);
            return;
        }

        state.drag = null;
        state.dragOver = null;
        clearDragPreviewClasses(true);
    }

    function updateDragPreviewFromPoint(clientY, fieldKey) {
        var rows = Array.prototype.slice.call(root.querySelectorAll('[data-wordvel-relation-field="' + fieldKey + '"]'));

        if (!rows.length) {
            return;
        }

        var target = rows[0];
        var index = 0;
        var position = 'before';
        var firstRect = rows[0].getBoundingClientRect();
        var lastRect = rows[rows.length - 1].getBoundingClientRect();

        if (clientY >= lastRect.bottom) {
            target = rows[rows.length - 1];
            index = rows.length - 1;
            position = 'after';
        } else if (clientY > firstRect.top) {
            rows.some(function (row, rowIndex) {
                var rect = row.getBoundingClientRect();

                if (clientY >= rect.top && clientY <= rect.bottom) {
                    target = row;
                    index = rowIndex;
                    position = clientY - rect.top > rect.height / 2 ? 'after' : 'before';
                    return true;
                }

                return false;
            });
        }

        if (
            state.dragOver
            && state.dragOver.fieldKey === fieldKey
            && state.dragOver.index === index
            && state.dragOver.position === position
        ) {
            return;
        }

        state.dragOver = { fieldKey: fieldKey, index: index, position: position };
        clearDragPreviewClasses(false);
        target.classList.add('drop-' + position);
    }

    function moveSelectedTo(key, ids, fromIndex, toIndex, position) {
        if (fromIndex === null || fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= ids.length || toIndex >= ids.length) {
            state.drag = null;
            state.dragOver = null;
            render();
            return;
        }

        var next = ids.slice();
        var item = next.splice(fromIndex, 1)[0];
        var insertIndex = position === 'after' ? toIndex + 1 : toIndex;

        if (fromIndex < insertIndex) {
            insertIndex -= 1;
        }

        next.splice(Math.max(0, Math.min(insertIndex, next.length)), 0, item);
        setValue(key, next);
        state.drag = null;
        state.dragOver = null;
        render();
    }

    function clearDragPreviewClasses(includeDragging) {
        root.querySelectorAll('.wordvel-resource-relation-row').forEach(function (row) {
            row.classList.remove('drop-before', 'drop-after');

            if (includeDragging) {
                row.classList.remove('is-dragging');
            }
        });
    }

    function openMediaFrame(field, multiple) {
        var frame = window.wp.media({
            title: field.label,
            multiple: multiple,
            library: {
                type: field.options && field.options.media_type ? field.options.media_type : undefined
            }
        });

        frame.on('select', function () {
            var selection = frame.state().get('selection');
            var values = selection.map(function (attachment) {
                return mediaValue(attachment.toJSON());
            });

            setValue(field.key, multiple ? values : (values[0] || null));
            render();
        });

        frame.open();
    }

    function mediaValue(media) {
        var duration = media.fileLength || media.length || (media.meta && media.meta.length);

        return {
            id: media.id || '',
            url: media.url || media.source_url || '',
            mime_type: media.mime || media.mime_type || media.type || '',
            title: media.title || '',
            alt: media.alt || media.alt_text || '',
            width: media.width || '',
            height: media.height || '',
            duration_seconds: duration ? parseInt(duration, 10) : null
        };
    }

    function fieldWrap(field, children) {
        return element('div', { className: 'wordvel-resource-field' }, [
            element('div', { className: 'wordvel-resource-label' }, [field.label || field.key])
        ].concat(children));
    }

    function button(label, variant, onClick, disabled, destructive) {
        var classes = ['button'];

        if (variant === 'primary') {
            classes.push('button-primary');
        }

        if (variant === 'small') {
            classes.push('button-small');
        }

        if (destructive) {
            classes.push('is-destructive');
        }

        return element('button', {
            type: 'button',
            className: classes.join(' '),
            disabled: disabled ? 'disabled' : null,
            onclick: disabled ? null : onClick
        }, [label]);
    }

    function iconButton(icon, label, onClick, disabled, destructive) {
        var classes = ['button', 'button-small', 'wordvel-icon-button'];

        if (destructive) {
            classes.push('is-destructive');
        }

        return element('button', {
            type: 'button',
            className: classes.join(' '),
            title: label,
            'aria-label': label,
            disabled: disabled ? 'disabled' : null,
            onclick: disabled ? null : onClick
        }, [element('span', { className: 'dashicons ' + icon }, [])]);
    }

    function element(tag, attributes, children) {
        var node = document.createElement(tag);
        var pendingValue;

        Object.keys(attributes || {}).forEach(function (key) {
            var value = attributes[key];

            if (value == null) {
                return;
            }

            if (key === 'className') {
                node.className = value;
                return;
            }

            if (key === 'style') {
                node.setAttribute('style', value);
                return;
            }

            if (key.indexOf('on') === 0 && typeof value === 'function') {
                node.addEventListener(key.slice(2), value);
                return;
            }

            if (key === 'checked' || key === 'disabled') {
                node[key] = true;
                return;
            }

            if (key === 'value') {
                pendingValue = value;
                return;
            }

            node.setAttribute(key, value);
        });

        (children || []).forEach(function (child) {
            if (child == null) {
                return;
            }

            node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
        });

        if (pendingValue !== undefined) {
            node.value = pendingValue;
        }

        return node;
    }

    function editorId(field) {
        var activeId = state.active && state.active.id ? state.active.id : 'new';

        return 'wordvel-resource-editor-' + config.resourceKey + '-' + activeId + '-' + field.key;
    }

    function initializeRichTextEditors() {
        if (!window.wp || !window.wp.editor || typeof window.wp.editor.initialize !== 'function') {
            return;
        }

        (config.schema.fields || []).forEach(function (field) {
            if (field.type !== 'rich_text') {
                return;
            }

            var id = editorId(field);

            if (!document.getElementById(id) || window.tinymce && window.tinymce.get(id)) {
                return;
            }

            window.setTimeout(function () {
                try {
                    window.wp.editor.initialize(id, {
                        tinymce: {
                            height: 180,
                            menubar: false,
                            toolbar1: 'bold italic link bullist numlist',
                            plugins: 'lists link'
                        },
                        quicktags: true
                    });
                } catch (error) {
                    // Textarea fallback stays usable.
                }
            }, 0);
        });
    }

    function syncRichTextEditors() {
        if (!window.tinymce) {
            return;
        }

        (config.schema.fields || []).forEach(function (field) {
            if (field.type !== 'rich_text') {
                return;
            }

            var editor = window.tinymce.get(editorId(field));

            if (editor) {
                setValue(field.key, editor.getContent());
            }
        });
    }

    loadAllRelationshipOptions().then(loadRecords);
})();
