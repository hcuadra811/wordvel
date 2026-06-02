(function bootWordVelResourceOverview() {
    var config = window.wordvelResourceGroup;
    var root = document.getElementById('wordvel-resource-overview-root');

    if (!config || !root || !config.apiBaseUrl || !Array.isArray(config.resources)) {
        return;
    }

    var state = {
        records: {},
        activeIndex: {},
        isLoading: true
    };

    function request(path) {
        return window.fetch(config.apiBaseUrl + path, {
            headers: { Accept: 'application/json' },
            credentials: 'omit'
        }).then(function (response) {
            return response.json().then(function (json) {
                if (!response.ok || json.status === false) {
                    throw new Error((json.error && json.error.message) || json.message || 'WordVel request failed.');
                }

                return json.data;
            });
        });
    }

    function load() {
        render();

        Promise.all(config.resources.map(function (resource) {
            return request('/' + resource.key + '?summary=1&summary_preview=1')
                .then(function (records) {
                    state.records[resource.key] = latestFirst(Array.isArray(records) ? records : []);
                })
                .catch(function () {
                    state.records[resource.key] = [];
                });
        })).finally(function () {
            state.isLoading = false;
            render();
        });
    }

    function render() {
        root.innerHTML = '';
        root.appendChild(element('div', { className: 'wordvel-resource-overview-grid' }, config.resources.map(renderCard)));
    }

    function latestFirst(records) {
        return records.slice().sort(function (left, right) {
            var leftId = Number(left && left.id);
            var rightId = Number(right && right.id);

            if (!Number.isFinite(leftId) || !Number.isFinite(rightId)) {
                return 0;
            }

            return rightId - leftId;
        });
    }

    function renderCard(resource) {
        var records = state.records[resource.key] || [];
        var activeIndex = Math.min(state.activeIndex[resource.key] || 0, Math.max(records.length - 1, 0));
        var active = records[activeIndex] || null;
        var preview = active && active.preview && active.preview.url ? active.preview : null;

        return element('article', { className: 'wordvel-resource-overview-card' }, [
            element('div', { className: 'wordvel-resource-overview-card-header' }, [
                element('span', {}, [resource.label || 'Resource']),
                element('strong', {}, [resource.plural_label || resource.key]),
                element('small', {}, [state.isLoading ? 'Loading...' : records.length + ' total'])
            ]),
            element('a', {
                className: 'wordvel-resource-overview-feature',
                href: editorUrl(resource, active)
            }, [
                preview
                    ? element('img', { src: preview.url, alt: preview.alt || preview.title || '' }, [])
                    : element('div', { className: 'wordvel-resource-overview-placeholder' }, [
                        element('span', { className: 'dashicons dashicons-format-image' }, [])
                    ]),
                element('span', {}, [
                    element('strong', {}, [active ? (active.title || active.label || ('#' + active.id)) : 'No records yet']),
                    element('small', {}, [active ? 'Open selected record' : 'Create the first record'])
                ])
            ]),
            records.length > 1 ? element('div', { className: 'wordvel-resource-overview-controls' }, [
                iconButton('dashicons-arrow-left-alt2', 'Previous', function () {
                    move(resource.key, records.length, -1);
                }),
                element('span', {}, [(activeIndex + 1) + ' / ' + records.length]),
                iconButton('dashicons-arrow-right-alt2', 'Next', function () {
                    move(resource.key, records.length, 1);
                })
            ]) : null,
            records.length > 1 ? element('div', { className: 'wordvel-resource-overview-strip' }, records.map(function (record, index) {
                var itemPreview = record.preview && record.preview.url ? record.preview : null;

                return element('button', {
                    type: 'button',
                    className: index === activeIndex ? 'is-active' : '',
                    onclick: function () {
                        state.activeIndex[resource.key] = index;
                        render();
                    }
                }, [
                    itemPreview ? element('img', { src: itemPreview.url, alt: '' }, []) : element('span', { className: 'dashicons dashicons-admin-generic' }, []),
                    element('strong', {}, [record.title || record.label || ('#' + record.id)])
                ]);
            })) : null
        ]);
    }

    function move(resourceKey, length, direction) {
        if (!length) {
            return;
        }

        state.activeIndex[resourceKey] = ((state.activeIndex[resourceKey] || 0) + direction + length) % length;
        render();
    }

    function editorUrl(resource, record) {
        var params = new URLSearchParams();
        params.set('page', 'wordvel-resource-' + resource.key);

        if (record && record.id) {
            params.set('record', record.id);
        }

        return String(config.adminUrl || 'admin.php') + '?' + params.toString();
    }

    function iconButton(icon, label, onClick) {
        return element('button', {
            type: 'button',
            className: 'button button-small wordvel-icon-button',
            title: label,
            'aria-label': label,
            onclick: onClick
        }, [element('span', { className: 'dashicons ' + icon }, [])]);
    }

    function element(tag, attributes, children) {
        var node = document.createElement(tag);

        Object.keys(attributes || {}).forEach(function (key) {
            var value = attributes[key];

            if (value == null) {
                return;
            }

            if (key === 'className') {
                node.className = value;
                return;
            }

            if (key.indexOf('on') === 0 && typeof value === 'function') {
                node.addEventListener(key.slice(2), value);
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

        return node;
    }

    load();
})();
