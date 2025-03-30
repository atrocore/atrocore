<script lang="ts">
    import {Language} from "../../../../utils/Language";
    import {createEventDispatcher} from "svelte";
    import ActionParams from "../interfaces/ActionParams";

    const dispatch = createEventDispatcher();

    export let params: ActionParams;
    export let className: string = '';

    function runAction(e: Event) {
        const el = e.target as HTMLElement;

        dispatch('execute', {
            data: el.dataset,
            action: el.dataset.action,
            event: e
        });
    }
</script>

<a href="javascript:" role="button" class="action {className}" data-action={params.name || params.action}
   data-id={params.id} title={params.tooltip} on:click={runAction}>
    {#if params.html}{@html params.html}{:else}{Language.translate(params.label)}{/if}
</a>