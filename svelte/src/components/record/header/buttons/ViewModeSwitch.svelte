<script lang="ts">
    import {createEventDispatcher, onMount} from "svelte";
    import {Metadata} from "../../../../utils/Metadata";

    interface ViewTypeButton {
        name: string;
        iconClass: string;
        link?: string;
    }

    const dispatch = createEventDispatcher();

    const viewIcons = {
        list: 'fa fa-th-list',
        plate: 'fa fa-th',
        kanban: 'fa fa-grip-horizontal',
        tree: 'fa fa-stream'
    };

    export let modes: string[] | null = null;
    export let mode: string = 'list';
    export let scope: string;

    let viewTypes: ViewTypeButton[];

    $: {
        viewTypes = (modes || [mode])
            .filter((value, index, array) => viewIcons[value] && array.indexOf(value) === index)
            .map((mode: string) => {
                if (!viewIcons[mode]) {
                    mode = 'list';
                }

                return {
                    name: mode,
                    link: `#${scope}/${mode}`,
                    iconClass: viewIcons[mode],
                } as ViewTypeButton;
            });
    }

    function changeView(e: Event): void {
        const el = e.currentTarget as HTMLElement;

        if (el.dataset.name && el.dataset.name !== mode) {
            dispatch('view-change', {name: el.dataset.name});
        }
    }

    onMount(() => {
        if (modes !== null) {
            return;
        }

        const views = Metadata.get(['clientDefs', scope, 'listViewModeList']) || ['list'];
        if (Metadata.get(['clientDefs', scope, 'plateViewMode'])) {
            views.push('plate');
        }

        if (Metadata.get(['clientDefs', scope, 'kanbanViewMode'])) {
            views.push('kanban');
        }

        modes = views;
    });
</script>

{#if viewTypes.length > 1}
    <div class="btn-group view-mode-switch">
        {#each viewTypes as view}
            <a role="button" href={view.link} class="btn action" class:btn-default={view.name !== mode}
               class:btn-primary={view.name === mode} data-name={view.name} data-action="List" on:click={changeView}>
                <span class={view.iconClass}></span>
            </a>
        {/each}
    </div>
{/if}

<style>
    .btn:first-child {
        border-top-left-radius: 3px;
        border-bottom-left-radius: 3px;
    }

    .btn:last-child {
        border-top-right-radius: 3px;
        border-bottom-right-radius: 3px;
    }
</style>