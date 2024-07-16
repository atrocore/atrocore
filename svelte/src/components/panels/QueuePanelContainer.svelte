<script lang="ts">
    export let isOpen = false;
    export let close: any;

    import {Language} from '../../utils/Language';
    import {UserData} from '../../utils/UserData';
    import {Notifier} from '../../utils/Notifier';

    let qmPaused = false;
    window.addEventListener('publicDataFetched', (event: CustomEvent): void => {
        qmPaused = !!(event.detail.qmPaused);
    });

    async function startStopQm(pause: boolean): void {
        let userData = UserData.get();
        if (!userData) {
            return;
        }

        try {
            const response = await fetch('/api/v1/App/action/QueueManagerUpdate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization-Token': btoa(userData.user.userName + ':' + userData.token)
                },
                body: JSON.stringify({pause: pause})
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            await response.json();

            Notifier.notify('Done', 'success');
        } catch (error) {
            console.error('Error:', error);
        }
    }
</script>

{#if isOpen}
    <div class="queue-panel-container">
        <div class="panel panel-default">
            <div class="panel-heading clearfix">
                <span class="panel-heading-title">{Language.translate('queueManager', 'labels', 'QueueItem')}</span>
                <span class="pull-right">
                    <a href="/" class="close" on:click={close}><span aria-hidden="true">×</span></a>
                    {#if qmPaused}
                    <a href="/" title="{Language.translate('Start')}" class="qm-button"
                       on:click={event=>{event.preventDefault();startStopQm(false);}}>{Language.translate('Start')}</a>
                    {:else}
                    <a href="/" title="{Language.translate('Pause')}" class="qm-button"
                       on:click={event=>{event.preventDefault();startStopQm(true);}}>{Language.translate('Pause')}</a>
                    {/if}
                    <a href="#QueueItem" title="{Language.translate('View List')}"
                       class="qp-view-list">{Language.translate('View List')}</a>
               </span>
            </div>
            <div class="panel-body">
                <div class="list-container">{Language.translate('Loading...')}</div>
            </div>
        </div>
    </div>
{/if}

<style>
    .qp-view-list {
        margin-left: 5px
    }

    .close {
        margin-left: 10px;
    }
</style>
