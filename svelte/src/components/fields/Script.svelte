<script>
    import {editor} from 'monaco-editor';
    import {onMount} from 'svelte';
    import Text from './Text.svelte';

    export let mode = 'detail';
    export let name = '';
    export let model = null;
    export let params = {};
    export let value = model.get(name);

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
        editorComponent = editor.create(containerElement, {
            value,
            language: "twig",
            automaticLayout: true,
            minimap: {
                enabled: false
            },
            readOnly: mode !== 'edit',
        });

        editorComponent.onDidChangeModelContent(() => {
            value = editorComponent.getValue();
        });
    });
</script>

<Text {name} {value} {mode} {model} {params}>
    <div class="code-container" style={`height: ${height}px;`} bind:this={containerElement}>
    </div>
</Text>