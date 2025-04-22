<script lang="ts">
    import {onMount} from "svelte";
    import {get} from "svelte/store";
    import {Language} from "../../../utils/Language";
    import {Notifier} from "../../../utils/Notifier";
    import SavedSearch from "../search-filter/SavedSearch.svelte"
    import GeneralFilter from "../search-filter/GeneralFilter.svelte";
    import {generalFilterStore} from "../search-filter/stores/GeneralFilter"
    import {savedSearchStore} from "../search-filter/stores/SavedSearch"


    export let scope: string;
    export let searchManager: any;
    export let hiddenBoolFilter: string[] = [];

    let showUnsetAll: boolean = false;
    let filterNames: string = "";
    let advancedFilterChecked: boolean = searchManager.isQueryBuilderApplied();

    generalFilterStore.advancedFilterChecked.set(advancedFilterChecked);

    const selectSavedSub = savedSearchStore.selectedSavedItemIds.subscribe(_ => {
        refreshShowUnsetAll();
        updateSelectedFilterNames();
    });

    const selectBoolSub = generalFilterStore.selectBoolFilters.subscribe(_ => {
        refreshShowUnsetAll();
        updateSelectedFilterNames();

    });

    const advancedFilterCheckedSub =  generalFilterStore.advancedFilterChecked.subscribe((value) => {
        advancedFilterChecked = value;
        refreshShowUnsetAll();
        updateSelectedFilterNames();
    })


    function refreshShowUnsetAll() {
        setTimeout(() => {
            showUnsetAll = searchManager.isQueryBuilderApplied() || searchManager.getSavedFilters().length > 0
            let bool = searchManager.getBool();
            for (const boolKey in bool) {
                if(bool[boolKey]){
                    showUnsetAll = true;
                    break;
                }
            }
        }, 100)
    }


    function updateCollection() {
        searchManager.collection.reset();
        Notifier.notify(Language.translate('loading', 'messages'));

        searchManager.collection.where = searchManager.getWhere();

        searchManager.collection.fetch().then(() => window.Backbone.trigger('after:search', searchManager.collection));
    }

    function  handleAdvancedFilterChecked(refresh = true) {
        generalFilterStore.advancedFilterChecked.set(advancedFilterChecked);
        searchManager.update({queryBuilderApplied: advancedFilterChecked ? 'apply' : false});
        if(refresh) {
            updateCollection();
        }
    }

    function unsetAll() {
        if(!showUnsetAll) {
            return;
        }
        searchManager.reset();
        advancedFilterChecked = false;
        handleAdvancedFilterChecked(false);
        savedSearchStore.selectedSavedItemIds.set([]);
        generalFilterStore.selectBoolFilters.set([]);
        refreshShowUnsetAll();
        updateCollection();
        window.dispatchEvent(new CustomEvent('filter:unset-all'));
    }

    function updateSelectedFilterNames() {
        let boolFilters = get(generalFilterStore.selectBoolFilters).map((filter) => {
            return Language.translate(filter, 'boolFilters', scope);
        });

        let selectedSavedSearchNames = get(savedSearchStore.savedSearchItems)
            .filter(item => get(savedSearchStore.selectedSavedItemIds).includes(item.id))
            .map(item => item.name);

        filterNames =  boolFilters.concat(selectedSavedSearchNames).reverse().join(', ').trim();
    }

    function openFilter() {
        window.dispatchEvent(new CustomEvent('right-side-view:toggle-filter'));
    }

    onMount(() => {
        refreshShowUnsetAll();

        return () => {
            advancedFilterCheckedSub();
            selectSavedSub();
            selectBoolSub();
        }
    })
</script>

<div class="row search-row" style="padding-bottom: 0">
    <div class="form-group ">
            <div class="input-group filter-group">
                <button
                        type="button"
                        class="btn btn-default filter"
                        data-original-title="Filter"
                        aria-expanded="false"
                        data-tippy="true"
                        on:click={openFilter}
                        class:has-content={filterNames !== ""}
                >
                    <svg class="icon" ><use href="client/img/icons/icons.svg#filter"></use></svg>
                </button>
                <div class="dropdown" class:has-content={filterNames !== ""}>
                    <button
                            data-toggle="dropdown"
                            class="btn btn-default filter-switcher"
                            on:mousedown={event => event.preventDefault()}>
                        <span class="filter-names" > {filterNames}</span>
                        <span class=" chevron fas fa-chevron-down"></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right">
                        <GeneralFilter scope={scope} searchManager={searchManager} />
                        <SavedSearch scope={scope} searchManager={searchManager} hideRowAction={true}/>
                        <ul class="advanced-checkbox">
                            <li class="checkbox">
                                <label>
                                    <input type="checkbox" bind:checked={advancedFilterChecked} on:change={() => handleAdvancedFilterChecked()}>
                                    {Language.translate('Advanced Filter')}
                                </label>
                            </li>
                        </ul>
                    </div>
                </div>
                {#if showUnsetAll}
                    <button
                            type="button"
                            class="btn btn-default "
                            data-original-title="Reset Filter"
                            aria-expanded="false"
                            data-tippy="true"
                            on:click={unsetAll}
                    >
                        <svg class="icon"><use href="client/img/icons/icons.svg#close"></use></svg>
                    </button>
                {/if}
            </div>
    </div>
</div>


<style>
    .search-row .input-group {
        border: 1px solid #eee;
        border-radius: 5px;
    }

    .search-row .input-group-btn button {
        border: 0;
        border-left: 1px solid #eee;
        background-color: transparent;
        color: #333;
    }

    .search-row .form-group {
        display: flex;
    }

    .search-row .input-group.filter-group {
        display: flex;
    }


    .filter-switcher {
        max-width: 220px;
        width: auto;
        padding-right: 15px;
        padding-left: 15px;
        text-overflow: ellipsis;
        overflow: hidden;
        height: 100%;
        border-left: 1px solid #eee;
        border-right: 1px solid #eee;
    }

    .has-content  .filter-switcher{
        padding-right: 20px;
    }

    .dropdown .dropdown-menu {
        min-width: 180px;
        max-width: 260px;
        padding: 10px;
    }

    .has-content span.chevron{
        position: absolute;
        right: 10px;
        top: 10px;
    }

    .has-content span.filter-names {
        margin-right: 8px;
    }

    .advanced-checkbox label{
        font-weight: bold;
        margin-top: 9px;
        margin-bottom: 9px;
    }

    .dropdown-menu-right ul {
        padding: 0;
    }

    .filter-group .filter.has-content{
        color: #06c;
    }

</style>