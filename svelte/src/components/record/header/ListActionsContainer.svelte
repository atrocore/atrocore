<script lang="ts">
    import { Language } from "$lib/core/language";
    import Preloader from "../../icons/loading/Preloader.svelte";
    import FilterSearch from "../search/FilterSearch.svelte";
    import SearchBar from "../search/SearchBar.svelte";
    import {Notifier} from "../../../utils/Notifier";
    import Counter from "./interfaces/Counter";
    import MassAction from "./interfaces/MassAction";

    export let loading: boolean = false;
    export let counters: Array<Counter[]> = [];
    export let massActions: Array<MassAction | string> = [];
    export let showFilter: boolean = false;
    export let showSearch: boolean = false;
    export let searchManager: any;
    export let scope: string;
    export let uniqueKey: string = 'default';
    export let selected: string[] | boolean = false;
    export let hasSelectAllCheckbox: boolean = false;
    export let isRelationship: boolean = false;
    export let executeMassAction = (action: string, id?: Record<string, any>): void => {
    };
    export let handleSelectAll = (e: Event): void => {
    };

    export function reset(): void {
        filter.unsetAll();
        search.reset();
    }

    let refreshDisabled: boolean = false;
    let search: any;
    let filter: any;

    function onRefreshClick(): void {
        refreshDisabled = true;
        Notifier.notify(Language.translate('loading', 'messages'));
        searchManager.fetchCollection();

        window.Backbone.once('after:search', () => {
            Notifier.notify(false);
            refreshDisabled = false;
        })
    }

    function onMassActionClick(e: MouseEvent): void {
        const target = e.currentTarget as HTMLElement;
        const action = target.dataset.action;
        if (!action) {
            return;
        }

        executeMassAction(action, target.dataset);
    }

    function onSelectAllClick(e: Event): void {
        handleSelectAll(e);
    }

    $: canShowAction = massActions.length > 0 && (isRelationship ? (typeof selected === 'boolean' ? selected : selected.length > 0) : true);
</script>

<div class="actions-row">
    {#if hasSelectAllCheckbox}
        <div class="select-all-container">
            <input type="checkbox" class="select-all" checked={selected === true} on:click={onSelectAllClick}>
        </div>
    {/if}

    {#if canShowAction}
        <div class="actions">
            <button type="button" class="dropdown-toggle actions-button" data-toggle="dropdown"
                    disabled={typeof selected === 'boolean' ? !selected : selected.length === 0}
                    title={Language.translate('Actions')}>
                <span>{Language.translate('Actions')}</span><i class="ph ph-caret-down"></i>
            </button>
            <ul class="dropdown-menu">
                {#each massActions as action}
                    {#if typeof action === 'string'}
                        <li><a class="mass-action" href="javascript:" data-action={action}
                               on:click={onMassActionClick}>{Language.translate(action, 'massActions', scope)}</a></li>
                    {:else if action.divider}
                        <li class="divider"></li>
                    {:else}
                        <li><a class="mass-action" href="javascript:" data-action={action.action} data-id={action.id}
                               on:click={onMassActionClick}>{action.label}</a></li>
                    {/if}
                {/each}
            </ul>
        </div>
    {/if}

    {#if scope && searchManager}
        <div class="filter-search-bar">
            {#if showSearch}
                <SearchBar bind:this={search} searchManager={searchManager} scope={scope}/>
            {/if}
            {#if showFilter}
                <FilterSearch bind:this={filter} searchManager={searchManager} scope={scope} uniqueKey={uniqueKey}/>
            {/if}

            <button class="refresh" disabled={refreshDisabled} title={Language.translate('Refresh')}
                    on:click={onRefreshClick}><i class="ph ph-arrows-clockwise"></i></button>
        </div>
    {/if}

    {#if !isRelationship}
        <div class="list-details">
            <slot></slot>

            {#if counters.length > 0}
                <div class="counters-container">
                    {#if loading}
                        <Preloader heightPx={14}/>
                    {:else}
                        {#each counters as group}
                            <div class="group">
                                {#each group as counter, i}
                                    <span class="counter" data-name={counter.name}><span
                                            class="counter-label">{counter.label}</span>: <span
                                            class="counter-value">{counter.value}</span></span>
                                    {#if i < group.length - 1}<span class="separator">|</span>{/if}
                                {/each}
                            </div>
                        {/each}
                    {/if}
                </div>
            {/if}
        </div>
    {/if}
</div>

<style>
    .actions-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        height: 100%;
    }

    .actions-button i {
        font-size: 14px;
    }

    .filter-search-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .list-details {
        margin: 0 0 0 auto;
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 25px;
    }

    .counters-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 15px;
    }

    .separator {
        margin: 0 5px;
    }

    @media screen and (max-width: 768px) {
        .filter-search-bar {
            order: -1;
            flex-basis: 100%;
        }
    }

</style>