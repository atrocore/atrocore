<script>
    import * as monaco from 'monaco-editor';

    const jsonTwigLanguageConfig = {
        defaultToken: '',
        tokenizer: {
            root: [
                // Twig variable output {{ var }}
                [/{{/, {token: 'twig.delimiter', next: '@twigExpression'}],
                // Twig statements {% if/for/set %}
                [/{%/, {token: 'twig.delimiter', next: '@twigStatement'}],
                // JSON tokens
                [/[{}]/, 'delimiter.bracket'],
                [/[[\]]/, 'delimiter.square'],
                [/:/, 'delimiter'],
                [/,/, 'delimiter'],
                [/"([^"\\]|\\.)*"/, 'string'],
                [/true|false|null/, 'keyword'],
                [/-?\d+\.?\d*([eE][+-]?\d+)?/, 'number']
            ],

            twigExpression: [
                [/}}/, {token: 'twig.delimiter', next: '@pop'}],
                [/[a-zA-Z_]\w*/, 'twig.variable'],
                [/\./, 'twig.operator'],
                [/\|/, 'twig.operator', '@twigFilter'],
                [/"([^"\\]|\\.)*"/, 'twig.string'],
                [/'([^'\\]|\\.)*'/, 'twig.string']
            ],

            twigStatement: [
                [/%}/, {token: 'twig.delimiter', next: '@pop'}],
                [/\s*(if|else|elseif|endif|for|endfor|set)\s*/, 'twig.keyword'],
                [/[a-zA-Z_]\w*/, 'twig.variable'],
                [/==|!=|<|>|<=|>=/, 'twig.operator'],
                [/and|or|not|in|is/, 'twig.operator'],
                [/"([^"\\]|\\.)*"/, 'twig.string'],
                [/'([^'\\]|\\.)*'/, 'twig.string'],
                [/\d+/, 'twig.number']
            ],

            twigFilter: [
                [/[a-zA-Z_]\w*/, 'twig.filter'],
                [/\(/, {token: 'twig.delimiter', next: '@twigFilterParams'}],
                [/\|/, 'twig.operator', '@twigFilter'],
                [/}}/, {token: 'twig.delimiter', next: '@pop'}]
            ],

            twigFilterParams: [
                [/\)/, {token: 'twig.delimiter', next: '@pop'}],
                [/,/, 'twig.delimiter'],
                [/"([^"\\]|\\.)*"/, 'twig.string'],
                [/'([^'\\]|\\.)*'/, 'twig.string'],
                [/\d+/, 'twig.number']
            ]
        }
    };


    import {onMount} from 'svelte';
    import Text from './Text.svelte';
    import {Metadata} from "../../utils/Metadata";
    import {Language} from "../../utils/Language";

    export let mode = 'detail';
    export let name = '';
    export let scope;
    export let params = {};
    export let value = null;

    let language = params.language || Metadata.get(['entityDefs', scope, 'fields', name, 'language']) || 'twig';
    let twigVariables = params.twigVariables || Metadata.get(['entityDefs', scope, 'fields', name, 'twigVariables']) || [];
    let editorComponent;
    let containerElement;
    let rootElement;
    let height = 200;
    let fullScreen = false;
    let readOnly = mode !== 'edit';

    params = {
        ...params,
        seeMoreDisabled: true
    };

    $: {
        const lines = (value ?? '').split('\n').length;
        if (fullScreen) {
            height = "100vh"
        } else {
            if (lines <= 3) {
                height = "60px";
            } else if (lines <= 40) {
                height = (lines * 20) + "px";
            } else {
                height = "800px"
            }
        }
    }

    function registerTwigForLanguage(languageId) {
        let newLanguageId = languageId
        if (languageId === 'json') {
            newLanguageId = `${languageId}-twig`;
            monaco.languages.register({id: newLanguageId});
            monaco.languages.setLanguageConfiguration(newLanguageId, {
                // Characters that should auto close
                autoClosingPairs: [
                    {open: '{', close: '}'},
                    {open: '[', close: ']'},
                    {open: '(', close: ')'},
                    {open: '"', close: '"'},
                    {open: "'", close: "'"},
                    {open: '{{', close: '}}'},
                    {open: '{%', close: '%}'},
                    {open: '{#', close: '#}'}
                ],

                // Characters that should surround a selection
                surroundingPairs: [
                    {open: '{', close: '}'},
                    {open: '[', close: ']'},
                    {open: '(', close: ')'},
                    {open: '"', close: '"'},
                    {open: "'", close: "'"},
                    {open: '{{', close: '}}'},
                    {open: '{%', close: '%}'},
                    {open: '{#', close: '#}'}
                ],

                // Brackets definition for bracket matching
                brackets: [
                    ['{', '}'],
                    ['[', ']'],
                    ['(', ')'],
                    ['{{', '}}'],
                    ['{%', '%}'],
                    ['{#', '#}']
                ],

                // Characters that are used as word separators
                wordPattern: /(-?\d*\.\d\w*)|([^\`\~\!\@\#\$\%\^\&\*\(\)\=\+\[\{\]\}\\\|\;\:\'\"\,\.\<\>\/\?\s]+)/g,

                // Comment configuration
                comments: {
                    blockComment: ['{#', '#}']
                },

                // Auto indentation rules
                onEnterRules: [
                    {
                        beforeText: /^\s*\{\%\s*(if|for|block|verbatim|filter|spaceless|with|trans|autoescape|embed|macro)\s*.*\%\}$/,
                        afterText: /^\s*\{\%\s*end\1\s*\%\}$/,
                        action: {
                            indentAction: monaco.languages.IndentAction.IndentOutdent,
                            appendText: '    '
                        }
                    }
                ],

                // Folding rules
                folding: {
                    markers: {
                        start: new RegExp('^\\s*{%\\s*(if|for|block|verbatim|filter|spaceless|with|trans|autoescape|embed|macro)\\s.*%}'),
                        end: new RegExp('^\\s*{%\\s*end\\w+\\s*%}')
                    }
                }
            });
            // Set up tokens provider
            monaco.languages.setMonarchTokensProvider(newLanguageId, jsonTwigLanguageConfig);
        }

        if (window.monacoProviderRegistered == null || !window.monacoProviderRegistered.includes(newLanguageId)) {
            monaco.languages.registerCompletionItemProvider(newLanguageId, {
                provideCompletionItems: function (model, position) {
                    const editor = monaco.editor.getEditors().find(editor => editor.getModel() === model);
                    const options = editor?.getRawOptions() ?? {};
                    const params = options.params ?? {};
                    const twigVariables = options.twigVariables ?? [];
                    const name = options.name;
                    const scope = options.scope;

                    if (model.getLanguageId() === 'css') {
                        return {suggestions: []}
                    }
                    let order = 100;

                    const wordUntilPosition = model.getWordUntilPosition(position);
                    const textUntilPosition = model.getValueInRange({
                        startLineNumber: position.lineNumber,
                        startColumn: 1,
                        endLineNumber: position.lineNumber,
                        endColumn: position.column
                    });
                    const isInTwigBlock = /{%[\s\S]*$/.test(textUntilPosition);
                    const isInTwigExpression = /{{[\s\S]*$/.test(textUntilPosition);
                    const isAfterPipe = /\|[\s]*[\w]*$/.test(textUntilPosition);

                    let range = {
                        startLineNumber: position.lineNumber,
                        endLineNumber: position.lineNumber,
                        startColumn: wordUntilPosition.startColumn,
                        endColumn: wordUntilPosition.endColumn
                    };

                    if (isAfterPipe) {
                        const twigFilters = [
                            {
                                label: 'upper',
                                kind: monaco.languages.CompletionItemKind.Function,
                                detail: 'Filter',
                                documentation: 'Convert a value to uppercase',
                                insertText: 'upper',
                            },
                            {
                                label: 'lower',
                                kind: monaco.languages.CompletionItemKind.Function,
                                detail: 'Filter',
                                documentation: 'Convert a value to lowercase',
                                insertText: 'lower',
                            },
                            {
                                label: 'capitalize',
                                kind: monaco.languages.CompletionItemKind.Function,
                                detail: 'Filter',
                                documentation: 'Capitalize a value',
                                insertText: 'capitalize',
                            },
                            {
                                label: 'date',
                                kind: monaco.languages.CompletionItemKind.Function,
                                detail: 'Filter',
                                documentation: 'Format a date',
                                insertText: 'date(\'${1:Y-m-d}\')',
                                insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet
                            },
                            {
                                label: 'default',
                                kind: monaco.languages.CompletionItemKind.Function,
                                detail: 'Filter',
                                documentation: 'Returns the default value if the tested value is empty',
                                insertText: 'default(${1:default_value})',
                                insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet
                            },
                            {
                                label: 'json_encode',
                                kind: monaco.languages.CompletionItemKind.Function,
                                detail: 'Filter',
                                documentation: 'Returns the JSON representation of a value',
                                insertText: 'json_encode',
                            },
                            {
                                label: 'length',
                                kind: monaco.languages.CompletionItemKind.Function,
                                detail: 'Filter',
                                documentation: 'Returns the length of a string or array',
                                insertText: 'length',
                            },
                            {
                                label: 'trim',
                                kind: monaco.languages.CompletionItemKind.Function,
                                detail: 'Filter',
                                documentation: 'Strips whitespace from the beginning and end of a string',
                                insertText: 'trim',
                            }
                        ];

                        let filters = Metadata.get(['twig', 'filters']) ?? {};
                        if (params.isExport) {
                            filters = {...filters, ...(Metadata.get(['app', 'twigFilters']))}
                        }

                        let filterTranslations = Language.get('Global', 'twig', 'filters') ?? {};

                        Object.keys(filters).forEach(key => {
                            const filter = filters[key]

                            twigFilters.push({
                                label: key,
                                kind: monaco.languages.CompletionItemKind.Function,
                                insertText: filter.insertText ?? key,
                                insertTextRules: filter.insertText ? monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet : monaco.languages.CompletionItemInsertTextRule.None,
                                detail: "Filter",
                                documentation: filterTranslations[key]
                            })
                        })

                        return {
                            suggestions: twigFilters.map(tf => ({range, ...tf}))
                        }
                    }

                    if (!isInTwigBlock && !isInTwigExpression) {
                        return {suggestions: []}
                    }

                    let variableTranslations = Language.get(scope, 'twigVariables', name) ?? {}
                    // Entity variable suggestions
                    let suggestions = [
                        ...twigVariables,
                        'config',
                    ].map(variable => ({
                        name: variable,
                        description: variableTranslations[variable] ?? Language.get('Global', 'twigVariables', variable)
                    })).sort((v1, v2) => {
                        return v1.name.localeCompare(v2.name);
                    }).map(variable => {
                        return {
                            label: variable.name,
                            kind: monaco.languages.CompletionItemKind.Variable,
                            insertText: variable.name,
                            documentation: variable.description,
                            detail: 'Variable',
                            sortText: (order++) + ''
                        };
                    });

                    let functions = Metadata.get(['twig', 'functions']) ?? {};
                    if (params.isExport) {
                        functions = {...functions, ...(Metadata.get(['app', 'twigFunctions']))}
                    }

                    let functionTranslations = Language.get('Global', 'twig', 'functions') ?? {};

                    Object.keys(functions).sort((v1, v2) => {
                        return v1.localeCompare(v2);
                    }).forEach(key => {
                        const fn = functions[key]
                        suggestions.push({
                            label: key,
                            kind: monaco.languages.CompletionItemKind.Function,
                            insertText: fn.insertText ?? (key + '($0)'),
                            insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                            detail: 'Function',
                            documentation: functionTranslations[key],
                            sortText: (order++) + ''
                        })
                    })


                    suggestions.forEach(suggestion => {
                        suggestion.range = range;
                    })

                    // Snippet suggestions
                    const snippetSuggestions = [
                        {
                            label: 'set',
                            kind: monaco.languages.CompletionItemKind.Snippet,
                            insertText: 'set ${1:var} = $0 %}',
                            insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                            documentation: 'Set a variable'
                        },
                        {
                            label: 'block',
                            kind: monaco.languages.CompletionItemKind.Snippet,
                            insertText: 'block ${1:name} %}\n\t$0\n{% endblock %}',
                            insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                            documentation: 'Create a new block'
                        },
                        {
                            label: 'for',
                            kind: monaco.languages.CompletionItemKind.Snippet,
                            insertText: 'for ${1:item} in ${2:items} %}\n\t$0\n{% endfor %}',
                            insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                            documentation: 'Create a for loop'
                        },
                        {
                            label: 'if',
                            kind: monaco.languages.CompletionItemKind.Snippet,
                            insertText: 'if ${1:condition} %}\n\t$0\n{% endif %}',
                            insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                            documentation: 'Create an if statement'
                        }
                    ];

                    snippetSuggestions.forEach(suggestion => {
                        if (/{%\s*$/.test(textUntilPosition)) {
                            range = {
                                startLineNumber: position.lineNumber,
                                endLineNumber: position.lineNumber,
                                startColumn: wordUntilPosition.startColumn,
                                endColumn: model.getLineMaxColumn(position.lineNumber)
                            };
                            suggestions.push({range, ...suggestion})
                        }
                    })
                    return {
                        suggestions
                    };
                }
            });

            if (window.monacoProviderRegistered == null) {
                monaco.editor.defineTheme('twig', {
                    base: 'vs',
                    inherit: true,
                    rules: [
                        {token: 'twig.keyword', foreground: '0000FF', fontStyle: 'bold'},
                        {token: 'twig.variable', foreground: '267F99'},
                        {token: 'twig.operator', foreground: '000000'},
                        {token: 'twig.string', foreground: 'A31515'},
                        {token: 'twig.delimiter', foreground: '0000FF'},
                        {token: 'twig.filter', foreground: '267F99', fontStyle: 'italic'},
                        {token: 'twig.number', foreground: '098658'}
                    ],
                    colors: {
                        'editor.background': '#FFFFFE',
                        'editor.foreground': '#000000',
                        'editor.inactiveSelectionBackground': '#E5EBF1',
                        'editorIndentGuide.background1': '#D3D3D3',
                        'editorIndentGuide.activeBackground1': '#939393',
                        'editor.selectionHighlightBackground': '#ADD6FF4D'
                        // 'editor.selectionBackground': '#b3d4fc',
                        // 'editor.lineHighlightBackground': '#f7f7f7',
                        // 'editorCursor.foreground': '#000000',
                        // 'editorWhitespace.foreground': '#d3d3d3'
                    }
                });
                window.monacoProviderRegistered = []
            }
            window.monacoProviderRegistered.push(newLanguageId);
        }
        return newLanguageId;
    }

    export function fetch() {
        return {[name]: value};
    }

    function initFullScreenIcon(cell, editorComponent) {
        if (!cell) {
            return
        }
        cell.querySelectorAll('.fa-expand').forEach(el => el.parentElement.remove());

        const link = document.createElement('a');
        link.href = 'javascript:';
        link.className = 'pull-right fullscreen hidden';
        link.title = Language.translate('fullscreen', 'labels');
        link.innerHTML = '<span class="fas fa-expand fa-sm"></span>';

        let container = cell.querySelector(':scope > .status-container');
        if (!container) {
            container = cell
        }
        container.prepend(link)

        link.addEventListener('click', () => {
            editorComponent.getAction('fullscreen').run()
        });

        function handleMouseEnter(e) {
            e.stopPropagation();
            link.classList.remove('hidden');
        }

        function handleMouseLeave(e) {
            e.stopPropagation();
            link.classList.add('hidden');
        }

        cell.addEventListener('mouseenter', handleMouseEnter);
        cell.addEventListener('mouseleave', handleMouseLeave);

        return () => {
            cell.removeEventListener('mouseenter', handleMouseEnter);
            cell.removeEventListener('mouseleave', handleMouseLeave);
            link.remove();
        };
    }

    onMount(() => {
        // Extend the completion provider while keeping existing functionality
        const newLanguage = registerTwigForLanguage(language)

        self.MonacoEnvironment = {
            getWorker: async function (_moduleId, label) {
                if (label === "json") {
                    const {default: JsonWorker} = await import('monaco-editor/esm/vs/language/json/json.worker?worker');
                    return new JsonWorker();
                }
                if (label === "css" || label === "scss" || label === "less") {
                    const {default: CssWorker} = await import('monaco-editor/esm/vs/language/css/css.worker?worker');
                    return new CssWorker();
                }
                if (label === "html" || label === "handlebars" || label === "razor") {
                    const {default: HtmlWorker} = await import('monaco-editor/esm/vs/language/html/html.worker?worker');
                    return new HtmlWorker();
                }
                const {default: EditorWorker} = await import('monaco-editor/esm/vs/editor/editor.worker?worker');
                return new EditorWorker();
            },
        };
        let options = {
            value,
            language: newLanguage,
            automaticLayout: true,
            minimap: {
                enabled: false
            },
            theme: 'twig',
            readOnly: readOnly,
            params: params,
            name,
            scope,
            twigVariables
        };

        if (language === 'json') {
            options = {
                ...options,
                bracketPairColorization: {
                    enabled: true
                }
            }
        }

        editorComponent = monaco.editor.create(containerElement, options);

        const fullScreenContainer = document.createElement('div');
        fullScreenContainer.classList.add('fullscreen-monaco-editor')
        document.body.appendChild(fullScreenContainer);

        editorComponent.addAction({
            id: 'fullscreen',
            label: 'Toggle Fullscreen',
            keybindings: [
                monaco.KeyMod.CtrlCmd | monaco.KeyMod.Shift | monaco.KeyCode.KeyF
            ],
            run: function () {
                if (!document.fullscreenElement) {
                    fullScreen = true
                    fullScreenContainer.appendChild(containerElement);
                    fullScreenContainer.requestFullscreen();
                } else {
                    document.exitFullscreen();
                }

                editorComponent.layout(); // Resize editor content
            }
        });

        const handleFullscreen = (evt) => {
            if (!document.fullscreenElement && evt.target === fullScreenContainer) {
                fullScreen = false
                rootElement.appendChild(containerElement)
                editorComponent.layout();
            }
        };

        document.addEventListener('fullscreenchange', handleFullscreen);


        editorComponent.onDidChangeModelContent(() => {
            value = editorComponent.getValue();
        });

        const onEnd = initFullScreenIcon(rootElement.closest('.cell'), editorComponent)

        return () => {
            editorComponent.dispose();
            fullScreenContainer.remove();
            document.removeEventListener('fullscreenchange', handleFullscreen);
            onEnd()
        };
    });
</script>

<Text {name} {value} {mode} {params}>
    <div bind:this={rootElement}>
        <div class={`code-container ${readOnly?'read-only':''}`} style={`height: ${height}`}
             bind:this={containerElement}>
        </div>
    </div>
</Text>

<style>
    .code-container {
        border: 1px solid #ced4da;
        resize: vertical;
        overflow: auto;
    }

    :global(.fullscreen-monaco-editor .code-container) {
        resize: none;
        overflow: unset;
    }

    :global(.code-container .monaco-editor) {
        outline: none;
    }

    :global(.monaco-editor .margin) {
        margin: 0 !important;
    }

    :global(.code-container.read-only .monaco-editor) {
        --vscode-editor-background: #efefef;
    }
</style>