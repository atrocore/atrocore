<script lang="ts">
    import QueuePanelContainer from "../panels/QueuePanelContainer.svelte";

    export let Language: any;
    export let renderTable;

    let interval: any;

    let isPanelOpen = false;

    function openPanel(event: any): void {
        event.preventDefault();
        isPanelOpen = true;
        renderTable();
    }

    function closePanel(event: any): void {
        event.preventDefault();
        isPanelOpen = false;
        window.dispatchEvent(new Event('qmPanelClosed'));
    }
</script>

<a href="/" class="notifications-button" on:click={openPanel}>
    <span class="fas fa-tasks"></span>
    <span class="fas fa-pause-circle pause-icon hidden"></span>
</a>
<QueuePanelContainer isOpen={isPanelOpen} close={closePanel} {Language} />