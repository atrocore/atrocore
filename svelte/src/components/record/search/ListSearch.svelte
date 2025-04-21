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
    export let canShowFilter: boolean;
    export let openFilter: Function;
    export let hiddenBoolFilter: string[] = [];

    let showUnsetAll: bool = false;
    let filterNames: string = "";
    let advancedFilterChecked: bool = searchManager.isQueryBuilderApplied();
    let searchValue = searchManager.geTextFilter();

    $: hasSearchValue = searchValue.trim().length > 0;

    generalFilterStore.advancedFilterChecked.set(advancedFilterChecked);

    const selectSavedSub = savedSearchStore.selectedSavedItemIds.subscribe(_ => {
        refreshShowUnsetAll();
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

    function search() {
        searchManager.update({
            textFilter: searchValue.trim()
        });

        updateCollection();
    }

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

    function reset() {
        searchValue = "";
        search();
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

        filterNames =  boolFilters.concat(selectedSavedSearchNames).reverse().join(',').trim();
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

<div class="row search-row">
    <div class="form-group ">
        <div class="input-group search" style="width: 100%">
            <input
                type="text"
                class="form-control text-filter"
                placeholder={Language.translate("Type and press the enter key or click on the search icon to search...", "messages")}
                name="textFilter"
                bind:value={searchValue}
                on:keypress={(e) => {e.key === 'Enter' ? search() : e}}
                on:keyup={(e) => {e.key === 'Enter' ? search() : e}}
                tabindex="1"
            >
            <div class="input-group-btn">
                {#if hasSearchValue}
                    <button
                        type="button"
                        class="btn btn-default "
                        data-original-title="Reset"
                        aria-expanded="false"
                        data-tippy="true"
                        on:click={reset}
                    >
                        <svg class="icon"><use href="client/img/icons/icons.svg#close"></use></svg>
                    </button>
                {/if}
                <button
                    type="button"
                    class="btn btn-primary"
                    data-original-title="Search"
                    aria-expanded="false"
                    data-tippy="true"
                    on:click={search}
                >
                    <svg class="icon"><use href="client/img/icons/icons.svg#search"></use></svg>
                </button>
            </div>
        </div>
        {#if canShowFilter}
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
            <div class="dropdown">
                <button
                    data-toggle="dropdown"
                    class="btn btn-default filter-switcher"
                    on:mousedown={event => event.preventDefault()}>
                    <span  class:has-content={filterNames !== ""}> {filterNames}</span>
                    <span class=" chevron fas fa-chevron-down" class:has-content={filterNames !== ""}></span>
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
        {/if}
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
        margin-left: 20px !important;
        display: flex;
    }

    .filter-switcher {
        max-width: 220px;
        width: auto;
        padding-right: 20px;
        padding-left: 15px;
        text-overflow: ellipsis;
        overflow: hidden;
        height: 100%;
        border-left: 1px solid #eee;
        border-right: 1px solid #eee;
    }

    .dropdown .dropdown-menu {
        min-width: 180px;
        max-width: 260px;
        padding: 10px;
    }

    .dropdown button .chevron.has-content {
        position: absolute;
        right: 0;
        top: 10px;
    }

    .dropdown button span.has-content {
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

    .filter-group .filter.has-content {
        color: #06c;
    }

</style>