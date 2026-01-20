<script lang="ts">
    import {createEventDispatcher, onMount} from "svelte";
    import { Metadata } from '$lib/core/metadata';

    interface ViewTypeButton {
        name: string;
        icon: string;
        link?: string;
    }

    const dispatch = createEventDispatcher();

    const viewIcons: Record<string, string> = {
        list: '<i class="ph ph-list"></i>',
        plate: '<i class="ph ph-squares-four"></i>',
        kanban: '<i class="ph ph-kanban"></i>',
        tree: '<i class="ph ph-tree-view"></i>'
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
                    icon: viewIcons[mode],
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
    <div class="button-group view-mode-switch">
        {#each viewTypes as view}
            <button class="primary action" class:outline={view.name !== mode} data-name={view.name} data-action="List" on:click={changeView}>
                {@html view.icon}
            </button>
        {/each}
    </div>
{/if}
