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

    export let mode = 'detail';
    export let name = '';
    export let scope;
    export let params = {};
    export let value = null;

    let language = params.language || Metadata.get(['entityDefs', scope, 'fields', name, 'language']) || 'twig';
    let twigVariables = params.twigVariables || Metadata.get(['entityDefs', scope, 'fields', name, 'twigVariables']) || [];
    let editorComponent;
    let containerElement;
    let height = 200;
    let readOnly = mode !== 'edit';

    params = {
        ...params,
        seeMoreDisabled: true
    };

    $: {
        const lines = (value ?? '').split('\n').length;
        if (lines <= 3) {
            height = 60
        } else if (lines <= 40) {
            height = lines * 20;
        } else {
            height = 800
        }
    }

    function registerTwigForLanguage(languageId) {
        let newLanguageId = languageId
        if (languageId === 'json') {
            newLanguageId = `${languageId}-twig`;
            monaco.languages.register({id: newLanguageId});

            // Set up tokens provider
            monaco.languages.setMonarchTokensProvider(newLanguageId, jsonTwigLanguageConfig);
        }

        if (window.monacoProviderRegistered == null || !window.monacoProviderRegistered.includes(newLanguageId)) {
            monaco.languages.registerCompletionItemProvider(newLanguageId, {
                provideCompletionItems: function (model, position) {
                    const editor = monaco.editor.getEditors().find(editor => editor.getModel() === model);
                    const params = editor?.getRawOptions()?.params ?? {};
                    const twigVariables = editor?.getRawOptions()?.twigVariables ?? [];
                    console.log(twigVariables);
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

                        let filters = Metadata.get(['twigDescriptions', 'filters']) ?? {};

                        Object.keys(filters).forEach(key => {
                            const filter = filters[key]
                            if (filter.tag) {
                                if (!params.isExport) {
                                    return;
                                }
                            }
                            twigFilters.push({
                                label: key,
                                kind: monaco.languages.CompletionItemKind.Function,
                                insertText: filter.insertText ?? key,
                                insertTextRules: filter.insertText ? monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet : monaco.languages.CompletionItemInsertTextRule.None,
                                detail: "Filter",
                                documentation: filter.description
                            })
                        })

                        return {
                            suggestions: twigFilters.map(tf => ({range, ...tf}))
                        }
                    }

                    if (!isInTwigBlock && !isInTwigExpression) {
                        return {suggestions: []}
                    }


                    // Entity variable suggestions
                    let suggestions = [...twigVariables, {
                        name: 'config',
                        description: 'Configuration object of the system',
                    }].sort((v1, v2) => {
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

                    let functions = Metadata.get(['twigDescriptions', 'functions']) ?? {};

                    Object.keys(functions).sort((v1, v2) => {
                        return v1.localeCompare(v2);
                    }).forEach(key => {
                        const fn = functions[key]
                        if (fn.tag) {
                            if (!params.isExport) {
                                return;
                            }
                        }
                        suggestions.push({
                            label: key,
                            kind: monaco.languages.CompletionItemKind.Function,
                            insertText: fn.insertText ?? (key + '($0)'),
                            insertTextRules: monaco.languages.CompletionItemInsertTextRule.InsertAsSnippet,
                            detail: 'Function',
                            documentation: fn.description,
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
                if (label === "typescript" || label === "javascript") {
                    const {default: TsWorker} = await import('monaco-editor/esm/vs/language/typescript/ts.worker?worker');
                    return new TsWorker();
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

        editorComponent.onDidChangeModelContent(() => {
            value = editorComponent.getValue();
        });

        return () => {
            editorComponent.dispose();
        };
    });
</script>

<Text {name} {value} {mode} {params}>
    <div class={`code-container ${readOnly?'read-only':''}`} style={`height: ${height}px`} bind:this={containerElement}>
    </div>
</Text>

<style>
    .code-container {
        border: 1px solid #ced4da;
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