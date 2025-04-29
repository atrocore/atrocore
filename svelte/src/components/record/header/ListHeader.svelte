<script lang="ts">
    import {onDestroy, onMount} from "svelte";

    import BaseHeader from "./BaseHeader.svelte";
    import Params from "./interfaces/Params"
    import EntityActionsGroup from "./EntityActionsGroup.svelte";
    import EntityActionButtons from "./interfaces/EntityActionsButtons";
    import EntityHistory from "./navigation/EntityHistory.svelte";
    import EntityCallbacks from "./interfaces/EntityCallbacks";
    import SearchBar from "../search/SearchBar.svelte";
    import FilterSearch from "../search/FilterSearch.svelte";

    export let params: Params;
    export let entityActions: EntityActionButtons;
    export let callbacks: EntityCallbacks;
    export let viewMode: string = 'list';
    export let isFavoriteEntity: boolean = false;
    export let onViewModeChange: Function = (e: CustomEvent): void => {}

    function onViewChange(e: CustomEvent): void {
        viewMode = e.detail.name;
        onViewModeChange(e.detail.name)
    }

    onMount(() => {
        if (params.afterOnMount) {
            params.afterOnMount();
        }
    });

    onDestroy(() => {
        if (params.afterOnDestroy) {
            params.afterOnDestroy();
        }
    })
</script>

<EntityHistory scope={params.scope} />
<BaseHeader>
    {#if params.showSearchPanel || params.showFilter}
        <div class="search-wrapper">
            {#if params.showSearchPanel}
                <SearchBar searchManager={params.searchManager} scope={params.scope}/>
            {/if}

            {#if params.showFilter}
                <FilterSearch searchManager={params.searchManager} scope={params.scope}/>
            {/if}
        </div>
    {/if}
    <EntityActionsGroup {viewMode} scope={params.scope} {entityActions} {onViewChange} {callbacks} {isFavoriteEntity} />


</BaseHeader>

<style>
    .search-wrapper {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }

    .search-wrapper :global(.form-group) {
        margin: 0;
    }
</style>