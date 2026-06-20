(function bootWordVelResourceEditor() {
    var config = window.wordvelResourceEditor;

    if (!config || !config.schema || !config.apiBaseUrl) {
        return;
    }

    var root = document.getElementById('wordvel-resource-editor-root');

    if (!root) {
        return;
    }

    var languageStorageKey = 'wordvel-resource-language';
    var languages = normalizeLanguages(config.languages);
    var pendingRichTextEditors = {};

    function isTimecodeField(field) {
        return !!(field && field.type === 'number' && field.options && field.options.format === 'timecode');
    }

    function padMillisecondsSegment(segment) {
        var digits = String(segment || '').replace(/\D/g, '');

        if (!digits) {
            return 0;
        }

        if (digits.length > 3) {
            digits = digits.slice(0, 3);
        }

        return Math.min(999, parseInt(digits.padEnd(3, '0'), 10) || 0);
    }

    function looksLikeSecondsMillisecondsPair(seconds, milliseconds) {
        if (!/^\d+$/.test(milliseconds)) {
            return false;
        }

        if (milliseconds.length === 3) {
            return true;
        }

        return seconds >= 10 && milliseconds.length >= 1 && milliseconds.length <= 2;
    }

    function parseTimecodeInput(raw) {
        var value = String(raw == null ? '' : raw).trim().replace(',', '.');

        if (!value) {
            return null;
        }

        if (/^\d+(\.\d+)?$/.test(value)) {
            return parseFloat(value);
        }

        var legacyMatch = value.match(/^(\d+):(\d{1,2})\.(\d{1,3})$/);

        if (legacyMatch) {
            return (parseInt(legacyMatch[1], 10) * 60)
                + parseInt(legacyMatch[2], 10)
                + (padMillisecondsSegment(legacyMatch[3]) / 1000);
        }

        var parts = value.split(':').map(function (part) {
            return part.trim();
        }).filter(function (part) {
            return part !== '';
        });

        if (parts.length === 1) {
            return /^\d+(\.\d+)?$/.test(parts[0]) ? parseFloat(parts[0]) : null;
        }

        if (parts.length === 2) {
            if (!/^\d+$/.test(parts[0]) || !/^(\d+)(\.\d+)?$/.test(parts[1])) {
                return null;
            }

            var first = parseInt(parts[0], 10);

            if (looksLikeSecondsMillisecondsPair(first, parts[1])) {
                return first + (padMillisecondsSegment(parts[1]) / 1000);
            }

            return (first * 60) + parseFloat(parts[1]);
        }

        if (parts.length === 3) {
            if (!/^\d+$/.test(parts[0]) || !/^\d+$/.test(parts[1]) || !/^\d+$/.test(parts[2])) {
                return null;
            }

            var secondsPart = parseInt(parts[1], 10);

            if (secondsPart < 0 || secondsPart > 59) {
                return null;
            }

            return (parseInt(parts[0], 10) * 60)
                + secondsPart
                + (padMillisecondsSegment(parts[2]) / 1000);
        }

        return null;
    }

    function formatTimecodeInput(seconds) {
        var totalMs = Math.max(0, Math.round(Number(seconds) * 1000));
        var milliseconds = totalMs % 1000;
        var totalSeconds = Math.floor(totalMs / 1000);
        var secondsPart = totalSeconds % 60;
        var minutes = Math.floor(totalSeconds / 60);

        return minutes + ':' + String(secondsPart).padStart(2, '0') + ':' + String(milliseconds).padStart(3, '0');
    }

    function displayTimecodeValue(value) {
        if (value == null || value === '') {
            return '';
        }

        if (typeof value === 'number' && Number.isFinite(value)) {
            return formatTimecodeInput(value);
        }

        var asString = String(value).trim();

        if (/^\d+(\.\d+)?$/.test(asString)) {
            return formatTimecodeInput(parseFloat(asString));
        }

        return asString;
    }

    function renderTimecodeInput(field, value, onChange, extras) {
        return element('input', Object.assign({
            type: 'text',
            value: displayTimecodeValue(value),
            placeholder: '0:00:000',
            title: 'Time: 23, 1:23, 23:700, or 0:23:700',
            oninput: function (event) {
                onChange(event.target.value);
            },
            onblur: function (event) {
                var raw = event.target.value.trim();

                if (!raw) {
                    onChange('');
                    return;
                }

                var seconds = parseTimecodeInput(raw);

                if (seconds == null) {
                    return;
                }

                var formatted = formatTimecodeInput(seconds);

                event.target.value = formatted;
                onChange(formatted);
            }
        }, extras || {}));
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
        language: readStoredLanguage(),
        isLoading: false,
        isSaving: false,
        isCreating: new URLSearchParams(window.location.search).get('action') === 'new'
    };

    var loadRecordRequestId = 0;

    document.addEventListener('mousedown', function (event) {
        if (!state.relationOpen || event.target.closest('.wordvel-combobox')) {
            return;
        }

        closeRelationshipPicker(null, false);
    });

    function request(path, options) {
        return window.fetch(config.apiBaseUrl + pathWithLanguage(path), Object.assign({
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Wordvel-Language': state.language
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

    function pathWithLanguage(path) {
        var separator = path.indexOf('?') === -1 ? '?' : '&';

        return path + separator + 'lang=' + encodeURIComponent(state.language);
    }

    function normalizeLanguages(rawLanguages) {
        var fallback = [
            { code: 'en', label: 'English' },
            { code: 'es', label: 'Spanish' }
        ];

        if (!Array.isArray(rawLanguages) || !rawLanguages.length) {
            return fallback;
        }

        return rawLanguages
            .map(function (language) {
                if (typeof language === 'string') {
                    return { code: language, label: language.toUpperCase() };
                }

                return {
                    code: String(language.code || language.value || '').slice(0, 8),
                    label: String(language.label || language.name || language.code || language.value || '')
                };
            })
            .filter(function (language) {
                return language.code && language.label;
            });
    }

    function readStoredLanguage() {
        var stored = window.localStorage.getItem(languageStorageKey);
        var defaultLanguage = config.defaultLanguage || (languages[0] && languages[0].code) || 'en';
        var urlLanguage = config.initialLanguage || new URLSearchParams(window.location.search).get('lang');

        if (urlLanguage && languages.some(function (language) { return language.code === urlLanguage; })) {
            return urlLanguage;
        }

        return languages.some(function (language) { return language.code === stored; }) ? stored : defaultLanguage;
    }

    function readRecordIdFromUrl() {
        var recordId = new URLSearchParams(window.location.search).get('record');

        return recordId ? String(recordId) : null;
    }

    function syncEditorUrl(recordId, options) {
        if (!config.adminUrl || !config.adminPage) {
            return;
        }

        var params = new URLSearchParams();
        params.set('page', config.adminPage);

        if (recordId) {
            params.set('record', String(recordId));
        } else if (options && options.action === 'new') {
            params.set('action', 'new');
        }

        if (state.language) {
            params.set('lang', state.language);
        }

        var nextUrl = config.adminUrl + (config.adminUrl.indexOf('?') === -1 ? '?' : '&') + params.toString();
        var currentUrl = window.location.href.split('#')[0];

        if (currentUrl === nextUrl) {
            return;
        }

        var historyState = {
            wordvelRecordId: recordId || null,
            wordvelLanguage: state.language
        };
        var usePush = !!(options && options.push);

        if (usePush && window.history.pushState) {
            window.history.pushState(historyState, '', nextUrl);
            return;
        }

        window.history.replaceState(historyState, '', nextUrl);
    }

    function restoreEditorFromUrl() {
        var recordId = readRecordIdFromUrl();
        var isCreating = new URLSearchParams(window.location.search).get('action') === 'new';

        if (recordId) {
            if (!state.active || String(state.active.id) !== String(recordId)) {
                loadRecord(recordId);
            }

            return;
        }

        if (isCreating) {
            state.active = null;
            state.isCreating = true;
            state.values = emptyValues();
            state.notice = null;
            render();
            return;
        }

        if (state.active || state.isCreating) {
            state.active = null;
            state.isCreating = false;
            state.values = emptyValues();
            state.notice = null;
            syncEditorUrl(null);
            render();
        }
    }

    function hasLocalizedFields(schema) {
        return !!(schema && Array.isArray(schema.fields) && schema.fields.some(function (field) {
            return isLocalizedField(field) || hasLocalizedFields(field);
        }));
    }

    function hasAnyLocalizedFields() {
        return hasLocalizedFields(config.schema) || (config.resources || []).some(hasLocalizedFields);
    }

    function isLocalizedField(field) {
        return !!(field && field.options && field.options.localized === true);
    }

    function localizedObject(value) {
        var object = {};

        if (value && typeof value === 'object' && !Array.isArray(value)) {
            object = Object.assign({}, value);
        } else if (value != null && value !== '') {
            object.en = String(value);
            object.es = String(value);
        }

        languages.forEach(function (language) {
            if (object[language.code] == null) {
                object[language.code] = object.en || object.es || '';
            }
        });

        return object;
    }

    function fieldValue(field, values) {
        var value = (values || state.values)[field.key];

        if (!isLocalizedField(field)) {
            return value;
        }

        value = localizedObject(value);

        return value[state.language] || value.en || value.es || '';
    }

    function setFieldValue(field, value) {
        if (!isLocalizedField(field)) {
            setValue(field.key, value);
            return;
        }

        var localized = localizedObject(state.values[field.key]);
        localized[state.language] = value;
        setValue(field.key, localized);
    }

    function setModalFieldValue(field, value) {
        var modal = state.modal;

        if (!modal) {
            return;
        }

        modal.values = Object.assign({}, modal.values);

        if (!isLocalizedField(field)) {
            modal.values[field.key] = value;
            return;
        }

        var localized = localizedObject(modal.values[field.key]);
        localized[state.language] = value;
        modal.values[field.key] = localized;
    }

    function changeLanguage(nextLanguage) {
        if (!nextLanguage || nextLanguage === state.language) {
            return;
        }

        syncRichTextEditors();
        state.language = nextLanguage;
        window.localStorage.setItem(languageStorageKey, state.language);
        state.options = {};
        state.optionLabels = {};
        state.relationSearch = {};
        syncEditorUrl(state.active && state.active.id ? state.active.id : null);
        render();
        loadRecords({ silent: true });
    }

    function loadRecords(options) {
        var silent = !!(options && options.silent);
        var preserveNotice = !!(options && options.preserveNotice);

        if (!silent) {
            state.isLoading = true;
            render();
        }

        return request('/' + config.resourceKey)
            .then(function (records) {
                state.records = Array.isArray(records) ? records : [];
                if (!preserveNotice) {
                    state.notice = null;
                }

                var recordId = readRecordIdFromUrl() || config.initialId;

                if (!state.active && recordId) {
                    return loadRecord(recordId);
                }

                state.isCreating = new URLSearchParams(window.location.search).get('action') === 'new';

                if (state.active && state.active.id) {
                    var updatedActive = state.records.find(function (record) {
                        return String(record.id) === String(state.active.id);
                    });
                    state.active = updatedActive || state.active;
                }

                if (!preserveNotice) {
                    state.notice = null;
                }
            })
            .catch(function (error) {
                state.notice = { type: 'error', message: error.message };
            })
            .finally(function () {
                state.isLoading = false;
                if (!silent) {
                    render();
                }
            });
    }

    function loadRecord(id) {
        var requestId = ++loadRecordRequestId;
        var canRefreshForm = !!root.querySelector('.wordvel-resource-form');

        return request('/' + config.resourceKey + '/' + encodeURIComponent(id))
            .then(function (record) {
                if (requestId !== loadRecordRequestId) {
                    return;
                }

                state.active = record;
                state.isCreating = false;
                state.values = Object.assign({}, record.values || {});
                state.notice = null;
                syncEditorUrl(record.id);

                if (canRefreshForm) {
                    preserveScroll(refreshRecordForm);
                } else {
                    render();
                }
            })
            .catch(function (error) {
                if (requestId !== loadRecordRequestId) {
                    return;
                }

                state.notice = { type: 'error', message: error.message };
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

        loadRelationshipOptions(field).then(function () {
            refreshField(field);
        });
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
            loadRelationshipOptions(field).then(function () {
                refreshField(field);
                restoreRelationshipFocus();
            });
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
        return emptyValuesForFields(config.schema.fields || []);
    }

    function emptyValuesForFields(fields) {
        return (fields || []).reduce(function (values, field) {
            if (field.type === 'media') {
                values[field.key] = field.options && field.options.multiple ? [] : null;
            } else if (field.type === 'relationship') {
                values[field.key] = field.options && field.options.multiple ? [] : '';
            } else if (field.type === 'repeater') {
                values[field.key] = [];
            } else if (field.type === 'group') {
                values[field.key] = emptyValuesForFields(field.fields || []);
            } else if (field.type === 'boolean') {
                values[field.key] = false;
            } else if (isLocalizedField(field)) {
                values[field.key] = localizedObject(field.default);
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
        syncEditorUrl(record.id, { push: true });
        loadRecord(record.id);
    }

    function newRecord() {
        state.active = null;
        state.isCreating = true;
        state.values = emptyValues();
        state.notice = null;
        syncEditorUrl(null, { push: true, action: 'new' });
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
                state.isCreating = false;
                state.values = Object.assign({}, record.values || {});
                syncEditorUrl(record.id);

                return loadRecords({ silent: true, preserveNotice: true });
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
                state.isCreating = false;
                state.values = emptyValues();
                state.notice = { type: 'success', message: config.schema.label + ' deleted.' };
                syncEditorUrl(null);

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
        destroyRichTextEditors();
        root.innerHTML = '';
        root.appendChild(element('div', { className: 'wordvel-resource-app' }, [
            state.active || state.isCreating ? renderForm() : renderList(),
            renderModal(),
        ]));

        initializeRichTextEditors();
        restoreRelationshipFocus();
    }

    function renderList() {
        var columns = resourceListColumns();

        return element('section', { className: 'wordvel-resource-list wordvel-resource-list-screen' }, [
            element('h1', { className: 'wp-heading-inline' }, [config.schema.plural_label || config.schema.label]),
            element('button', { type: 'button', className: 'page-title-action', onclick: newRecord }, ['Add New']),
            renderLanguageSwitcher(),
            element('hr', { className: 'wp-header-end' }, []),
            state.notice ? element('div', {
                className: 'notice notice-' + (state.notice.type === 'error' ? 'error' : 'success') + ' wordvel-resource-notice'
            }, [element('p', {}, [state.notice.message])]) : null,
            element('ul', { className: 'subsubsub' }, [
                element('li', {}, [
                    element('a', { href: '#', className: 'current' }, [
                        'All ',
                        element('span', { className: 'count' }, ['(' + state.records.length + ')'])
                    ])
                ])
            ]),
            element('div', { className: 'tablenav top wordvel-resource-list-nav' }, [
                element('div', { className: 'alignleft actions bulkactions' }, [
                    element('select', {}, [
                        element('option', { value: '' }, ['Bulk actions']),
                        element('option', { value: 'delete' }, ['Delete'])
                    ]),
                    element('button', { type: 'button', className: 'button action', disabled: 'disabled' }, ['Apply'])
                ]),
                element('div', { className: 'alignleft actions' }, resourceFilterControls()),
                element('p', { className: 'search-box' }, [
                    element('label', { className: 'screen-reader-text', for: 'wordvel-resource-search' }, ['Search']),
                    element('input', { type: 'search', id: 'wordvel-resource-search' }),
                    element('button', { type: 'button', className: 'button' }, ['Search ' + (config.schema.plural_label || 'items')])
                ]),
                element('div', { className: 'tablenav-pages one-page' }, [
                    element('span', { className: 'displaying-num' }, [
                        state.isLoading ? 'Loading...' : state.records.length + ' item' + (state.records.length === 1 ? '' : 's')
                    ])
                ])
            ]),
            state.isLoading
                ? element('p', { style: 'padding: 14px;' }, ['Loading...'])
                : element('table', { className: 'wp-list-table widefat fixed striped table-view-list wordvel-resource-records' }, [
                    element('thead', {}, [
                        element('tr', {}, columns.map(renderListHeader))
                    ]),
                    element('tbody', {}, state.records.length ? state.records.map(function (record) {
                        var active = state.active && String(state.active.id) === String(record.id);

                        return element('tr', { className: active ? 'is-active' : '' }, columns.map(function (column) {
                                return renderListCell(column, record);
                        }));
                    }) : [
                        element('tr', {}, [
                            element('td', { colspan: String(columns.length) }, ['No records found.'])
                        ])
                    ])
                ])
        ]);
    }

    function resourceListColumns() {
        var fields = config.schema.fields || [];
        var has = function (key) {
            return fields.some(function (field) {
                return field.key === key;
            });
        };

        if (has('sku') && has('price_cents')) {
            return [
                { key: 'cb', label: '', className: 'check-column' },
                { key: 'thumb', label: '', className: 'column-thumb' },
                { key: 'title', label: 'Name', className: 'column-primary column-name' },
                { key: 'sku', label: 'SKU' },
                { key: 'stock_status', label: 'Stock' },
                { key: 'price_cents', label: 'Price' },
                { key: 'categories', label: 'Categories' },
                { key: 'is_featured', label: 'Featured' },
                { key: 'status', label: 'Status' }
            ];
        }

        return [
            { key: 'cb', label: '', className: 'check-column' },
            { key: 'title', label: 'Title', className: 'column-primary column-name' },
            { key: 'id', label: 'ID' }
        ];
    }

    function renderListHeader(column) {
        if (column.key === 'cb') {
            return element('td', { className: 'manage-column column-cb check-column' }, [
                element('input', { type: 'checkbox' }, [])
            ]);
        }

        return element('th', { scope: 'col', className: 'manage-column ' + (column.className || '') }, [column.label]);
    }

    function renderListCell(column, record) {
        if (column.key === 'cb') {
            return element('th', { scope: 'row', className: 'check-column' }, [
                element('input', { type: 'checkbox', value: record.id }, [])
            ]);
        }

        if (column.key === 'thumb') {
            var image = recordValue(record, 'featured_image') || recordValue(record, 'primary_image');
            var gallery = recordValue(record, 'gallery');

            if (!image && Array.isArray(gallery)) {
                image = gallery[0];
            }

            return element('td', { className: 'thumb column-thumb' }, [
                image && image.url
                    ? element('img', { src: image.url, alt: image.alt || record.title || '' }, [])
                    : element('span', { className: 'dashicons dashicons-format-image' }, [])
            ]);
        }

        if (column.key === 'title') {
            return element('td', { className: 'column-primary column-name' }, [
                element('strong', {}, [
                    element('button', {
                        type: 'button',
                        className: 'wordvel-link-button row-title',
                        'data-wordvel-record-id': record.id,
                        onclick: function () {
                            selectRecord(record);
                        }
                    }, [record.title || '#' + record.id])
                ]),
                element('div', { className: 'row-actions' }, [
                    element('span', { className: 'edit' }, [
                        element('button', {
                            type: 'button',
                            className: 'wordvel-link-button',
                            onclick: function () {
                                selectRecord(record);
                            }
                        }, ['Edit'])
                    ]),
                    ' | ',
                    element('span', { className: 'trash' }, [
                        element('button', {
                            type: 'button',
                            className: 'wordvel-link-button submitdelete',
                            onclick: function () {
                                selectRecord(record);
                                window.setTimeout(deleteRecord, 0);
                            }
                        }, ['Trash'])
                    ])
                ]),
                element('button', { type: 'button', className: 'toggle-row' }, [
                    element('span', { className: 'screen-reader-text' }, ['Show more details'])
                ])
            ]);
        }

        if (column.key === 'id') {
            return element('td', {}, ['#' + record.id]);
        }

        return element('td', {}, [formatListValue(column.key, record)]);
    }

    function resourceFilterControls() {
        if (!fieldByKey('categories') && !fieldByKey('stock_status')) {
            return [];
        }

        var controls = [];

        if (fieldByKey('categories')) {
            controls.push(element('select', {}, [
                element('option', { value: '' }, ['Select a category'])
            ]));
        }

        if (fieldByKey('stock_status')) {
            controls.push(element('select', {}, [
                element('option', { value: '' }, ['Filter by stock status'])
            ]));
        }

        controls.push(element('button', { type: 'button', className: 'button', disabled: 'disabled' }, ['Filter']));

        return controls;
    }

    function formatListValue(key, record) {
        var value = recordValue(record, key);

        if (key === 'price_cents') {
            return formatPrice(value, recordValue(record, 'currency') || 'NIO');
        }

        if (key === 'stock_status') {
            return value === 'in_stock' ? 'In stock' : (value || '—');
        }

        if (key === 'is_featured') {
            return value ? '★' : '☆';
        }

        if (key === 'categories') {
            return relationshipListLabels(fieldByKey(key), value);
        }

        if (value == null || value === '') {
            return '—';
        }

        return String(value);
    }

    function formatPrice(value, currency) {
        var amount = Number(value || 0) / 100;
        var symbol = currency === 'NIO' ? 'C$' : currency;

        return symbol + ' ' + amount.toLocaleString('es-NI', { maximumFractionDigits: 0 });
    }

    function relationshipListLabels(field, value) {
        var ids = Array.isArray(value) ? value.map(String) : (value ? [String(value)] : []);
        var resource = field && field.options && field.options.resource;

        if (!resource || !ids.length) {
            return '—';
        }

        var labels = state.optionLabels[resource] || {};
        var missing = ids.filter(function (id) {
            return !labels[id];
        });

        if (missing.length) {
            window.setTimeout(function () {
                loadRelationshipOptions(field, missing).then(render);
            }, 0);
        }

        return ids.map(function (id) {
            return labels[id] || '#' + id;
        }).join(', ');
    }

    function recordValue(record, key) {
        return record && record.values ? record.values[key] : null;
    }

    function fieldByKey(key) {
        return (config.schema.fields || []).find(function (field) {
            return field.key === key;
        });
    }

    function renderForm() {
        var groups = editorFieldGroups();

        return element('section', { className: 'wordvel-resource-form' }, [
            element('h1', { className: 'wp-heading-inline' }, [state.active ? 'Edit ' + config.schema.label : 'Add New ' + config.schema.label]),
            state.active ? element('button', { type: 'button', className: 'page-title-action', onclick: newRecord }, ['Add New']) : null,
            renderLanguageSwitcher(),
            element('hr', { className: 'wp-header-end' }, []),
            state.notice ? element('div', {
                className: 'notice notice-' + (state.notice.type === 'error' ? 'error' : 'success') + ' wordvel-resource-notice'
            }, [element('p', {}, [state.notice.message])]) : null,
            element('div', { id: 'poststuff', className: 'wordvel-poststuff' }, [
                element('div', { id: 'post-body', className: 'metabox-holder columns-2' }, [
                    element('div', { id: 'post-body-content' }, [
                        groups.title ? renderTitleField(groups.title) : null,
                        groups.slug ? element('div', { className: 'wordvel-slug-field' }, [renderField(groups.slug)]) : null,
                        groups.description ? renderPostbox(groups.description.label || 'Description', [renderField(groups.description)], 'wordvel-description-box') : null,
                        groups.productData.length ? renderPostbox('Product data', [
                            element('div', { className: 'wordvel-product-data-fields' }, groups.productData.map(renderField))
                        ], 'wordvel-product-data-box') : null,
                        groups.attributes ? renderPostbox(groups.attributes.label || 'Attributes', [renderField(groups.attributes)], 'wordvel-attributes-box') : null,
                        groups.shortDescription ? renderPostbox(groups.shortDescription.label || 'Short description', [renderField(groups.shortDescription)], 'wordvel-short-description-box') : null,
                        groups.main.length ? renderPostbox('Details', [
                            element('div', { className: 'wordvel-resource-fields' }, groups.main.map(renderField))
                        ], 'wordvel-resource-main-box') : null
                    ]),
                    element('div', { id: 'postbox-container-1', className: 'postbox-container' }, [
                        element('div', { id: 'side-sortables', className: 'meta-box-sortables ui-sortable' }, [
                            renderPublishBox(groups.status),
                            groups.featuredImage ? renderPostbox(groups.featuredImage.label || 'Featured image', [renderMediaField(groups.featuredImage, fieldValue(groups.featuredImage))], 'postimagediv') : null,
                            groups.gallery ? renderPostbox(groups.gallery.label || 'Product gallery', [renderMediaField(groups.gallery, fieldValue(groups.gallery))], 'wordvel-gallery-box') : null,
                            groups.categories ? renderPostbox(groups.categories.label || 'Categories', [renderRelationshipField(groups.categories, fieldValue(groups.categories))], 'categorydiv') : null,
                        ].concat(groups.side.map(function (field) {
                                return renderPostbox(field.label || field.key, [renderField(field)], 'wordvel-side-box');
                            })))
                    ])
                ])
            ]),
        ]);
    }

    function renderPostbox(title, children, className) {
        return element('div', { className: 'postbox ' + (className || '') }, [
            element('div', { className: 'postbox-header' }, [
                element('h2', {}, [title]),
                element('div', { className: 'handle-actions hide-if-no-js' }, [
                    element('button', { type: 'button', className: 'handlediv', 'aria-expanded': 'true' }, [
                        element('span', { className: 'screen-reader-text' }, ['Toggle panel']),
                        element('span', { className: 'toggle-indicator' }, [])
                    ])
                ])
            ]),
            element('div', { className: 'inside' }, children)
        ]);
    }

    function renderPublishBox(statusField) {
        return element('div', { id: 'submitdiv', className: 'postbox wordvel-submitbox' }, [
            element('div', { className: 'postbox-header' }, [
                element('h2', {}, ['Publish'])
            ]),
            element('div', { className: 'inside' }, [
                element('div', { id: 'submitpost', className: 'submitbox' }, [
                    element('div', { id: 'minor-publishing' }, [
                        element('div', { id: 'minor-publishing-actions' }, [
                            state.active ? element('div', { id: 'preview-action' }, [
                                element('span', { className: 'wordvel-record-meta' }, ['ID: ' + state.active.id])
                            ]) : null,
                            element('div', { className: 'clear' }, [])
                        ]),
                        element('div', { id: 'misc-publishing-actions' }, [
                            element('div', { className: 'misc-pub-section misc-pub-post-status' }, [
                                element('span', { className: 'dashicons dashicons-admin-post' }, []),
                                ' Status: ',
                                statusField ? renderField(statusField) : element('strong', {}, [state.active ? 'Saved' : 'Draft'])
                            ])
                        ])
                    ]),
                    element('div', { id: 'major-publishing-actions' }, [
                        state.active ? element('div', { id: 'delete-action' }, [
                            element('button', {
                                type: 'button',
                                className: 'submitdelete deletion wordvel-link-button',
                                onclick: deleteRecord,
                                disabled: state.isSaving ? 'disabled' : null
                            }, ['Move to Trash'])
                        ]) : null,
                        element('div', { id: 'publishing-action' }, [
                            button(state.isSaving ? 'Saving...' : 'Save ' + config.schema.label, 'primary', saveRecord, state.isSaving)
                        ]),
                        element('div', { className: 'clear' }, [])
                    ])
                ])
            ])
        ]);
    }

    function renderTitleField(field) {
        var value = fieldValue(field);

        return element('div', { id: 'titlediv', className: 'wordvel-titlediv' }, [
            element('div', { id: 'titlewrap' }, [
                element('label', { className: 'screen-reader-text', for: 'title' }, [field.label || 'Title']),
                element('input', {
                    type: 'text',
                    id: 'title',
                    size: '30',
                    value: value || '',
                    placeholder: field.label || 'Title',
                    'data-wordvel-field-key': field.key,
                    oninput: function (event) {
                        setFieldValue(field, event.target.value);
                    }
                })
            ])
        ]);
    }

    function editorFieldGroups() {
        var fields = config.schema.fields || [];
        var groups = {
            title: null,
            slug: null,
            status: null,
            description: null,
            shortDescription: null,
            productData: [],
            attributes: null,
            featuredImage: null,
            gallery: null,
            categories: null,
            side: [],
            main: []
        };
        var productDataKeys = ['sku', 'type', 'price_cents', 'compare_at_price_cents', 'currency', 'stock_quantity', 'stock_status', 'is_featured'];

        fields.forEach(function (field) {
            if (isTitleField(field)) {
                groups.title = groups.title || field;
                return;
            }

            if (field.type === 'slug' || field.key === 'slug') {
                groups.slug = field;
                return;
            }

            if (field.key === 'status') {
                groups.status = field;
                return;
            }

            if (field.key === 'description' || field.key === 'content') {
                groups.description = field;
                return;
            }

            if (field.key === 'short_description' || field.key === 'excerpt') {
                groups.shortDescription = field;
                return;
            }

            if (isFeaturedImageField(field)) {
                groups.featuredImage = field;
                return;
            }

            if (field.type === 'media' && (field.key === 'gallery' || field.key === 'product_gallery')) {
                groups.gallery = field;
                return;
            }

            if (field.type === 'relationship' && (field.key === 'categories' || field.key === 'category')) {
                groups.categories = field;
                return;
            }

            if (field.key === 'attribute_values' || field.key === 'attributes') {
                groups.attributes = field;
                return;
            }

            if (productDataKeys.indexOf(field.key) !== -1) {
                groups.productData.push(field);
                return;
            }

            groups.main.push(field);
        });

        return groups;
    }

    function isTitleField(field) {
        var titleFieldKey = config.schema && config.schema.options && config.schema.options.title_field;

        return field && (field.key === titleFieldKey || (!titleFieldKey && ['title', 'name'].indexOf(field.key) !== -1));
    }

    function isFeaturedImageField(field) {
        return field && field.type === 'media' && ['featured_image', 'primary_image', 'post_image'].indexOf(field.key) !== -1;
    }

    function activeRecordTitle() {
        var titleFieldKey = config.schema && config.schema.options && config.schema.options.title_field;
        var fields = config.schema && Array.isArray(config.schema.fields) ? config.schema.fields : [];
        var titleField = fields.find(function (field) {
            return titleFieldKey ? field.key === titleFieldKey : ['title', 'name', 'headline'].indexOf(field.key) !== -1;
        }) || fields[0];

        if (!titleField) {
            return state.active && state.active.title ? state.active.title : '#' + state.active.id;
        }

        var title = fieldValue(titleField);

        return title ? String(title) : (state.active && state.active.title ? state.active.title : '#' + state.active.id);
    }

    function renderLanguageSwitcher() {
        if (!hasAnyLocalizedFields()) {
            return null;
        }

        return element('label', { className: 'wordvel-language-switcher' }, [
            element('span', {}, ['Language']),
            element('select', {
                value: state.language,
                onchange: function (event) {
                    changeLanguage(event.target.value);
                }
            }, languages.map(function (language) {
                return element('option', { value: language.code }, [language.label]);
            }))
        ]);
    }

    function renderField(field) {
        var value = fieldValue(field);

        if (field.type === 'rich_text') {
            return fieldWrap(field, [
                element('textarea', {
                    id: editorId(field),
                    'data-wordvel-field-key': field.key,
                    rows: '8',
                    value: value || '',
                    onchange: function (event) {
                        setFieldValue(field, event.target.value);
                    }
                })
            ]);
        }

        if (field.type === 'number') {
            if (isTimecodeField(field)) {
                return fieldWrap(field, [
                    renderTimecodeInput(field, value, function (nextValue) {
                        setValue(field.key, nextValue);
                    }, {
                        'data-wordvel-field-key': field.key
                    })
                ]);
            }

            return fieldWrap(field, [
                element('input', {
                    type: 'number',
                    value: value == null ? '' : String(value),
                    'data-wordvel-field-key': field.key,
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
                        'data-wordvel-field-key': field.key,
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
                    'data-wordvel-field-key': field.key,
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

        if (field.type === 'repeater') {
            return renderRepeaterField(field, value);
        }

        if (field.type === 'group') {
            return renderGroupField(field, value);
        }

        return fieldWrap(field, [
            element('input', {
                type: field.type === 'url' ? 'url' : 'text',
                value: value || '',
                'data-wordvel-field-key': field.key,
                oninput: function (event) {
                    setFieldValue(field, event.target.value);
                }
            })
        ]);
    }

    function renderRepeaterField(field, value) {
        var rows = Array.isArray(value) ? value : [];
        var childFields = field.fields || [];
        var sortable = !!(field.options && field.options.sortable);

        if (sortable) {
            var maxVisibleRows = field.options && field.options.max_visible_rows;
            var scrollable = Number.isFinite(maxVisibleRows) && maxVisibleRows > 0;
            var insertBetween = !!(field.options && field.options.insert_between);

            return fieldWrap(field, [
                element('div', { className: 'wordvel-repeater-field' }, [
                    element('div', {
                        className: 'wordvel-repeater-rows is-sortable' + (scrollable ? ' is-scrollable' : ''),
                        style: scrollable ? { '--wordvel-repeater-max-rows': String(maxVisibleRows) } : null
                    }, [
                        element('div', {
                            className: 'wordvel-repeater-sortable-list',
                            'data-wordvel-repeater-scroll': field.key
                        }, rows.map(function (row, index) {
                            var isDragging = state.drag && state.drag.kind === 'repeater' && state.drag.fieldKey === field.key && state.drag.index === index;
                            var dropClass = state.dragOver && state.dragOver.kind === 'repeater' && state.dragOver.fieldKey === field.key && state.dragOver.index === index
                                ? ' drop-' + state.dragOver.position
                                : '';

                            return element('div', {
                                className: 'wordvel-repeater-sortable-item' + (isDragging ? ' is-dragging' : '') + dropClass,
                                'data-wordvel-repeater-field': field.key
                            }, [
                                element('div', { className: 'wordvel-repeater-sortable-row' }, [
                                    element('span', {
                                        className: 'wordvel-drag-handle',
                                        title: 'Drag to reorder',
                                        onmousedown: function (event) {
                                            if (event.button != null && event.button !== 0) {
                                                return;
                                            }

                                            event.preventDefault();
                                            startRepeaterDrag(event, field.key, index);
                                        }
                                    }, ['⋮⋮']),
                                    element('div', { className: 'wordvel-repeater-sortable-body' }, [
                                        element('div', { className: 'wordvel-repeater-row-fields wordvel-repeater-row-fields-' + childFields.length }, childFields.map(function (childField) {
                                            return renderNestedField(childField, row ? row[childField.key] : null, function (nextValue) {
                                                updateRepeaterRow(field, index, childField.key, nextValue);
                                            }, childFields.length === 2);
                                        }))
                                    ]),
                                    element('span', { className: 'wordvel-repeater-sortable-actions' }, [
                                        iconButton('dashicons-trash', 'Remove', function () {
                                            removeRepeaterRow(field, index);
                                        }, false, true)
                                    ])
                                ]),
                                insertBetween ? element('button', {
                                    type: 'button',
                                    className: 'wordvel-repeater-insert-after',
                                    title: 'Insert below',
                                    'aria-label': 'Insert below',
                                    onclick: function () {
                                        insertRepeaterRowAfter(field, index, childFields);
                                    }
                                }, ['+']) : null
                            ]);
                        }))
                    ]),
                    button('Add item', 'small', function () {
                        setValue(field.key, rows.concat([emptyValuesForFields(childFields)]));
                        refreshField(field);
                    })
                ])
            ]);
        }

        return fieldWrap(field, [
            element('div', { className: 'wordvel-repeater-field' }, [
                element('div', { className: 'wordvel-repeater-rows' }, rows.map(function (row, index) {
                    return element('div', { className: 'wordvel-repeater-row' }, [
                        element('div', { className: 'wordvel-repeater-row-fields wordvel-repeater-row-fields-' + childFields.length }, childFields.map(function (childField) {
                            return renderNestedField(childField, row ? row[childField.key] : null, function (nextValue) {
                                updateRepeaterRow(field, index, childField.key, nextValue);
                            }, childFields.length === 2);
                        })),
                        element('div', { className: 'wordvel-repeater-row-actions' }, [
                            iconButton('dashicons-arrow-up-alt2', 'Move up', function () {
                                moveRepeaterRow(field, index, -1);
                            }, index === 0),
                            iconButton('dashicons-arrow-down-alt2', 'Move down', function () {
                                moveRepeaterRow(field, index, 1);
                            }, index === rows.length - 1),
                            iconButton('dashicons-trash', 'Remove', function () {
                                removeRepeaterRow(field, index);
                            }, false, true)
                        ])
                    ]);
                })),
                button('Add item', 'small', function () {
                    setValue(field.key, rows.concat([emptyValuesForFields(childFields)]));
                    refreshField(field);
                })
            ])
        ]);
    }

    function renderGroupField(field, value) {
        var row = value && typeof value === 'object' && !Array.isArray(value) ? value : {};

        return fieldWrap(field, [
            element('div', { className: 'wordvel-group-field' }, (field.fields || []).map(function (childField) {
                return renderNestedField(childField, row[childField.key], function (nextValue) {
                    var nextRow = Object.assign({}, row);
                    nextRow[childField.key] = nextValue;
                    setValue(field.key, nextRow);
                });
            }))
        ]);
    }

    function renderNestedField(field, value, setValueCallback, compact) {
        if (field.type === 'number') {
            if (isTimecodeField(field)) {
                return nestedFieldWrap(field, renderTimecodeInput(field, value, setValueCallback, {
                    placeholder: field.label || field.key
                }));
            }

            return nestedFieldWrap(field, element('input', {
                type: 'number',
                value: value == null ? '' : String(value),
                placeholder: field.label || field.key,
                oninput: function (event) {
                    setValueCallback(event.target.value);
                }
            }));
        }

        if (field.type === 'boolean') {
            return nestedFieldWrap(field, element('label', {}, [
                element('input', {
                    type: 'checkbox',
                    checked: value ? 'checked' : null,
                    onchange: function (event) {
                        setValueCallback(event.target.checked);
                    }
                }),
                ' Enabled'
            ]));
        }

        if (field.type === 'textarea' || field.type === 'rich_text') {
            return nestedFieldWrap(field, element('textarea', {
                rows: compact ? '1' : '3',
                value: value || '',
                placeholder: field.label || field.key,
                oninput: function (event) {
                    setValueCallback(event.target.value);
                }
            }));
        }

        if (field.type === 'select') {
            var options = (field.options && field.options.options) || {};

            return nestedFieldWrap(field, element('select', {
                value: value || '',
                onchange: function (event) {
                    setValueCallback(event.target.value);
                }
            }, [element('option', { value: '' }, ['Select one'])].concat(Object.keys(options).map(function (optionValue) {
                return element('option', { value: optionValue }, [options[optionValue]]);
            }))));
        }

        return nestedFieldWrap(field, element('input', {
            type: field.type === 'url' ? 'url' : 'text',
            value: value || '',
            placeholder: field.label || field.key,
            oninput: function (event) {
                setValueCallback(event.target.value);
            }
        }));
    }

    function nestedFieldWrap(field, control) {
        return element('label', { className: 'wordvel-nested-field' }, [
            element('span', {}, [field.label || field.key]),
            control
        ]);
    }

    function updateRepeaterRow(field, index, childKey, nextValue) {
        var rows = Array.isArray(state.values[field.key]) ? state.values[field.key].slice() : [];
        var row = Object.assign({}, rows[index] || {});
        row[childKey] = nextValue;
        rows[index] = row;
        setValue(field.key, rows);
    }

    function moveRepeaterRow(field, index, direction) {
        var rows = Array.isArray(state.values[field.key]) ? state.values[field.key].slice() : [];
        var nextIndex = index + direction;

        if (nextIndex < 0 || nextIndex >= rows.length) {
            return;
        }

        var row = rows[index];
        rows[index] = rows[nextIndex];
        rows[nextIndex] = row;
        setValue(field.key, rows);
        refreshField(field);
    }

    function removeRepeaterRow(field, index) {
        var rows = Array.isArray(state.values[field.key]) ? state.values[field.key].slice() : [];
        rows.splice(index, 1);
        setValue(field.key, rows);
        refreshField(field);
    }

    function insertRepeaterRowAfter(field, index, childFields) {
        var rows = Array.isArray(state.values[field.key]) ? state.values[field.key].slice() : [];
        rows.splice(index + 1, 0, emptyValuesForFields(childFields));
        setValue(field.key, rows);
        refreshField(field);
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
                loadRelationshipOptions(field, selectedIds).then(function () {
                    refreshField(field);
                });
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
                                    loadRelationshipOptions(field).then(function () {
                                        refreshField(field);
                                        restoreRelationshipFocus();
                                    });
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
                var isDragging = state.drag && state.drag.kind === 'relationship' && state.drag.fieldKey === field.key && state.drag.index === index;
                var dropClass = state.dragOver && state.dragOver.kind === 'relationship' && state.dragOver.fieldKey === field.key && state.dragOver.index === index
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
                            refreshField(field);
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

        return emptyValuesForFields(fields);
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
        var value = fieldValue(field, modal.values);
        var setModalValue = function (nextValue) {
            setModalFieldValue(field, nextValue);
            render();
        };

        if (field.type === 'number') {
            if (isTimecodeField(field)) {
                return fieldWrap(field, [
                    renderTimecodeInput(field, value, function (nextValue) {
                        modal.values[field.key] = nextValue;
                    }, {
                        disabled: field.options && field.options.readonly ? 'disabled' : null
                    })
                ]);
            }

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
                    'data-wordvel-field-key': field.key,
                    oninput: function (event) {
                        setModalFieldValue(field, event.target.value);
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
                'data-wordvel-field-key': field.key,
                oninput: function (event) {
                    setModalFieldValue(field, event.target.value);
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
            kind: 'relationship',
            fieldKey: fieldKey,
            index: index,
            ids: ids.slice()
        };
        state.dragOver = null;

        var row = event.currentTarget.closest('.wordvel-resource-relation-row');

        if (row) {
            row.classList.add('is-dragging');
        }

        document.addEventListener('mousemove', handleSortableDragMove);
        document.addEventListener('mouseup', finishSortableDrag);
    }

    function startRepeaterDrag(event, fieldKey, index) {
        event.preventDefault();
        state.drag = {
            kind: 'repeater',
            fieldKey: fieldKey,
            index: index
        };
        state.dragOver = null;

        var row = event.currentTarget.closest('.wordvel-repeater-sortable-item');

        if (row) {
            row.classList.add('is-dragging');
        }

        document.addEventListener('mousemove', handleSortableDragMove);
        document.addEventListener('mouseup', finishSortableDrag);
    }

    function handleSortableDragMove(event) {
        if (!state.drag) {
            return;
        }

        updateDragPreviewFromPoint(event.clientY, state.drag.fieldKey, state.drag.kind);
    }

    function finishSortableDrag() {
        document.removeEventListener('mousemove', handleSortableDragMove);
        document.removeEventListener('mouseup', finishSortableDrag);

        var drag = state.drag;
        var dragOver = state.dragOver;

        if (drag && dragOver) {
            if (drag.kind === 'repeater') {
                reorderRepeaterRows(drag.fieldKey, drag.index, dragOver.index, dragOver.position);
            } else {
                moveSelectedTo(drag.fieldKey, drag.ids || [], drag.index, dragOver.index, dragOver.position);
            }

            return;
        }

        state.drag = null;
        state.dragOver = null;
        clearDragPreviewClasses(true);
    }

    function reorderRepeaterRows(fieldKey, fromIndex, toIndex, position) {
        var rows = Array.isArray(state.values[fieldKey]) ? state.values[fieldKey].slice() : [];

        if (fromIndex === null || toIndex === null || fromIndex < 0 || toIndex < 0 || fromIndex >= rows.length) {
            state.drag = null;
            state.dragOver = null;
            clearDragPreviewClasses(true);
            render();
            return;
        }

        var moved = rows.splice(fromIndex, 1)[0];
        var insertIndex = position === 'after' ? toIndex + 1 : toIndex;

        if (fromIndex < insertIndex) {
            insertIndex -= 1;
        }

        rows.splice(Math.max(0, Math.min(insertIndex, rows.length)), 0, moved);
        setValue(fieldKey, rows);
        state.drag = null;
        state.dragOver = null;
        clearDragPreviewClasses(true);
        render();
    }

    function updateDragPreviewFromPoint(clientY, fieldKey, kind) {
        var selector = kind === 'repeater'
            ? '[data-wordvel-repeater-field="' + fieldKey + '"]'
            : '[data-wordvel-relation-field="' + fieldKey + '"]';
        var rows = Array.prototype.slice.call(root.querySelectorAll(selector));

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
            && state.dragOver.kind === kind
            && state.dragOver.fieldKey === fieldKey
            && state.dragOver.index === index
            && state.dragOver.position === position
        ) {
            return;
        }

        state.dragOver = { kind: kind, fieldKey: fieldKey, index: index, position: position };
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
        root.querySelectorAll('.wordvel-resource-relation-row, .wordvel-repeater-sortable-item').forEach(function (row) {
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
        return element('div', {
            className: 'wordvel-resource-field',
            'data-wordvel-field-wrap': field.key
        }, [
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
        return 'wordvel-resource-editor-' + config.resourceKey + '-' + field.key;
    }

    function refreshRecordForm() {
        refreshActiveRecordInList();
        refreshActiveTitle();
        refreshActionButtons();

        (config.schema.fields || []).forEach(function (field) {
            if (field.type === 'media' || field.type === 'relationship' || field.type === 'repeater' || field.type === 'group') {
                refreshField(field);
                return;
            }

            refreshFieldControl(field);
        });

        initializeRichTextEditors();
        restoreRelationshipFocus();
    }

    function refreshLocalizedControls() {
        refreshActiveTitle();

        (config.schema.fields || []).forEach(function (field) {
            if (!isLocalizedField(field)) {
                return;
            }

            var value = fieldValue(field) || '';

            if (field.type === 'rich_text') {
                refreshRichTextField(field, value);
                return;
            }

            refreshFieldControl(field);
        });
    }

    function refreshActiveTitle() {
        var heading = root.querySelector('.wordvel-resource-form-header h2');

        if (heading && state.active) {
            heading.textContent = activeRecordTitle();
        }
    }

    function refreshActiveRecordInList() {
        root.querySelectorAll('[data-wordvel-record-id]').forEach(function (buttonNode) {
            buttonNode.classList.toggle(
                'is-active',
                !!(state.active && String(buttonNode.getAttribute('data-wordvel-record-id')) === String(state.active.id))
            );
        });
    }

    function refreshActionButtons() {
        var actions = root.querySelector('.wordvel-resource-actions');

        if (!actions) {
            return;
        }

        actions.replaceWith(element('div', { className: 'wordvel-resource-actions' }, [
            button(state.isSaving ? 'Saving...' : 'Save ' + config.schema.label, 'primary', saveRecord, state.isSaving),
            state.active ? button('Delete', 'secondary', deleteRecord, state.isSaving, true) : null
        ]));
    }

    function refreshField(field) {
        var existing = root.querySelector('[data-wordvel-field-wrap="' + field.key + '"]');

        if (!existing) {
            return;
        }

        var repeaterList = existing.querySelector('[data-wordvel-repeater-scroll="' + field.key + '"]');
        var savedRepeaterScrollTop = repeaterList ? repeaterList.scrollTop : null;

        preserveScroll(function () {
            existing.replaceWith(renderField(field));

            if (savedRepeaterScrollTop === null) {
                return;
            }

            var restoreRepeaterScroll = function () {
                var nextList = root.querySelector('[data-wordvel-repeater-scroll="' + field.key + '"]');

                if (nextList) {
                    nextList.scrollTop = savedRepeaterScrollTop;
                }
            };

            restoreRepeaterScroll();
            window.requestAnimationFrame(restoreRepeaterScroll);
        });
    }

    function refreshFieldControl(field) {
        var value = fieldValue(field);

        if (field.type === 'rich_text') {
            refreshRichTextField(field, value || '');
            return;
        }

        var input = root.querySelector('[data-wordvel-field-key="' + field.key + '"]');

        if (!input) {
            return;
        }

        if (field.type === 'boolean') {
            input.checked = !!value;
            return;
        }

        var nextValue = value == null ? '' : String(value);

        if (input.value !== nextValue) {
            input.value = nextValue;
        }
    }

    function refreshRichTextField(field, value) {
        var id = editorId(field);
        var textarea = document.getElementById(id);
        var editor = window.tinymce && window.tinymce.get(id);
        value = value || '';

        if (editor && editor.getContent() !== value) {
            editor.setContent(value);
        }

        if (textarea && textarea.value !== value) {
            textarea.value = value;
        }
    }

    function preserveScroll(callback) {
        var scrollX = window.pageXOffset;
        var scrollY = window.pageYOffset;

        callback();

        window.scrollTo(scrollX, scrollY);
        window.requestAnimationFrame(function () {
            window.scrollTo(scrollX, scrollY);
        });
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

            if (!document.getElementById(id) || pendingRichTextEditors[id] || window.tinymce && window.tinymce.get(id)) {
                return;
            }

            pendingRichTextEditors[id] = true;

            window.setTimeout(function () {
                try {
                    delete pendingRichTextEditors[id];

                    if (!document.getElementById(id) || window.tinymce && window.tinymce.get(id)) {
                        return;
                    }

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
                    delete pendingRichTextEditors[id];
                    // Textarea fallback stays usable.
                }
            }, 0);
        });
    }

    function destroyRichTextEditors() {
        if (!window.wp || !window.wp.editor || typeof window.wp.editor.remove !== 'function') {
            return;
        }

        (config.schema.fields || []).forEach(function (field) {
            if (field.type !== 'rich_text') {
                return;
            }

            delete pendingRichTextEditors[editorId(field)];

            try {
                window.wp.editor.remove(editorId(field));
            } catch (error) {
                // The editor may not have been initialized yet.
            }
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

            var textarea = document.getElementById(editorId(field));
            var wrapper = document.getElementById('wp-' + editorId(field) + '-wrap');
            var isCodeMode = wrapper && wrapper.classList.contains('html-active');
            var editor = window.tinymce.get(editorId(field));

            if (isCodeMode && textarea) {
                setFieldValue(field, textarea.value);
            } else if (editor) {
                setFieldValue(field, editor.getContent());
            } else if (textarea) {
                setFieldValue(field, textarea.value);
            }
        });
    }

    window.addEventListener('popstate', restoreEditorFromUrl);

    loadAllRelationshipOptions().then(loadRecords);
})();
