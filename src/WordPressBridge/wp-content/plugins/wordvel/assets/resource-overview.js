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
        root.appendChild(element('div', { className: 'wordvel-resource-overview-list' }, [
            element('table', { className: 'wp-list-table widefat fixed striped table-view-list wordvel-resource-overview-table' }, [
                element('thead', {}, [
                    element('tr', {}, [
                        element('th', { scope: 'col' }, ['Resource']),
                        element('th', { scope: 'col' }, ['Records']),
                        element('th', { scope: 'col' }, ['Latest']),
                        element('th', { scope: 'col' }, ['Actions'])
                    ])
                ]),
                element('tbody', {}, config.resources.map(renderRow))
            ])
        ]));
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

    function renderRow(resource) {
        var records = state.records[resource.key] || [];
        var active = records[0] || null;
        var preview = active && active.preview && active.preview.url ? active.preview : null;

        return element('tr', {}, [
            element('td', { className: 'wordvel-resource-title column-primary' }, [
                element('strong', {}, [
                    element('a', { href: editorUrl(resource, null) }, [resource.plural_label || resource.key])
                ]),
                element('div', { className: 'row-actions' }, [
                    element('span', { className: 'edit' }, [
                        element('a', { href: editorUrl(resource, active) }, [active ? 'Edit latest' : 'Add new'])
                    ]),
                    ' | ',
                    element('span', { className: 'view' }, [
                        element('a', { href: editorUrl(resource, null) }, ['View all'])
                    ])
                ])
            ]),
            element('td', {}, [state.isLoading ? 'Loading...' : String(records.length)]),
            element('td', {}, [
                element('a', {
                    className: 'wordvel-resource-latest',
                    href: editorUrl(resource, active)
                }, [
                    preview
                        ? element('img', { src: preview.url, alt: preview.alt || preview.title || '' }, [])
                        : element('span', { className: 'dashicons dashicons-admin-generic' }, []),
                    element('span', {}, [active ? (active.title || active.label || ('#' + active.id)) : 'No records yet'])
                ])
            ]),
            element('td', {}, [
                element('a', { className: 'button button-small', href: editorUrl(resource, null) }, ['Add New']),
                active ? element('a', { className: 'button button-small', href: editorUrl(resource, active) }, ['Edit']) : null
            ])
        ]);
    }

    function editorUrl(resource, record) {
        var params = new URLSearchParams();
        params.set('page', 'wordvel-resource-' + resource.key);

        if (record && record.id) {
            params.set('record', record.id);
        }

        return String(config.adminUrl || 'admin.php') + '?' + params.toString();
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
