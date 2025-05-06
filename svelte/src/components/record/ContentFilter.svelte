<script lang="ts">
    import {onMount} from "svelte";
    import {Language} from "../../utils/Language";
    import {Notifier} from "../../utils/Notifier";
    import {Metadata} from "../../utils/Metadata";
    import {Storage} from "../../utils/Storage";


    export let scope: string;
    export let onExecute: (e: CustomEvent) => void;


    let allFilters = ['filled', 'empty', 'optional', 'required']

    let filters = [...allFilters]
    let selectedFilters = Storage.get('fieldFilter', scope) || []
    cleanFilters()

    let dropdownButton: HTMLElement;
    let dropdownDiv: HTMLElement;

    function unsetAll() {
        selectedFilters = []
        onFilterChange()
    }

    function onFilterChange() {
        cleanFilters()
        Storage.set('fieldFilter', scope, selectedFilters)

        onExecute(new CustomEvent('execute', {
            detail: {
                action: 'applyOverviewFilter'
            }
        }))
    }

    function cleanFilters() {
        filters = [...allFilters]
        if (selectedFilters.includes('filled')) {
            filters.splice(filters.indexOf('empty'), 1)
        } else if (selectedFilters.includes('empty')) {
            filters.splice(filters.indexOf('filled'), 1)
        }

        if (selectedFilters.includes('optional')) {
            filters.splice(filters.indexOf('required'), 1)
        } else if (selectedFilters.includes('required')) {
            filters.splice(filters.indexOf('optional'), 1)
        }

        selectedFilters = selectedFilters.filter(filter => filters.includes(filter))
    }


</script>

<div class="search-row" style="padding-bottom: 0">
    <div class="form-group">
        <div class="btn-group input-group filter-group">
            <button
                    type="button"
                    class="btn btn-default filter"
                    title={Language.translate('Filter')}
                    aria-expanded="false"
                    class:active={selectedFilters.length>0}
            >
                {#if selectedFilters.length > 0}
                    <i class="ph-fill ph-funnel"></i>
                {:else}
                    <i class="ph ph-funnel"></i>
                {/if}
            </button>
            {#if selectedFilters.length > 0 }
                <button type="button"
                        class="btn btn-default reset"
                        title={Language.translate('Reset Filter')}
                        aria-expanded="false"
                        on:click={unsetAll}
                >
                    <i class="ph ph-x"></i>
                </button>
            {/if}

            <div bind:this={dropdownDiv} class="dropdown" class:has-content={selectedFilters.length>0}>
                <button
                        bind:this={dropdownButton}
                        data-toggle="dropdown"
                        class="btn btn-default filter-switcher"
                        on:mousedown={event => event.preventDefault()}
                >
                    <span class="filter-names">{selectedFilters.map(item => Language.translateOption(item, 'fieldFilter', 'Global')).join(', ')}</span>
                    <i class="ph ph-caret-down chevron"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <ul style="padding: 0" on:click={event => event.stopPropagation()}>
                        {#each allFilters as filter }
                            <li class="checkbox">
                                <label>
                                    <input disabled="{!filters.includes(filter)}" type="checkbox"
                                           bind:group={selectedFilters} value="{filter}"
                                           on:change={onFilterChange}>
                                    {Language.translateOption(filter, 'fieldFilter', 'Global')}
                                </label>
                            </li>
                        {/each}
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
    .search-row .input-group {
        border: 0;
    }

    .search-row .btn {
        border: 1px solid #eee;
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
    }

    .has-content .filter-switcher {
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

    .dropdown:last-child .btn:last-of-type {
        border-radius: 0 3px 3px 0;
    }

    .filter-group .filter.active {
        color: #06c;
    }

    button.filter {
        margin-right: 0;
    }
</style>