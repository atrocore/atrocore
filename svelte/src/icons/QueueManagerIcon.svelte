<script lang="ts">
    import QueueManagerModal from "../modals/QueueManagerModal.svelte";

    let showModal = false;

    function showQueueManagerPopup(event) {
        event.preventDefault();
        showModal = true;
    }
</script>

<a href="/" class="notifications-button" on:click={showQueueManagerPopup}>
    <span class="fas fa-tasks"></span>
    <span class="fas fa-pause-circle pause-icon hidden"></span>
</a>

<QueueManagerModal {showModal}/>