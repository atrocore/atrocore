<script lang="ts">
    export let isOpen = false;
    export let close: any;

    import {Language} from '../../utils/Language';

    let qmPaused = false;
    window.addEventListener('publicDataFetched', (event: CustomEvent): void => {
        qmPaused = !!(event.detail.qmPaused);
    });

    async function startStopQm(pause: boolean): void {
        console.log('stop', pause);
        const url = '/api/v1/App/action/QueueManagerUpdate';
        const data = {
            pause: pause
        };

        // 'Authorization-Token': Base64.encode(userName + ':' + data.token)

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization-Token': 'YWRtaW46ZDg4MGVjOWY1MWY3YTEwYzc1MTA0ZDcxNjgzN2JkNDc='
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const result = await response.json();
            console.log('Success:', result);
        } catch (error) {
            console.error('Error:', error);
        }

        // NavbarView.ajaxPostRequest('App/action/QueueManagerUpdate', {pause: true}).then(() => {
        //     NavbarView.notify('Done', 'success');
        // });
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
