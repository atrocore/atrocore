<script lang="ts">
    import {Language} from "../../../../utils/Language";
    import {createEventDispatcher} from "svelte";
    import ActionParams from "../interfaces/ActionParams";

    const dispatch = createEventDispatcher();

    export let params: ActionParams;
    export let className: string = '';

    let computedClassNames: string;
    $: {
        computedClassNames = `btn btn-${params.style ?? 'default'} action`;
        if (className) {
            computedClassNames += ` ${className}`;
        }
    }

    function runAction(e: Event) {
        const el = e.target as HTMLElement;

        dispatch('execute', {
            data: el.dataset,
            action: el.dataset.action,
            event: e
        });
    }
</script>

<button class={computedClassNames} data-name={params.name} data-action={params.action || params.name} data-id={params.id} type="button"
        on:click={runAction} title={params.tooltip}>
    {#if params.html}{@html params.html}{:else}{Language.translate(params.label)}{/if}
</button>