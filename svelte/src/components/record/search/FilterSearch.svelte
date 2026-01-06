<script lang="ts">
    import {onMount} from "svelte";
    import {get} from "svelte/store";
    import {Language} from "../../../utils/Language";
    import {Notifier} from "../../../utils/Notifier";
    import SavedSearch from "../search-filter/SavedSearch.svelte"
    import GeneralFilter from "../search-filter/GeneralFilter.svelte";
    import {getGeneralFilterStore} from "../search-filter/stores/GeneralFilter"
    import {getSavedSearchStore} from "../search-filter/stores/SavedSearch"
    import Rule from "../search-filter/interfaces/Rule";
    import {Metadata} from "../../../utils/Metadata";
    import Dropdown from "../../../utils/Dropdown";


    export let scope: string;
    export let searchManager: any;
    export let uniqueKey: string = 'default';
    export let boolFilterData: any = {}

    let showUnsetAll: boolean = false;
    let filterNames: string = "";
    let advancedFilterDisabled: boolean = false;
    let advancedFilterChecked: boolean = searchManager.isQueryBuilderApplied();
    let dropdownButton: HTMLElement;
    let dropdownDiv: HTMLElement;
    let dropdownMenu: HTMLElement;

    let generalFilterStore = getGeneralFilterStore(uniqueKey);
    let savedSearchStore = getSavedSearchStore(scope, uniqueKey, {
        items: searchManager.savedSearchList || [],
        selectedItems: searchManager.getSavedFilters().map(v => v.id)
    });

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
        showUnsetAll = searchManager.isFilterSet();
    }


    function updateCollection() {
        Notifier.notify(Language.translate('loading', 'messages'));
        searchManager.fetchCollection();
    }

    function  handleAdvancedFilterChecked(refresh = true) {
        generalFilterStore.advancedFilterChecked.set(advancedFilterChecked);
        searchManager.update({queryBuilderApplied: advancedFilterChecked});
        if(refresh) {
            updateCollection();
        }
    }

    export function unsetAll() {
        if(!showUnsetAll) {
            return;
        }
        searchManager.update({
            bool: {},
            savedFilters: [],
            queryBuilderApplied: false,
            advanced: []
        });
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

        filterNames =  boolFilters.concat(selectedSavedSearchNames).join(', ').trim();
    }

    function refreshAdvancedFilterDisabled() {
        let rules = searchManager.getQueryBuilder();
        let value = true;

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
        window.dispatchEvent(new CustomEvent('right-side-view:toggle-filter', {detail: {uniqueKey}}));
    }

    function cleanUpSavedRule( exists: Function): boolean{
        // we clean up to remove deleted fields
        let hasChanged = false;
        let  cleanUpRule = (rule: Rule) => {
            if(rule.rules) {
                for (const rulesKey in rule.rules) {
                    if(rule.rules[rulesKey].id) {
                        if(!exists(rule.rules[rulesKey].id)){
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
        searchManager.collection.on('filter-state:changed', (value) => showUnsetAll = !!value);
        refreshAdvancedFilterDisabled();
        cleanUpSavedRule((field: string) => {
            // we do not clean up attribute here
            if(field.startsWith('attr_')) {
                return true;
            }
            let exits =  !!Metadata.get(['entityDefs', scope, 'fields', field]);
            if(!exits && field ===  (field.slice(0, -2) + 'Id'))  {
                return !!Metadata.get(['entityDefs', scope, 'fields', field.slice(0, -2)]);
            }
            return exits;
        });

        const dropdown = new Dropdown(dropdownButton, dropdownMenu, {
            placement: 'bottom-start',
            disableAutoHide: true,
        })

        return () => {
            searchManager.collection.off('filter-state:changed');
            advancedFilterCheckedSub();
            selectSavedSub();
            selectBoolSub();
            advancedFilterDisabledSub();
            dropdown.destroy();
        }
    });
</script>

<div class="search-row" style="padding-bottom: 0">
    <div class="form-group">
            <div class="button-group input-group filter-group">
                <button
                        type="button"
                        class="filter"
                        title={Language.translate('Filter')}
                        aria-expanded="false"
                        on:click={openFilter}
                        class:active={showUnsetAll}
                >
                    {#if showUnsetAll}
                        <i class="ph-fill ph-funnel"></i>
                    {:else}
                        <i class="ph ph-funnel"></i>
                    {/if}
                </button>

                <div bind:this={dropdownDiv} class="dropdown" class:has-content={filterNames !== ""}>
                    <button
                            bind:this={dropdownButton}
                            class="actions-button filter-switcher"
                            on:mousedown={event => event.preventDefault()}
                    >
                        {#if filterNames !== ""}
                            <span class="filter-names">{filterNames}</span>
                        {/if}
                        <i class="ph ph-caret-down chevron"></i>
                    </button>
                    <div class="dropdown-menu" bind:this={dropdownMenu}>
                        <GeneralFilter scope={scope} searchManager={searchManager} opened={true} uniqueKey={uniqueKey}/>
                        <SavedSearch scope={scope} searchManager={searchManager} hideRowAction={true} opened={true} uniqueKey={uniqueKey}/>
                        <ul class="advanced-checkbox">
                            <li class="checkbox">
                                <label>
                                    <input type="checkbox" disabled={advancedFilterDisabled} bind:checked={advancedFilterChecked} on:change={() => handleAdvancedFilterChecked()}>
                                    {Language.translate('Advanced Filter')}
                                </label>
                            </li>
                        </ul>
                    </div>
                </div>

                {#if filterNames !== "" || advancedFilterChecked}
                    <button
                            type="button"
                            disabled={!showUnsetAll}
                            class="reset"
                            title={Language.translate('Reset Filter')}
                            aria-expanded="false"
                            on:click={unsetAll}
                    >
                        <i class="ph ph-x"></i>
                    </button>
                {/if}
            </div>
    </div>
</div>


<style>
    .search-row .input-group {
        border: 0;
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
        padding-right: 10px;
        padding-left: 10px;
        text-overflow: ellipsis;
        overflow: hidden;
        height: 100%;
        margin: 0 -1px;
        border-radius: 0;
    }

    .has-content  .filter-switcher {
        padding-right: 0;
        display: inline-flex;
        align-items: center;
    }

    .dropdown .dropdown-menu {
        min-width: 180px;
        max-width: 260px;
        padding: 10px;
    }

    .has-content .chevron {
        flex-shrink: 0;
        margin-right: 10px;
    }

    .has-content span.filter-names {
        margin-right: 5px;
        vertical-align: baseline;
        flex: 1;
        min-width: 0;
        text-overflow: ellipsis;
        overflow-x: clip;
    }

    .advanced-checkbox label {
        font-weight: bold;
        margin-top: 9px;
        margin-bottom: 9px;
    }

    .dropdown ul {
        padding: 0;
    }

    .dropdown .advanced-checkbox,
    .dropdown .advanced-checkbox .checkbox {
        margin-bottom: 0;
    }

    .dropdown .advanced-checkbox {
        padding-left: 3px;
    }

    .dropdown:last-child button:last-of-type {
        border-radius: 0 3px 3px 0;
    }

    .filter-group .filter.active {
        color: #06c;
    }

    button.filter {
        margin-right: 0;
    }
</style>