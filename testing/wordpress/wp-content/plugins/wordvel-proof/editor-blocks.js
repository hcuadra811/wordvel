(function (wp) {
    if (!wp || !wp.blocks || !wp.element || !wp.components || !wp.blockEditor || !window.wordvelBlocks) {
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var ToggleControl = wp.components.ToggleControl;
    var ColorPalette = wp.components.ColorPalette;
    var Button = wp.components.Button;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var MediaUpload = wp.blockEditor.MediaUpload;
    var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
    var RichText = wp.blockEditor.RichText;
    var useBlockProps = wp.blockEditor.useBlockProps;

    window.wordvelBlocks.forEach(function (schema) {
        var attributes = {};

        (schema.fields || []).forEach(function (field) {
            attributes[field.key] = {
                type: field.type === 'repeater' ? 'array' : 'string',
                default: field.type === 'repeater' ? (field.default || []) : (field.default || '')
            };
        });

        wp.blocks.registerBlockType('wordvel/' + schema.key, {
            title: schema.name,
            category: 'wordvel',
            icon: 'layout',
            attributes: attributes,
            edit: function (props) {
                var generatedPreview = wordvelGeneratedPreview(schema, props);
                var blockProps = useBlockProps({
                    className: 'wordvel-block wordvel-block-' + schema.key + (generatedPreview.className ? ' ' + generatedPreview.className : '')
                });

                return el(Fragment, {}, [
                    el(InspectorControls, { key: 'inspector' },
                        el(PanelBody, { title: schema.name, initialOpen: true },
                            (schema.fields || []).map(function (field) {
                                return wordvelFieldControl(field, props);
                            })
                        )
                    ),
                    generatedPreview.html
                        ? el('div', Object.assign({}, blockProps, {
                            dangerouslySetInnerHTML: { __html: generatedPreview.html }
                        }))
                        : wordvelBlockPreview(schema, props, blockProps)
                ]);
            },
            save: function () {
                return null;
            }
        });
    });

    function wordvelGeneratedPreview(schema, props) {
        var preview = window.wordvelEditorPreviews && window.wordvelEditorPreviews[schema.key];
        var html = preview && preview.html ? String(preview.html) : '';

        if (!html) {
            return { html: '', className: '' };
        }

        html = wordvelBindTemplate(html, props.attributes);
        html = wordvelDisablePreviewLinks(html);

        var container = document.createElement('div');
        container.innerHTML = html;
        var root = container.firstElementChild;
        var className = root ? root.getAttribute('class') || '' : '';

        if (root) {
            container.innerHTML = root.innerHTML;
        }

        wordvelRemoveEmptyPreviewItems(container);

        return {
            html: container.innerHTML,
            className: className
        };
    }

    function wordvelBindTemplate(html, attributes) {
        return html.replace(/\{\{\s*([^}]+?)\s*\}\}/g, function (_, path) {
            var value = wordvelValueAtPath(attributes, path.trim());

            return wordvelEscapeHtml(value == null ? '' : String(value));
        });
    }

    function wordvelValueAtPath(source, path) {
        return path.split('.').reduce(function (value, segment) {
            if (value == null) {
                return null;
            }

            return value[segment];
        }, source);
    }

    function wordvelEscapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function wordvelDisablePreviewLinks(html) {
        return html
            .replace(/<a\b/gi, '<span')
            .replace(/<\/a>/gi, '</span>');
    }

    function wordvelRemoveEmptyPreviewItems(container) {
        Array.prototype.forEach.call(container.querySelectorAll('.feature'), function (item) {
            var title = item.querySelector('h3');
            var body = item.querySelector('p');

            if ((!title || !title.textContent.trim()) && (!body || !body.textContent.trim())) {
                item.remove();
            }
        });
    }

    function wordvelBlockPreview(schema, props, blockProps) {
        if (schema.key === 'hero') {
            return wordvelHeroPreview(props, blockProps);
        }

        if (schema.key === 'feature-grid') {
            return wordvelFeatureGridPreview(props, blockProps);
        }

        if (schema.key === 'principles') {
            return wordvelPrinciplesPreview(props, blockProps);
        }

        if (schema.key === 'workflow') {
            return wordvelWorkflowPreview(props, blockProps);
        }

        if (schema.key === 'api-preview') {
            return wordvelApiPreview(props, blockProps);
        }

        return el('section', blockProps,
            (schema.fields || []).map(function (field) {
                return wordvelPreviewField(field, props);
            })
        );
    }

    function wordvelHeroPreview(props, blockProps) {
        var attrs = props.attributes;
        var stats = [
            { value: attrs.stat_1_value, label: attrs.stat_1_label },
            { value: attrs.stat_2_value, label: attrs.stat_2_label },
            { value: attrs.stat_3_value, label: attrs.stat_3_label }
        ].filter(function (stat) {
            return stat.value || stat.label;
        });

        return el('section', blockProps, [
            el('div', { key: 'copy', className: 'hero-copy' }, [
                wordvelInlineText('eyebrow', 'p', 'eyebrow', 'Eyebrow', props),
                wordvelInlineText('headline', 'h1', '', 'Headline', props),
                wordvelInlineText('body', 'p', 'hero-body', 'Body', props),
                el('div', { key: 'actions', className: 'hero-actions' }, [
                    wordvelInlineAction('primary_action_label', 'primary_action_href', 'primary', 'Primary action', props),
                    wordvelInlineAction('secondary_action_label', 'secondary_action_href', 'secondary', 'Secondary action', props)
                ])
            ]),
            el('div', { key: 'panel', className: 'hero-panel', 'aria-label': 'WordVel content contract preview' }, [
                el('div', { key: 'toolbar', className: 'panel-toolbar' }, [
                    el('span', { key: 'one' }),
                    el('span', { key: 'two' }),
                    el('span', { key: 'three' })
                ]),
                el('div', { key: 'page', className: 'contract-card' }, [
                    el('p', { key: 'title' }, 'PageResource'),
                    el('ul', { key: 'items' }, [
                        el('li', { key: 'id' }, 'id: int'),
                        el('li', { key: 'slug' }, 'slug: string'),
                        el('li', { key: 'blocks' }, 'blocks: oneOf[]')
                    ])
                ]),
                el('div', { key: 'hero', className: 'contract-card raised' }, [
                    el('p', { key: 'title' }, 'HeroBlockData'),
                    el('ul', { key: 'items' }, [
                        el('li', { key: 'headline' }, 'headline: string'),
                        el('li', { key: 'image' }, 'image: MediaResource'),
                        el('li', { key: 'cta' }, 'primary_action_href: string')
                    ])
                ])
            ]),
            el('div', { key: 'stats', className: 'hero-stats' },
                stats.map(function (stat, index) {
                    return el('div', { key: index }, [
                        el('strong', { key: 'value' }, stat.value),
                        el('span', { key: 'label' }, stat.label)
                    ]);
                })
            )
        ]);
    }

    function wordvelPrinciplesPreview(props, blockProps) {
        var attrs = props.attributes;
        var items = [
            { title: 'item_1_title', body: 'item_1_body' },
            { title: 'item_2_title', body: 'item_2_body' },
            { title: 'item_3_title', body: 'item_3_body' }
        ];

        return el('section', blockProps, [
            el('div', { key: 'heading', className: 'section-heading' }, [
                wordvelInlineText('eyebrow', 'p', 'eyebrow', 'Eyebrow', props),
                wordvelInlineText('heading', 'h2', '', 'Heading', props)
            ]),
            el('div', { key: 'items', className: 'principle-list' },
                items.filter(function (item) {
                    return attrs[item.title] || attrs[item.body];
                }).map(function (item) {
                    return el('article', { key: item.title, className: 'principle' }, [
                        wordvelInlineText(item.title, 'h3', '', 'Title', props),
                        wordvelInlineText(item.body, 'p', '', 'Body', props)
                    ]);
                })
            )
        ]);
    }

    function wordvelFeatureGridPreview(props, blockProps) {
        var attrs = props.attributes;
        var rows = Array.isArray(attrs.features) ? attrs.features : [];

        return el('section', blockProps, [
            el('div', { key: 'heading', className: 'section-heading compact' }, [
                wordvelInlineText('eyebrow', 'p', 'eyebrow', 'Eyebrow', props),
                wordvelInlineText('heading', 'h2', '', 'Heading', props)
            ]),
            el('div', { key: 'features', className: 'feature-grid' },
                rows.map(function (row, index) {
                    return el('article', { key: index, className: 'feature' }, [
                        row.icon ? el('span', {
                            key: 'icon',
                            className: 'feature-icon'
                        }, row.icon) : null,
                        wordvelRepeaterPreviewChild({ key: 'features' }, { key: 'title', label: 'Title' }, row, index, props),
                        wordvelRepeaterPreviewChild({ key: 'features' }, { key: 'body', label: 'Body' }, row, index, props)
                    ]);
                })
            )
        ]);
    }

    function wordvelWorkflowPreview(props, blockProps) {
        var attrs = props.attributes;
        var steps = [
            { title: 'step_1_title', body: 'step_1_body' },
            { title: 'step_2_title', body: 'step_2_body' },
            { title: 'step_3_title', body: 'step_3_body' },
            { title: 'step_4_title', body: 'step_4_body' }
        ];

        return el('section', blockProps, [
            el('div', { key: 'heading', className: 'section-heading compact' }, [
                wordvelInlineText('eyebrow', 'p', 'eyebrow', 'Eyebrow', props),
                wordvelInlineText('heading', 'h2', '', 'Heading', props)
            ]),
            el('div', { key: 'steps', className: 'flow-list' },
                steps.filter(function (step) {
                    return attrs[step.title] || attrs[step.body];
                }).map(function (step, index) {
                    return el('article', { key: step.title, className: 'flow-step' }, [
                        el('span', { key: 'number' }, String(index + 1).padStart(2, '0')),
                        wordvelInlineText(step.title, 'h3', '', 'Title', props),
                        wordvelInlineText(step.body, 'p', '', 'Body', props)
                    ]);
                })
            )
        ]);
    }

    function wordvelApiPreview(props, blockProps) {
        return el('section', blockProps, [
            el('div', { key: 'copy' }, [
                wordvelInlineText('label', 'p', 'eyebrow', 'Label', props),
                wordvelInlineText('heading', 'h2', '', 'Heading', props),
                wordvelInlineText('body', 'p', '', 'Body', props)
            ]),
            el('pre', { key: 'code', 'aria-label': 'API payload example' },
                el(RichText, {
                    tagName: 'code',
                    placeholder: 'Code sample',
                    value: props.attributes.code || '',
                    allowedFormats: [],
                    onChange: function (nextValue) {
                        props.setAttributes({ code: nextValue });
                    }
                })
            )
        ]);
    }

    function wordvelInlineAction(labelKey, hrefKey, variant, placeholder, props) {
        return el('span', {
            key: labelKey,
            className: 'button ' + variant
        }, wordvelInlineText(labelKey, 'span', '', placeholder, props));
    }

    function wordvelInlineText(key, tagName, className, placeholder, props) {
        return el(RichText, {
            key: key,
            tagName: tagName,
            className: className || undefined,
            placeholder: placeholder,
            value: props.attributes[key] || '',
            allowedFormats: [],
            onChange: function (nextValue) {
                var next = {};
                next[key] = nextValue;
                props.setAttributes(next);
            }
        });
    }

    function wordvelTagName(field) {
        if (field.key === 'headline') {
            return 'h2';
        }

        return 'p';
    }

    function wordvelPreviewField(field, props) {
        var value = props.attributes[field.key] || '';

        if (field.type === 'repeater') {
            return wordvelRepeaterPreview(field, Array.isArray(value) ? value : [], props);
        }

        if (field.type === 'image' || field.type === 'media' || field.type === 'icon' || field.type === 'url') {
            return null;
        }

        return el(RichText, {
            key: field.key,
            tagName: wordvelTagName(field),
            placeholder: field.label,
            value: value,
            allowedFormats: [],
            onChange: function (nextValue) {
                var next = {};
                next[field.key] = nextValue;
                props.setAttributes(next);
            }
        });
    }

    function wordvelRepeaterPreview(field, rows, props) {
        return el('div', {
            key: field.key,
            className: 'wordvel-repeater-preview'
        }, [
            el('div', { key: 'heading', className: 'wordvel-repeater-preview-heading' }, [
                el('strong', { key: 'label' }, field.label),
                el('span', { key: 'count' }, String(rows.length) + ' items')
            ]),
            el('div', { key: 'items', className: 'wordvel-repeater-preview-items' },
                rows.map(function (row, index) {
                    return el('article', {
                        key: index,
                        className: 'wordvel-repeater-preview-item'
                    }, (field.fields || []).map(function (childField) {
                        return wordvelRepeaterPreviewChild(field, childField, row, index, props);
                    }));
                })
            )
        ]);
    }

    function wordvelRepeaterPreviewChild(parentField, childField, row, rowIndex, props) {
        var value = row[childField.key] || '';

        if (childField.type === 'icon') {
            return value ? el('span', {
                key: childField.key,
                className: 'wordvel-repeater-preview-icon'
            }, value) : null;
        }

        if (childField.type === 'image' || childField.type === 'media') {
            return null;
        }

        return el(RichText, {
            key: childField.key,
            tagName: childField.key === 'title' || childField.key === 'name' ? 'h3' : 'p',
            placeholder: childField.label,
            value: value,
            allowedFormats: [],
            onChange: function (nextValue) {
                var rows = Array.isArray(props.attributes[parentField.key])
                    ? props.attributes[parentField.key].slice()
                    : [];
                var nextRow = Object.assign({}, rows[rowIndex] || {});
                var next = {};

                nextRow[childField.key] = nextValue;
                rows[rowIndex] = nextRow;
                next[parentField.key] = rows;
                props.setAttributes(next);
            }
        });
    }

    function wordvelFieldControl(field, props) {
        var value = props.attributes[field.key] || '';
        var setValue = function (nextValue) {
            var next = {};
            next[field.key] = nextValue;
            props.setAttributes(next);
        };

        if (field.type === 'repeater') {
            return el(WordvelRepeaterControl, {
                key: field.key,
                field: field,
                value: Array.isArray(value) ? value : [],
                onChange: setValue
            });
        }

        return wordvelBasicFieldControl(field, value, setValue, field.key);
    }

    function wordvelBasicFieldControl(field, value, setValue, key) {
        if (field.type === 'textarea') {
            return el(TextareaControl, {
                key: key,
                label: field.label,
                value: value,
                onChange: setValue
            });
        }

        if (field.type === 'rich_text') {
            return el(TextareaControl, {
                key: key,
                label: field.label,
                value: value,
                onChange: setValue
            });
        }

        if (field.type === 'number') {
            return el(TextControl, {
                key: key,
                type: 'number',
                label: field.label,
                value: value,
                onChange: setValue
            });
        }

        if (field.type === 'boolean') {
            return el(ToggleControl, {
                key: key,
                label: field.label,
                checked: value === true || value === '1',
                onChange: function (checked) {
                    setValue(checked ? '1' : '');
                }
            });
        }

        if (field.type === 'select') {
            return el(SelectControl, {
                key: key,
                label: field.label,
                value: value,
                options: wordvelSelectOptions(field),
                onChange: setValue
            });
        }

        if (field.type === 'color') {
            return el('div', { key: key }, [
                el('p', { key: 'label' }, field.label),
                el(ColorPalette, {
                    key: 'palette',
                    value: value,
                    onChange: function (color) {
                        setValue(color || '');
                    }
                })
            ]);
        }

        if (field.type === 'image' || field.type === 'media') {
            return el(MediaUploadCheck, { key: key },
                el(MediaUpload, {
                    onSelect: function (media) {
                        setValue(String(media.id || ''));
                    },
                    allowedTypes: field.type === 'image' ? ['image'] : undefined,
                    value: value ? parseInt(value, 10) : undefined,
                    render: function (uploadProps) {
                        return el('div', {}, [
                            el('p', { key: 'label' }, field.label),
                            el(Button, {
                                key: 'button',
                                variant: 'secondary',
                                onClick: uploadProps.open
                            }, value ? 'Change media #' + value : 'Select media')
                        ]);
                    }
                })
            );
        }

        if (field.type === 'icon') {
            return el(WordvelIconControl, {
                key: key,
                field: field,
                value: value,
                onChange: setValue
            });
        }

        return el(TextControl, {
            key: key,
            type: field.type === 'url' ? 'url' : 'text',
            label: field.label,
            value: value,
            onChange: setValue
        });
    }

    function WordvelRepeaterControl(props) {
        var field = props.field;
        var rows = props.value || [];

        var updateRow = function (index, key, nextValue) {
            var nextRows = rows.slice();
            var nextRow = Object.assign({}, nextRows[index] || {});
            nextRow[key] = nextValue;
            nextRows[index] = nextRow;
            props.onChange(nextRows);
        };

        var addRow = function () {
            props.onChange(rows.concat([wordvelEmptyRepeaterRow(field)]));
        };

        var removeRow = function (index) {
            props.onChange(rows.filter(function (_, rowIndex) {
                return rowIndex !== index;
            }));
        };

        var moveRow = function (index, direction) {
            var nextIndex = index + direction;

            if (nextIndex < 0 || nextIndex >= rows.length) {
                return;
            }

            var nextRows = rows.slice();
            var row = nextRows[index];
            nextRows[index] = nextRows[nextIndex];
            nextRows[nextIndex] = row;
            props.onChange(nextRows);
        };

        return el('div', { className: 'wordvel-repeater-control' }, [
            el('div', { key: 'heading', className: 'wordvel-repeater-heading' }, [
                el('strong', { key: 'label' }, field.label),
                el(Button, {
                    key: 'add',
                    variant: 'secondary',
                    onClick: addRow
                }, 'Add item')
            ]),
            rows.map(function (row, index) {
                return el(PanelBody, {
                    key: index,
                    title: wordvelRepeaterRowTitle(field, row, index),
                    initialOpen: index === rows.length - 1
                }, [
                    (field.fields || []).map(function (childField) {
                        return wordvelBasicFieldControl(
                            childField,
                            row[childField.key] || '',
                            function (nextValue) {
                                updateRow(index, childField.key, nextValue);
                            },
                            field.key + '-' + index + '-' + childField.key
                        );
                    }),
                    el('div', { key: 'actions', className: 'wordvel-repeater-actions' }, [
                        el(Button, {
                            key: 'up',
                            variant: 'tertiary',
                            disabled: index === 0,
                            onClick: function () {
                                moveRow(index, -1);
                            }
                        }, 'Move up'),
                        el(Button, {
                            key: 'down',
                            variant: 'tertiary',
                            disabled: index === rows.length - 1,
                            onClick: function () {
                                moveRow(index, 1);
                            }
                        }, 'Move down'),
                        el(Button, {
                            key: 'remove',
                            variant: 'tertiary',
                            isDestructive: true,
                            onClick: function () {
                                removeRow(index);
                            }
                        }, 'Remove')
                    ])
                ]);
            })
        ]);
    }

    function WordvelIconControl(props) {
        var searchState = useState('');
        var search = searchState[0];
        var setSearch = searchState[1];
        var icons = (props.field.options && props.field.options.icons) || {};
        var options = Object.keys(icons)
            .filter(function (key) {
                var haystack = (key + ' ' + icons[key]).toLowerCase();
                return haystack.indexOf(search.toLowerCase()) !== -1;
            })
            .map(function (key) {
                return {
                    label: icons[key],
                    value: key
                };
            });

        return el('div', { className: 'wordvel-icon-control' }, [
            el(TextControl, {
                key: 'search',
                label: props.field.label + ' search',
                value: search,
                onChange: setSearch
            }),
            el(SelectControl, {
                key: 'select',
                label: props.field.label,
                value: props.value || '',
                options: [{ label: 'Select icon', value: '' }].concat(options),
                onChange: props.onChange
            }),
            props.value ? el('div', {
                key: 'preview',
                className: 'wordvel-icon-preview'
            }, [
                el('span', { key: 'mark' }, props.value),
                el('small', { key: 'provider' }, (props.field.options && props.field.options.provider) || 'icon')
            ]) : null
        ]);
    }

    function wordvelEmptyRepeaterRow(field) {
        var row = {};

        (field.fields || []).forEach(function (childField) {
            row[childField.key] = childField.type === 'repeater' ? [] : '';
        });

        return row;
    }

    function wordvelRepeaterRowTitle(field, row, index) {
        var titleField = (field.fields || []).find(function (childField) {
            return childField.key === 'title' || childField.key === 'name' || childField.type === 'text';
        });

        return titleField && row[titleField.key]
            ? row[titleField.key]
            : field.label + ' ' + String(index + 1);
    }

    function wordvelSelectOptions(field) {
        var options = field.options || {};

        return Object.keys(options).map(function (key) {
            return {
                label: options[key],
                value: key
            };
        });
    }
})(window.wp);
