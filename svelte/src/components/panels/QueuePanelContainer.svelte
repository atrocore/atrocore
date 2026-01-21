<script lang="ts">
    export let isOpen = false;
    export let close: any;
    export let icon: any;

    import {onMount, onDestroy} from 'svelte';

    import { Language } from "$lib/core/language"
    import {UserData} from '../../utils/UserData';
    import {Notifier} from '../../utils/Notifier';

    let qmPaused = false;
    window.addEventListener('publicDataFetched', (event: any): void => {
        qmPaused = !!(event.detail.qmPaused);
    });

    let panel: any;

    function handleClickOutside(event: any) {
        if (panel && !panel.contains(event.target) && icon && !icon.contains(event.target) && isOpen === true) {
            close();
        }
    }

    onMount(() => {
        document.addEventListener('click', handleClickOutside);
    });

    onDestroy(() => {
        document.removeEventListener('click', handleClickOutside);
    });

    async function startStopQm(pause: boolean) {
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
    <div bind:this={panel} class="queue-panel-container">
        <div class="panel panel-default">
            <div class="panel-heading clearfix">
                <span class="panel-heading-title">{Language.translate('jobManager')}</span>
                <span class="pull-right">
                    <a href="/" class="close" on:click={event=>{event.preventDefault();close();}}><span
                            aria-hidden="true">×</span></a>
               </span>
            </div>
            <div class="panel-body">
                <div class="btn-container">
                    <button title="{Language.translate('View List')}" on:click={e => {window.location.href = '#Job'; close();}}
                       class="primary outline"><i class="ph ph-list"></i><span>{Language.translate('View List')}</span></button>
                    {#if qmPaused}
                        <button title="{Language.translate('Start')}" class="primary outline"
                           on:click={event=>{event.preventDefault();startStopQm(false);}}><i class="ph ph-play"></i><span>{Language.translate('Start')}</span></button>
                    {:else}
                        <button title="{Language.translate('Pause')}" class="primary outline"
                           on:click={event=>{event.preventDefault();startStopQm(true);}}><i class="ph ph-pause"></i><span>{Language.translate('Pause')}</span></button>
                    {/if}
                </div>
                <div class="list-container">{Language.translate('Loading...')}</div>
            </div>
        </div>
    </div>
{/if}

<style>
    .close {
        margin-left: 10px;
    }
</style>
