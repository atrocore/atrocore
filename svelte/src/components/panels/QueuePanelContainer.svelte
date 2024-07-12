<script lang="ts">
    export let isOpen = false;
    export let close: any;
    export let Language: any;
</script>

{#if isOpen}
    <div class="queue-panel-container">
        <div class="panel panel-default">
            <div class="panel-heading clearfix">
                <span class="panel-heading-title">{Language.translate('queueManager', 'labels', 'QueueItem')}</span>
                <span class="pull-right">
            <a href="/" class="close" on:click={close}><span aria-hidden="true">×</span></a>
            <a href="/" title="{Language.translate('Start')}" class="qm-button">{Language.translate('Start')}</a>
            <a href="/" title="{Language.translate('Pause')}" class="qm-button">{Language.translate('Pause')}</a>
            <a href="#QueueItem" title="{Language.translate('View List')}"
               class="qp-view-list">{Language.translate('View List')}</a>
        </span>
            </div>
            <div class="panel-body">
                <div class="list-container">
                    {Language.translate('Loading...')}
                </div>
            </div>
        </div>
    </div>
{/if}

<style>
    .qp-view-list {
        margin-left: 5px
    }
</style>
