<script lang="ts">
    import FilterSearch from "./FilterSearch.svelte";
    import SearchBar from "./SearchBar.svelte";

    export let showFilter: boolean = false;
    export let showSearchPanel: boolean = false;
    export let searchManager: any;
    export let scope: string;
    export let uniqueKey: string = 'default';

    let search: any;
    let filter: any;
    export function reset() {
        filter.unsetAll();
        search.reset();
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
</div>

<style>
    .filter-search-bar {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
</style>