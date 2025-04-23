<script lang="ts">
    import {onMount} from "svelte";
    import {get} from "svelte/store";
    import {Language} from "../../../utils/Language";
    import {Notifier} from "../../../utils/Notifier";
    import SavedSearch from "../search-filter/SavedSearch.svelte"
    import GeneralFilter from "../search-filter/GeneralFilter.svelte";
    import {generalFilterStore} from "../search-filter/stores/GeneralFilter"
    import {savedSearchStore} from "../search-filter/stores/SavedSearch"
    import Rule from "../search-filter/interfaces/Rule";
    import {Metadata} from "../../../utils/Metadata";


    export let scope: string;
    export let searchManager: any;
    export let hiddenBoolFilter: string[] = [];

    let showUnsetAll: boolean = false;
    let filterNames: string = "";
    let advancedFilterDisabled: boolean = false;
    let advancedFilterChecked: boolean = searchManager.isQueryBuilderApplied();
    let dropdownButton: HTMLElement;
    let dropdownDiv: HTMLElement;

    generalFilterStore.advancedFilterChecked.set(advancedFilterChecked);

    const selectSavedSub = savedSearchStore.selectedSavedItemIds.subscribe(_ => {
        refreshShowUnsetAll();
        updateSelectedFilterNames();
        closeDropdown();
    });

    const selectBoolSub = generalFilterStore.selectBoolFilters.subscribe(_ => {
        refreshShowUnsetAll();
        updateSelectedFilterNames();
        closeDropdown();
    });

    const advancedFilterCheckedSub =  generalFilterStore.advancedFilterChecked.subscribe((value) => {
        advancedFilterChecked = value;
        refreshShowUnsetAll();
        updateSelectedFilterNames();
    });

    const advancedFilterDisabledSub = generalFilterStore.advancedFilterDisabled.subscribe((value) => {
        advancedFilterDisabled = value;
        closeDropdown();
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
        searchManager.collection.abortLastFetch();
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

    function refreshAdvancedFilterDisabled() {
        let rules = searchManager.getQueryBuilder();
        let value = false;

        if(typeof rules === 'object' && rules.condition) {
            value = isRuleEmpty(rules);
        }

        generalFilterStore.advancedFilterDisabled.set(value);

        if(value) {
            generalFilterStore.advancedFilterChecked.set(false);
        }
    }

    function isRuleEmpty(rule: Rule): boolean {
        if(rule.operator) {
            return  false;
        }

        if(!rule.rules) {
            return true;
        }

        return rule.rules.length === 0;
    }

    function openFilter() {
        window.dispatchEvent(new CustomEvent('right-side-view:toggle-filter'));
    }

    function cleanUpSavedRule( exists: Function): boolean{
        // we clean up to remove deleted fields
        let hasChanged = false;
        let  cleanUpRule = (rule: Rule) => {
            if(rule.rules) {
                for (const rulesKey in rule.rules) {
                    if(rule.rules[rulesKey].id) {
                        if(!exists(rule)){
                            hasChanged = true;
                            rule.rules = rule.rules.filter(v => v.id !== rule.rules[rulesKey].id);
                        }
                    }

                    if(rule.rules[rulesKey] && rule.rules[rulesKey].rules) {
                        cleanUpRule(rule.rules[rulesKey]);
                    }
                }
            }
        }

        let rule = searchManager.getQueryBuilder();
        cleanUpRule(rule);
        if(hasChanged) {
            searchManager.update({queryBuilder: rule})
        }

        return hasChanged
    }

    function closeDropdown() {
        window.$(dropdownDiv).removeClass('open');
        window.$(dropdownButton).attr('aria-expanded', false);
    }

    onMount(() => {
        refreshShowUnsetAll();
        refreshAdvancedFilterDisabled();
        cleanUpSavedRule((field) => {
            return !!Metadata.get(['entityDefs', scope, 'fields', field]);
        });

        return () => {
            advancedFilterCheckedSub();
            selectSavedSub();
            selectBoolSub();
            advancedFilterDisabledSub();
        }
    });
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
                        class:active={showUnsetAll}
                >
                    <svg class="icon" ><use href="client/img/icons/icons.svg#filter"></use></svg>
                </button>
                <div bind:this={dropdownDiv} class="dropdown" class:has-content={filterNames !== ""}>
                    <button
                            bind:this={dropdownButton}
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
                                    <input type="checkbox"  disabled={advancedFilterDisabled} bind:checked={advancedFilterChecked} on:change={() => handleAdvancedFilterChecked()}>
                                    {Language.translate('Advanced Filter')}
                                </label>
                            </li>
                        </ul>
                    </div>
                </div>
                {#if showUnsetAll}
                    <button
                            type="button"
                            class="btn btn-default reset"
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
        border-radius: 3px;
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
        max-width: 400px;
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

    .has-content .filter-switcher {
        padding-left: 10px;
    }

     .has-content .filter-switcher span{
        text-align: left;
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

    .filter-group .filter.active{
        color: #06c;
    }

    button.reset {
        height: 35px;
    }

</style>