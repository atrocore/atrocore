<script lang="ts">
    import SpinnerIcon from "../../icons/loading/SpinnerIcon.svelte";
    import { Language } from "$lib/core/language"
    import {onMount} from "svelte";
    import {Utils} from "../../../utils/Utils";

    export let onApply: () => void;
    export let onCancel: () => void;

    let data: string | null = null;
    let buttonsDisabled: boolean = true;
    let loading: boolean = false;

    onMount(() => {
        loading = true;
        Utils.request('get', '/Admin/getSchemaDiff')
            .then(response => {
                if (response.ok) {
                    response.text().then(text => {
                        if (text) {
                            buttonsDisabled = false;
                        }

                        data = text;
                    })
                }
            }).finally(() => {
                loading = false;
            });
    });
</script>

<div class="rebuild-inner">
    <div class="button-container">
        <button class="danger" disabled={buttonsDisabled} on:click={onApply}>{Language.translate('Apply')}</button>
        <button on:click={onCancel}>{Language.translate('Cancel')}</button>
    </div>
    <div class="message">{Language.translate('rebuildDb', 'messages', 'Admin')}</div>
    <div class="details">
        {#if !loading}
            <pre>{data || 'No database changes were detected'}</pre>
        {:else}
            <SpinnerIcon size={50} thickness={5} />
        {/if}
    </div>
</div>

<style>
    .button-container {
        display: flex;
        gap: 10px;
    }

    .rebuild-inner {
        display: flex;
        flex-direction: column;
        height: 100%;
        gap: 10px;
    }

    .rebuild-inner > * {
        flex-shrink: 0;
    }

    .rebuild-inner > .details {
        flex: 1;
        min-height: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 20px;
    }

    .rebuild-inner > .details > pre {
        height: 100%;
        width: 100%;
        overflow-y: auto;
    }
</style>