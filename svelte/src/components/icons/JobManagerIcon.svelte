<script lang="ts">
    import QueuePanelContainer from "../panels/QueuePanelContainer.svelte";
    import {Language} from "../../utils/Language";

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
    <svg class="icon"><use href="client/img/icons/icons.svg#tasks"></use></svg>
    {#if qmPaused}
    <span class="fas fa-pause-circle pause-icon"></span>
    {/if}
</a>
<QueuePanelContainer icon={icon} isOpen={isPanelOpen} close={closePanel}/>