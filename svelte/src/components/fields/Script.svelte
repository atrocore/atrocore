<script>
    import {editor} from 'monaco-editor';

    import {onMount} from 'svelte';
    import Text from './Text.svelte';
    import {Metadata} from "../../utils/Metadata";

    export let mode = 'detail';
    export let name = '';
    export let scope;
    export let params = {};
    export let value = null;

    let editorComponent;
    let containerElement;
    let height = 200;

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


    export function fetch() {
        return {[name]: value};
    }

    onMount(() => {
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

        editorComponent = editor.create(containerElement, {
            value,
            language: params.language || Metadata.get(['entityDefs', scope, 'fields', name, 'language']) || "twig",
            automaticLayout: true,
            minimap: {
                enabled: false
            },
            readOnly: mode !== 'edit',
        });

        editorComponent.onDidChangeModelContent(() => {
            value = editorComponent.getValue();
        });

        return () => {
            editorComponent.dispose();
        };
    });
</script>

<Text {name} {value} {mode} {params}>
    <div class="code-container" style={`height: ${height}px;`} bind:this={containerElement}>
    </div>
</Text>