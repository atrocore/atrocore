<script lang="ts">
    import QueuePanelContainer from "../panels/QueuePanelContainer.svelte";

    export let renderTable = (): void => {
        // should render table
    };

    let icon;
    let isPanelOpen = false;

    function openPanel(): void {
        if (!isPanelOpen) {
            isPanelOpen = true;
            renderTable();
        }
    }

    function closePanel(): void {
        isPanelOpen = false;
        window.dispatchEvent(new Event('qmPanelClosed'));
    }
</script>

<a href="/" class="notifications-button" bind:this={icon} on:click={event=>{event.preventDefault();openPanel();}}>
    <span class="fas fa-tasks"></span>
    <span class="fas fa-pause-circle pause-icon hidden"></span>
</a>
<QueuePanelContainer icon={icon} isOpen={isPanelOpen} close={closePanel}/>