(function wordvelBootEditorBlocks() {
    var wp = window.wp;

    if (!wp || !wp.blocks || !wp.element || !wp.components || !wp.blockEditor || !window.wordvelBlocks) {
        window.setTimeout(wordvelBootEditorBlocks, 50);
        return;
    }

    if (window.wordvelEditorBlocksBooted) {
        return;
    }

    window.wordvelEditorBlocksBooted = true;

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useEffect = wp.element.useEffect;
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

    wordvelRegisterEditorLanguagePanel();

    window.wordvelBlocks.forEach(function (schema) {
        var attributes = {};

        (schema.fields || []).forEach(function (field) {
            attributes[field.key] = {
                type: field.type === 'repeater'
                    ? 'array'
                    : field.type === 'image' || field.type === 'media' || field.type === 'localized_text'
                        ? 'object'
                        : 'string',
                default: field.type === 'repeater'
                    ? (field.default || [])
                    : field.type === 'image' || field.type === 'media'
                        ? (field.default || {})
                        : field.type === 'localized_text'
                            ? (field.default || { en: '', es: '' })
                        : (field.default || '')
            };
        });

        if (wp.blocks.getBlockType('wordvel/' + schema.key)) {
            wp.blocks.unregisterBlockType('wordvel/' + schema.key);
        }

        wp.blocks.registerBlockType('wordvel/' + schema.key, {
            title: schema.name,
            category: 'wordvel',
            icon: 'layout',
            attributes: attributes,
            edit: function (props) {
                try {
                if (false) {
                    return el('div', {
                        className: 'wordvel-block-debug'
                    }, schema.name);
                }
                var previewSlideState = useState('0');
                var previewSlideIndex = parseInt(previewSlideState[0], 10) || 0;
                var setPreviewSlideIndex = previewSlideState[1];
                var language = wordvelUseEditorLanguage();
                var mode = wordvelUseEditorMode();
                var generatedPreview = wordvelGeneratedPreview(schema, props, {
                    previewSlideIndex: previewSlideIndex,
                    mode: mode
                });
                var blockProps = useBlockProps({
                    className: 'wordvel-block wordvel-block-' + schema.key,
                    onClick: function (event) {
                        wordvelSelectBlock(props.clientId, event);
                    },
                    onFocus: function (event) {
                        wordvelSelectBlock(props.clientId, event);
                    }
                });

                return el(Fragment, {}, [
                    el(InspectorControls, { key: 'inspector' },
                        el(PanelBody, { title: schema.name, initialOpen: true }, [
                            schema.key === 'home-hero' ? el(SelectControl, {
                                key: 'preview-slide',
                                label: 'Preview slide',
                                value: String(previewSlideIndex),
                                options: ((props.attributes.slides || [])).map(function (slide, index) {
                                    return {
                                        label: wordvelLocalizedDisplayValue(slide && (slide.title || slide.eyebrow), language) || 'Slide ' + String(index + 1),
                                        value: String(index)
                                    };
                                }),
                                onChange: function (value) {
                                    setPreviewSlideIndex(value);
                                }
                            }) : null,
                            (schema.fields || []).map(function (field) {
                                return wordvelFieldControl(field, props, language, schema.fields || []);
                            })
                        ])
                    ),
                    generatedPreview.html
                        ? wordvelRenderGeneratedPreview(generatedPreview.html, props, schema, blockProps, {
                            previewSlideIndex: previewSlideIndex,
                            language: language,
                            mode: mode
                        })
                        : wordvelBlockPreview(schema, props, blockProps)
                ]);
                } catch (error) {
                    return el('pre', {
                        className: 'wordvel-editor-error'
                    }, error && error.stack ? error.stack : String(error));
                }
            },
            save: function () {
                return null;
            }
        });
    });

    function wordvelGeneratedPreview(schema, props, previewState) {
        var preview = window.wordvelEditorPreviews && window.wordvelEditorPreviews[schema.key];
        var html = preview && preview.html ? String(preview.html) : '';

        if (!html) {
            return { element: null, className: '' };
        }

        var container = document.createElement('div');
        container.innerHTML = html;
        var root = Array.prototype.find.call(container.children, function (child) {
            return ['link', 'meta', 'script', 'style'].indexOf(child.tagName.toLowerCase()) === -1;
        });
        root = wordvelPrepareGeneratedRoot(root, schema, props, previewState || {});
        var className = root ? root.getAttribute('class') || '' : '';

        return {
            html: root ? root.outerHTML : '',
            className: className
        };
    }

    function wordvelSelectBlock(clientId, event) {
        if (wordvelEventStartedInInlineEditor(event)) {
            return;
        }

        if (!clientId || !wp.data || typeof wp.data.dispatch !== 'function') {
            return;
        }

        var dispatcher = wp.data.dispatch('core/block-editor');

        if (dispatcher && typeof dispatcher.selectBlock === 'function') {
            dispatcher.selectBlock(clientId);
        }
    }

    function wordvelEventStartedInInlineEditor(event) {
        var target = event && event.target;

        return !!(target && target.closest && target.closest('.block-editor-rich-text__editable, [contenteditable="true"]'));
    }

    function wordvelRegisterEditorLanguagePanel() {
        if (window.wordvelEditorLanguagePanelBooted) {
            return;
        }

        if (!wp.plugins || !wp.editPost || !wp.editPost.PluginDocumentSettingPanel) {
            window.setTimeout(wordvelRegisterEditorLanguagePanel, 50);
            return;
        }

        window.wordvelEditorLanguagePanelBooted = true;

        wp.plugins.registerPlugin('wordvel-editor-language', {
            render: function () {
                var language = wordvelUseEditorLanguage();
                var mode = wordvelUseEditorMode();
                var options = wordvelAllLanguageOptions();
                var controls = [
                    el(SelectControl, {
                        key: 'mode',
                        label: 'Editor mode',
                        value: mode,
                        options: [
                            { label: 'Light', value: 'light' },
                            { label: 'Dark', value: 'dark' }
                        ],
                        onChange: wordvelSetEditorMode
                    })
                ];

                if (options.length > 1) {
                    controls.unshift(el(SelectControl, {
                        key: 'language',
                        label: 'Editor language',
                        value: language,
                        options: options,
                        onChange: wordvelSetEditorLanguage
                    }));
                }

                return el(wp.editPost.PluginDocumentSettingPanel, {
                    name: 'wordvel-editor-language',
                    title: 'WordVel',
                    className: 'wordvel-editor-language-panel'
                }, controls);
            }
        });
    }

    function wordvelUseEditorLanguage() {
        var languageState = useState(wordvelStoredEditorLanguage());
        var language = languageState[0];
        var setLanguage = languageState[1];

        useEffect(function () {
            function handleLanguageChange(event) {
                setLanguage((event && event.detail && event.detail.language) || wordvelStoredEditorLanguage());
            }

            window.addEventListener('wordvel-editor-language-change', handleLanguageChange);

            return function () {
                window.removeEventListener('wordvel-editor-language-change', handleLanguageChange);
            };
        }, []);

        return language;
    }

    function wordvelStoredEditorLanguage() {
        try {
            return window.localStorage.getItem('wordvel.editorLanguage') || window.wordvelEditorLanguage || 'en';
        } catch (error) {
            return window.wordvelEditorLanguage || 'en';
        }
    }

    function wordvelSetEditorLanguage(nextLanguage) {
        window.wordvelEditorLanguage = nextLanguage || 'en';

        try {
            window.localStorage.setItem('wordvel.editorLanguage', window.wordvelEditorLanguage);
        } catch (error) {
            // Local storage can be unavailable in locked-down browser contexts.
        }

        window.dispatchEvent(new CustomEvent('wordvel-editor-language-change', {
            detail: {
                language: window.wordvelEditorLanguage
            }
        }));
    }

    function wordvelUseEditorMode() {
        var modeState = useState(wordvelStoredEditorMode());
        var mode = modeState[0];
        var setMode = modeState[1];

        useEffect(function () {
            wordvelApplyEditorMode(mode);
        }, [mode]);

        useEffect(function () {
            function handleModeChange(event) {
                setMode((event && event.detail && event.detail.mode) || wordvelStoredEditorMode());
            }

            window.addEventListener('wordvel-editor-mode-change', handleModeChange);

            return function () {
                window.removeEventListener('wordvel-editor-mode-change', handleModeChange);
            };
        }, []);

        return mode;
    }

    function wordvelStoredEditorMode() {
        try {
            return window.localStorage.getItem('wordvel.editorMode') || window.wordvelEditorMode || 'light';
        } catch (error) {
            return window.wordvelEditorMode || 'light';
        }
    }

    function wordvelSetEditorMode(nextMode) {
        window.wordvelEditorMode = nextMode || 'light';
        wordvelApplyEditorMode(window.wordvelEditorMode);

        try {
            window.localStorage.setItem('wordvel.editorMode', window.wordvelEditorMode);
        } catch (error) {
            // Local storage can be unavailable in locked-down browser contexts.
        }

        window.dispatchEvent(new CustomEvent('wordvel-editor-mode-change', {
            detail: {
                mode: window.wordvelEditorMode
            }
        }));
    }

    function wordvelApplyEditorMode(mode) {
        Array.prototype.forEach.call(document.querySelectorAll('.editor-styles-wrapper'), function (wrapper) {
            wrapper.classList.toggle('wordvel-editor-mode-dark', mode === 'dark');
            wrapper.classList.toggle('wordvel-editor-mode-light', mode !== 'dark');
        });
    }

    function wordvelHydratePreviewHtml(html, props, schema, previewState) {
        var container = document.createElement('div');
        container.innerHTML = html.replace(/\{\{\s*([^}]+?)\s*\}\}/g, function (_, path) {
            return wordvelEscapeHtml(String(wordvelValueAtPath(props.attributes, path.trim(), schema, previewState.language) || ''));
        });

        wordvelPrepareGeneratedRoot(container.firstElementChild, schema, props, previewState || {});

        return container.innerHTML;
    }

    function wordvelEscapeHtml(value) {
        return value
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function wordvelRenderGeneratedPreview(html, props, schema, blockProps, previewState) {
        var container = document.createElement('div');
        container.innerHTML = html;
        var root = wordvelGeneratedPreviewRoot(container, schema);

        wordvelPrepareGeneratedRoot(root, schema, props, previewState || {});

        return el('div', blockProps, Array.prototype.map.call(container.childNodes, function (child, index) {
            return wordvelWithKey(wordvelRenderGeneratedNode(child, props, schema, false, previewState || {}), index);
        }).filter(function (child) {
            return child !== null && child !== false;
        }));
    }

    function wordvelGeneratedPreviewRoot(container, schema) {
        if (schema.key === 'home-hero') {
            return container.querySelector('.hero') || container.firstElementChild;
        }

        return container.firstElementChild;
    }

    function wordvelPrepareGeneratedRoot(root, schema, props, previewState) {
        wordvelPruneGeneratedRepeaters(root, props);

        if (!root || schema.key !== 'home-hero') {
            return root;
        }

        var activeIndex = previewState.previewSlideIndex || 0;

        Array.prototype.forEach.call(root.querySelectorAll('.hero-slide, .hero-copy-slide'), function (node, index) {
            var slideIndex = index % ((root.querySelectorAll('.hero-slide').length) || 1);
            node.classList.toggle('is-active', slideIndex === activeIndex);

            if (node.classList.contains('hero-copy-slide')) {
                node.setAttribute('aria-hidden', slideIndex === activeIndex ? 'false' : 'true');
            }
        });

        Array.prototype.forEach.call(root.querySelectorAll('.hero-dots button'), function (node, index) {
            node.classList.toggle('is-active', index === activeIndex);
        });

        var slides = Array.isArray(props.attributes.slides) ? props.attributes.slides : [];
        var activeSlide = slides[activeIndex] || {};

        var defaultTone = previewState.mode === 'dark' ? 'light' : 'dark';
        var activeTone = activeSlide.text_tone && activeSlide.text_tone !== 'auto'
            ? activeSlide.text_tone
            : defaultTone;

        root.classList.toggle('text-light', activeTone === 'light');
        root.classList.toggle('text-dark', activeTone !== 'light');

        Array.prototype.forEach.call(root.querySelectorAll('.hero-slide img'), function (image, index) {
            var slide = slides[index] || {};

            image.classList.toggle('is-contained', slide.image_mode === 'contain');
        });

        return root;
    }

    function wordvelPruneGeneratedRepeaters(root, props) {
        if (!root) {
            return;
        }

        Object.keys(props.attributes || {}).forEach(function (key) {
            var rows = props.attributes[key];

            if (!Array.isArray(rows)) {
                return;
            }

            Array.prototype.forEach.call(root.querySelectorAll('*'), function (node) {
                var paths = wordvelPlaceholderPathsInNode(node);
                var indexes = paths.map(function (path) {
                    var match = path.match(new RegExp('^' + key + '\\.(\\d+)\\.'));

                    return match ? parseInt(match[1], 10) : null;
                }).filter(function (index) {
                    return index !== null;
                });

                if (indexes.length > 0 && indexes.every(function (index) {
                    return index >= rows.length || wordvelRepeaterRowBlank(rows[index]);
                })) {
                    node.remove();
                }
            });
        });
    }

    function wordvelRepeaterRowBlank(row) {
        if (!row || typeof row !== 'object') {
            return true;
        }

        return Object.keys(row).every(function (key) {
            var value = row[key];

            if (value && typeof value === 'object') {
                return wordvelRepeaterRowBlank(value);
            }

            return wordvelBlankPreviewValue(value);
        });
    }

    function wordvelRenderGeneratedNode(node, props, schema, omitWrapper, previewState) {
        if (node.nodeType === Node.TEXT_NODE) {
            return wordvelRenderGeneratedText(node.textContent || '', props, schema, previewState);
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return null;
        }

        if (wordvelShouldSkipGeneratedNode(node, props)) {
            return null;
        }

        var tagName = node.tagName.toLowerCase() === 'a' ? 'span' : node.tagName.toLowerCase();
        var elementProps = wordvelGeneratedElementProps(node, props, schema, previewState);
        var onlyPlaceholder = wordvelOnlyPlaceholder(node);

        if (wordvelVoidElement(tagName)) {
            return el(tagName, elementProps);
        }

        if (onlyPlaceholder) {
            var localizedPath = wordvelLocalizedPath(schema, onlyPlaceholder, previewState.language);

            if (wordvelCanInlineEditPath(schema, localizedPath)) {
                return wordvelInlineRichTextForPath(localizedPath, tagName, elementProps, props, schema);
            }

            return el(tagName, elementProps, String(wordvelValueAtPath(props.attributes, onlyPlaceholder, schema, previewState.language) || ''));
        }

        var children = Array.prototype.map.call(node.childNodes, function (child, index) {
            return wordvelWithKey(wordvelRenderGeneratedNode(child, props, schema, false, previewState), index);
        }).filter(function (child) {
            return child !== null && child !== false;
        });

        if (omitWrapper) {
            return children;
        }

        return el(tagName, elementProps, children);
    }

    function wordvelVoidElement(tagName) {
        return [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr'
        ].indexOf(tagName) !== -1;
    }

    function wordvelRenderGeneratedText(text, props, schema, previewState) {
        var parts = [];
        var pattern = /\{\{\s*([^}]+?)\s*\}\}/g;
        var lastIndex = 0;
        var match;

        while ((match = pattern.exec(text)) !== null) {
            if (match.index > lastIndex) {
                parts.push(text.slice(lastIndex, match.index));
            }

            parts.push(String(wordvelValueAtPath(props.attributes, match[1].trim(), schema, previewState.language) || ''));

            lastIndex = pattern.lastIndex;
        }

        if (lastIndex < text.length) {
            parts.push(text.slice(lastIndex));
        }

        if (parts.length === 0) {
            return text;
        }

        if (parts.length === 1) {
            return parts[0];
        }

        return parts.map(wordvelWithKey);
    }

    function wordvelInlineRichTextForPath(path, tagName, elementProps, props, schema) {
        var field = wordvelFieldForPath(schema, path);
        var parentField = wordvelParentLocalizedTextFieldForPath(schema, path);
        var value = wordvelValueAtPath(props.attributes, path, schema);
        var nextProps = Object.assign({}, elementProps, {
            key: path,
            tagName: tagName,
            placeholder: (parentField && parentField.label) || (field && field.label) || path,
            value: value == null ? '' : String(value),
            allowedFormats: field && field.type === 'rich_text' ? undefined : [],
            onChange: function (nextValue) {
                props.setAttributes(wordvelSetValueAtPath(props.attributes, path, nextValue));
            }
        });

        return el(RichText, nextProps);
    }

    function wordvelLocalizedDisplayValue(value, language) {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            return value[language || 'en'] || value.en || value.es || '';
        }

        return value == null ? '' : String(value);
    }

    function wordvelParentLocalizedTextFieldForPath(schema, path) {
        var match = path.match(/^(.*)\.(en|es)$/);

        if (!match) {
            return null;
        }

        var parent = wordvelFieldForPath(schema, match[1]);

        return parent && parent.type === 'localized_text' ? parent : null;
    }

    function wordvelValueAtPath(source, path, schema, language) {
        if (schema && language) {
            path = wordvelLocalizedPath(schema, path, language);
        }

        var fallbackValue;

        if (path.endsWith('.url')) {
            fallbackValue = wordvelValueAtPath(source, path.slice(0, -4));

            if (typeof fallbackValue === 'string') {
                return fallbackValue;
            }
        }

        if (path.endsWith('.alt')) {
            fallbackValue = wordvelValueAtPath(source, path.slice(0, -4));

            if (fallbackValue && typeof fallbackValue === 'object') {
                return fallbackValue.alt || '';
            }
        }

        return path.split('.').reduce(function (value, segment) {
            if (value == null) {
                return null;
            }

            return value[segment];
        }, source);
    }

    function wordvelSetValueAtPath(source, path, nextValue) {
        var segments = path.split('.').filter(Boolean).map(function (segment) {
            return /^\d+$/.test(segment) ? parseInt(segment, 10) : segment;
        });

        if (!segments.length) {
            return {};
        }

        var root = Object.assign({}, source);
        var cursor = root;
        var sourceCursor = source;

        segments.forEach(function (segment, index) {
            var isLast = index === segments.length - 1;
            var nextSegment = segments[index + 1];

            if (isLast) {
                cursor[segment] = nextValue;
                return;
            }

            var existing = sourceCursor && sourceCursor[segment];
            var cloned = Array.isArray(existing)
                ? existing.slice()
                : existing && typeof existing === 'object'
                    ? Object.assign({}, existing)
                    : typeof nextSegment === 'number'
                        ? []
                        : {};

            cursor[segment] = cloned;
            cursor = cloned;
            sourceCursor = existing;
        });

        return root;
    }

    function wordvelGeneratedElementProps(node, blockProps, schema, previewState) {
        var elementProps = {};

        Array.prototype.forEach.call(node.attributes || [], function (attribute) {
            if (/^on/i.test(attribute.name)) {
                return;
            }

            if (attribute.name === 'style') {
                return;
            }

            if (attribute.name === 'class') {
                elementProps.className = wordvelBindAttributeValue(attribute.value, blockProps, schema, previewState);
                return;
            }

            if (attribute.name === 'for') {
                elementProps.htmlFor = wordvelBindAttributeValue(attribute.value, blockProps, schema, previewState);
                return;
            }

            if (node.tagName.toLowerCase() === 'a' && attribute.name === 'href') {
                return;
            }

            if (attribute.name === 'tabindex') {
                elementProps.tabIndex = wordvelBindAttributeValue(attribute.value, blockProps, schema, previewState);
                return;
            }

            if (attribute.name === 'readonly') {
                elementProps.readOnly = true;
                return;
            }

            elementProps[attribute.name] = wordvelBindAttributeValue(attribute.value, blockProps, schema, previewState);
        });

        return elementProps;
    }

    function wordvelBindAttributeValue(value, blockProps, schema, previewState) {
        return String(value).replace(/\{\{\s*([^}]+?)\s*\}\}/g, function (_, path) {
            var nextValue = wordvelValueAtPath(blockProps.attributes, path.trim(), schema, previewState.language);

            return nextValue == null ? '' : String(nextValue);
        });
    }

    function wordvelOnlyPlaceholder(node) {
        if (node.childNodes.length !== 1 || node.firstChild.nodeType !== Node.TEXT_NODE) {
            return null;
        }

        var match = (node.textContent || '').match(/^\s*\{\{\s*([^}]+?)\s*\}\}\s*$/);

        return match ? match[1].trim() : null;
    }

    function wordvelShouldSkipGeneratedNode(node, props) {
        var paths = wordvelPlaceholderPathsInNode(node);

        if (paths.length > 0 && paths.every(function (path) {
            var value = wordvelValueAtPath(props.attributes, path);

            return wordvelBlankPreviewValue(value);
        })) {
            return true;
        }

        return paths.some(function (path) {
            var segments = path.split('.');

            for (var index = 0; index < segments.length - 1; index += 1) {
                if (!/^\d+$/.test(segments[index + 1])) {
                    continue;
                }

                var rows = wordvelValueAtPath(props.attributes, segments.slice(0, index + 1).join('.'));

                if (Array.isArray(rows) && parseInt(segments[index + 1], 10) >= rows.length) {
                    return true;
                }
            }

            return false;
        });
    }

    function wordvelBlankPreviewValue(value) {
        if (value == null) {
            return true;
        }

        if (typeof value === 'string') {
            var trimmed = value.replace(/\uFEFF/g, '').trim();

            return trimmed === '' || /^\{\{\s*[^}]+?\s*\}\}$/.test(trimmed);
        }

        return false;
    }

    function wordvelPlaceholderPathsInNode(node) {
        var values = [node.textContent || ''];

        Array.prototype.forEach.call(node.attributes || [], function (attribute) {
            values.push(attribute.value || '');
        });

        return values.flatMap(function (value) {
            return Array.prototype.map.call(value.matchAll(/\{\{\s*([^}]+?)\s*\}\}/g), function (match) {
                return match[1].trim();
            });
        });
    }

    function wordvelCanInlineEditPath(schema, path) {
        var field = wordvelFieldForPath(schema, path);

        return !!field && ['text', 'textarea', 'rich_text', 'url'].indexOf(field.type) !== -1;
    }

    function wordvelLanguageOptions(schema) {
        return wordvelHasLanguage(schema, 'es')
            ? [
                { label: 'English', value: 'en' },
                { label: 'Spanish', value: 'es' }
            ]
            : [{ label: 'English', value: 'en' }];
    }

    function wordvelAllLanguageOptions() {
        return wordvelHasLanguage({ fields: wordvelAllEditorFields() }, 'es')
            ? [
                { label: 'English', value: 'en' },
                { label: 'Spanish', value: 'es' }
            ]
            : [{ label: 'English', value: 'en' }];
    }

    function wordvelAllEditorFields() {
        return (window.wordvelBlocks || []).reduce(function (fields, schema) {
            return fields.concat(schema.fields || []);
        }, []);
    }

    function wordvelHasLanguage(schema, language) {
        return wordvelFlattenFields(schema.fields || []).some(function (field) {
            return field.key.endsWith('_' + language) || (field.type === 'localized_text' && (field.fields || []).some(function (child) {
                return child.key === language;
            }));
        });
    }

    function wordvelFlattenFields(fields) {
        return fields.reduce(function (all, field) {
            return all.concat([field], wordvelFlattenFields(field.fields || []));
        }, []);
    }

    function wordvelLocalizedPath(schema, path, language) {
        if (!language || language === 'en' || path.endsWith('.url') || path.endsWith('.alt')) {
            var englishField = wordvelFieldForPath(schema, path);

            return englishField && englishField.type === 'localized_text' ? path + '.en' : path;
        }

        var segments = path.split('.');
        var lastIndex = segments.length - 1;
        var baseField = wordvelFieldForPath(schema, path);

        if (baseField && baseField.type === 'localized_text') {
            return path + '.' + language;
        }

        var field = wordvelFieldForPath(schema, path + '_' + language);

        if (field) {
            segments[lastIndex] = segments[lastIndex] + '_' + language;
        }

        return segments.join('.');
    }

    function wordvelIsLanguageVariant(field) {
        return /_[a-z]{2}$/.test(field.key);
    }

    function wordvelActiveField(field, language, siblings) {
        if (field.type === 'localized_text') {
            var localizedKey = language && language !== 'en' ? language : 'en';
            var localizedField = (field.fields || []).find(function (candidate) {
                return candidate.key === localizedKey;
            }) || (field.fields || [])[0] || field;

            return Object.assign({}, localizedField, {
                key: field.key + '.' + localizedField.key,
                label: field.label
            });
        }

        if (wordvelIsLanguageVariant(field)) {
            return null;
        }

        if (!language || language === 'en') {
            return field;
        }

        var localizedKey = field.key + '_' + language;
        var localized = (siblings || []).find(function (candidate) {
            return candidate.key === localizedKey;
        });

        return localized ? Object.assign({}, localized, {
            key: localized.key,
            label: field.label
        }) : field;
    }

    function wordvelFieldForPath(schema, path) {
        var segments = path.split('.').filter(Boolean);
        var fields = schema.fields || [];
        var field = null;

        for (var index = 0; index < segments.length; index += 1) {
            if (/^\d+$/.test(segments[index])) {
                continue;
            }

            field = fields.find(function (candidate) {
                return candidate.key === segments[index];
            });

            if (!field) {
                return null;
            }

            fields = field.fields || [];
        }

        return field;
    }

    function wordvelWithKey(value, index) {
        if (Array.isArray(value)) {
            return value.map(function (child, childIndex) {
                return wordvelWithKey(child, String(index) + '-' + String(childIndex));
            });
        }

        if (value && typeof value === 'object' && value.props && value.key == null) {
            return wp.element.cloneElement(value, { key: index });
        }

        return value;
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

        if (field.type === 'image' || field.type === 'media') {
            return value && value.url ? el('img', {
                key: field.key,
                src: value.url,
                alt: value.alt || ''
            }) : null;
        }

        if (field.type === 'icon' || field.type === 'url') {
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
            return value && value.url ? el('img', {
                key: childField.key,
                src: value.url,
                alt: value.alt || ''
            }) : null;
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

    function wordvelFieldControl(field, props, language, siblings) {
        var activeField = wordvelActiveField(field, language, siblings || []);

        if (!activeField) {
            return null;
        }

        var value = wordvelValueAtPath(props.attributes, activeField.key) || '';
        var setValue = function (nextValue) {
            props.setAttributes(wordvelSetValueAtPath(props.attributes, activeField.key, nextValue));
        };

        if (activeField.type === 'repeater') {
            return el(WordvelRepeaterControl, {
                key: activeField.key,
                field: activeField,
                language: language,
                value: Array.isArray(value) ? value : [],
                onChange: setValue
            });
        }

        return wordvelBasicFieldControl(activeField, value, setValue, activeField.key);
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
                        setValue(wordvelMediaValue(media));
                    },
                    allowedTypes: field.type === 'image' ? ['image'] : undefined,
                    value: value && value.id ? parseInt(value.id, 10) : undefined,
                    render: function (uploadProps) {
                        return el('div', { className: 'wordvel-media-control' }, [
                            el('p', { key: 'label', className: 'wordvel-media-label' }, field.label),
                            value && value.url ? el('img', {
                                key: 'preview',
                                src: value.url,
                                alt: value.alt || '',
                                className: 'wordvel-media-preview'
                            }) : null,
                            el(Button, {
                                key: 'button',
                                className: 'wordvel-media-button',
                                variant: 'secondary',
                                onClick: uploadProps.open
                            }, value && value.id ? 'Change image' : 'Select image')
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
        var language = props.language || 'en';
        var rows = props.value || [];

        var updateRow = function (index, key, nextValue) {
            var nextRows = rows.slice();
            var nextRow = Object.assign({}, nextRows[index] || {});
            nextRow = wordvelSetValueAtPath(nextRow, key, nextValue);
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
                    className: 'wordvel-repeater-add-button',
                    label: 'Add ' + field.label + ' item',
                    variant: 'tertiary',
                    onClick: addRow
                }, '+')
            ]),
            rows.length > 0 ? el(SelectControl, {
                key: 'jump',
                label: 'Edit item',
                value: '0',
                options: rows.map(function (row, index) {
                    return {
                        label: wordvelRepeaterRowTitle(field, row, index, language),
                        value: String(index)
                    };
                }),
                onChange: function (value) {
                    var panel = document.querySelector('[data-wordvel-repeater-panel="' + field.key + '-' + value + '"]');

                    if (panel) {
                        panel.scrollIntoView({
                            block: 'center',
                            behavior: 'smooth'
                        });
                    }
                }
            }) : null,
            rows.map(function (row, index) {
                return el(PanelBody, {
                    key: index,
                    title: wordvelRepeaterRowTitle(field, row, index, language),
                    initialOpen: index === rows.length - 1
                }, [
                    el('div', {
                        key: 'anchor',
                        'data-wordvel-repeater-panel': field.key + '-' + index
                    }),
                    (field.fields || []).map(function (childField) {
                        var activeChildField = wordvelActiveField(childField, language, field.fields || []);

                        if (!activeChildField) {
                            return null;
                        }

                        return wordvelBasicFieldControl(
                            activeChildField,
                            wordvelValueAtPath(row, activeChildField.key) || '',
                            function (nextValue) {
                                updateRow(index, activeChildField.key, nextValue);
                            },
                            field.key + '-' + index + '-' + activeChildField.key
                        );
                    }).filter(Boolean),
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
            row[childField.key] = childField.type === 'repeater'
                ? []
                : childField.type === 'image' || childField.type === 'media'
                    ? {}
                    : childField.type === 'localized_text'
                        ? { en: '', es: '' }
                    : '';
        });

        return row;
    }

    function wordvelMediaValue(media) {
        return {
            id: media.id || '',
            url: media.url || media.source_url || '',
            alt: media.alt || media.alt_text || '',
            width: media.width || '',
            height: media.height || ''
        };
    }

    function wordvelRepeaterRowTitle(field, row, index, language) {
        var titleField = (field.fields || []).find(function (childField) {
            return childField.key === 'title' || childField.key === 'name' || childField.type === 'localized_text' || childField.type === 'text';
        });
        var value = titleField ? row[titleField.key] : null;
        value = wordvelLocalizedDisplayValue(value, language);

        return titleField && value
            ? value
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
})();
