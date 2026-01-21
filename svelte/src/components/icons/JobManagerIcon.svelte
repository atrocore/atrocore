<script lang="ts">
    import QueuePanelContainer from "../panels/QueuePanelContainer.svelte";
    import { Language } from "$lib/core/language"

    export let renderTable = (): void => {
        // should render table
    };

    let icon;
    let isPanelOpen = false;

    let qmPaused = false;
    window.addEventListener('publicDataFetched', (event: any): void => {
        qmPaused = !!(event.detail.qmPaused);
    });

    function openPanel(): void {
        if (!isPanelOpen) {
            isPanelOpen = true;
            renderTable();
        }
    }

    function closePanel(): void {
        isPanelOpen = false;
        window.dispatchEvent(new Event('jobManagerPanelClosed'));
    }
</script>

<a href="/" class="notifications-button" bind:this={icon} on:click={event=>{event.preventDefault();openPanel();}} title={Language.translate('jobManager')}>
    <i class="ph ph-list-checks"></i>
    {#if qmPaused}
        <i class="ph-fill ph-pause-circle pause-icon"></i>
    {/if}
</a>
<QueuePanelContainer icon={icon} isOpen={isPanelOpen} close={closePanel}/>

<style>
    .notifications-button {
        position: relative;
    }

    .pause-icon {
        position: absolute;
        top: 5%;
        right: -5%;
        z-index: 10;
        color: #ef990e;
        font-size: 16px;
    }
</style>