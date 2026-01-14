<script lang="ts">
    import {Language} from "../../../../utils/Language";
    import {createEventDispatcher} from "svelte";
    import ActionParams from "../interfaces/ActionParams";

    const dispatch = createEventDispatcher();

    export let params: ActionParams;
    export let className: string = '';

    let computedClassNames: string;
    $: {
        const size = params.size ?? 'regular';
        computedClassNames = `action`;

        if (size === 'small') {
            computedClassNames += ` small`;
        }

        (params.style ?? '').split(' ').forEach(style => {
            computedClassNames += ` ${style}`;
        });

        if (className) {
            computedClassNames += ` ${className}`;
        }

        if (params.className) {
            computedClassNames += ` ${params.className}`;
        }
    }

    function runAction(e: Event) {
        const el = e.currentTarget as HTMLElement;

        dispatch('execute', {
            data: el.dataset,
            action: el.dataset.action,
            event: e
        });
    }
</script>

{#if !params.hidden}
    <button class={computedClassNames} data-name={params.name} data-action={params.action || params.name}
            data-id={params.id} type="button" on:click={runAction} title={params.tooltip} disabled={params.disabled}>
        {#if params.html}{@html params.html}{:else}{Language.translate(params.label)}{/if}
    </button>
{/if}