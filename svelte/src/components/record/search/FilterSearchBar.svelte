<script lang="ts">
    import FilterSearch from "./FilterSearch.svelte";
    import SearchBar from "./SearchBar.svelte";
    import { Language } from "$lib/core/language"
    import {Notifier} from "../../../utils/Notifier";

    export let showFilter: boolean = false;
    export let showSearchPanel: boolean = false;
    export let searchManager: any;
    export let scope: string;
    export let uniqueKey: string = 'default';

    let refreshDisabled: boolean = false;
    let search: any;
    let filter: any;
    export function reset() {
        filter.unsetAll();
        search.reset();
    }

    function onRefreshClick() {
        refreshDisabled = true;
        Notifier.notify(Language.translate('loading', 'messages'));
        searchManager.fetchCollection();

        window.Backbone.once('after:search', () => {
            Notifier.notify(false);
            refreshDisabled = false;
        })
    }
</script>

<div class="filter-search-bar">
    {#if showSearchPanel}
        <SearchBar bind:this={search} searchManager={searchManager} scope={scope}/>
    {/if}
    {#if showFilter}
       <div class="filter-search">
           <FilterSearch bind:this={filter} searchManager={searchManager} scope={scope} uniqueKey={uniqueKey}/>
       </div>
    {/if}
    <button class="refresh" disabled={refreshDisabled} title={Language.translate('Refresh')} on:click={onRefreshClick}><i class="ph ph-arrows-clockwise"></i></button>
</div>

<style>
    .filter-search-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
</style>