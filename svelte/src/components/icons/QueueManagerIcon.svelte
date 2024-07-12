<script lang="ts">
    import QueuePanelContainer from "../panels/QueuePanelContainer.svelte";

    export let Language: any;
    export let NavbarView: any;

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
        if (interval) {
            window.clearInterval(interval);
        }
    }

    function renderTable() {
        NavbarView.getCollectionFactory().create('QueueItem', collection => {
            collection.maxSize = 20;
            collection.url = 'QueueItem';
            collection.sortBy = 'sortOrder';
            collection.asc = true;
            collection.where = [
                {
                    field: 'status',
                    type: 'in',
                    value: ['Running', 'Pending']
                }
            ];
            NavbarView.listenToOnce(collection, 'sync', () => {
                NavbarView.createView('list', 'views/record/list', {
                    el: NavbarView.options.el + ' .list-container',
                    collection: collection,
                    rowActionsDisabled: true,
                    checkboxes: false,
                    headerDisabled: true,
                    layoutName: 'listInQueueManager'
                }, view => {
                    view.render();
                    interval = window.setInterval(() => {
                        collection.fetch();
                    }, 2000)
                });
            });
            collection.fetch();
        });
    }
</script>

<a href="/" class="notifications-button" on:click={openPanel}>
    <span class="fas fa-tasks"></span>
    <span class="fas fa-pause-circle pause-icon hidden"></span>
</a>
<QueuePanelContainer isOpen={isPanelOpen} close={closePanel} {Language}/>