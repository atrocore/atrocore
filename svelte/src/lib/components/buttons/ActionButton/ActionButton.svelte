<script lang="ts">
    import { createEventDispatcher } from "svelte";
    import { Language } from "$lib/core/language";
    import type { ActionParams } from "./types/button-style";
    import { getComputedClasses } from "./utils/action-button";

    const dispatch = createEventDispatcher();

    export let params: ActionParams;
    export let className: string = '';

    $: computedClassNames = getComputedClasses(params, className);

    const handleClick = (e: MouseEvent) => {
        const el = e.currentTarget as HTMLElement;
        dispatch('execute', {
            data: el.dataset,
            action: el.dataset.action,
            event: e
        });
    };
</script>

{#if !params.hidden}
    <button
            class={computedClassNames}
            data-toggle={params.dropdown ? 'dropdown' : null}
            data-name={params.name}
            data-action={params.action || params.name}
            data-id={params.id}
            type="button"
            on:click={handleClick}
            title={params.tooltip}
            disabled={params.disabled}
    >
        {#if params.html}
            {@html params.html}
        {:else}
            {Language.translate(params.label)}
        {/if}
    </button>
{/if}