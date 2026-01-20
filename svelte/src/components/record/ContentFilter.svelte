<script lang="ts">
    import {onMount} from "svelte";
    import {Language} from "../../utils/Language";
    import {Notifier} from "../../utils/Notifier";
    import { Metadata } from '$lib/core/metadata';
    import {Storage} from "../../utils/Storage";
    import Dropdown from "../../utils/Dropdown";


    export let scope: string;
    export let onExecute: (e: CustomEvent) => void;
    export let allFilters = ['filled', 'empty', 'optional', 'required']
    export let storageKey = 'fieldFilter'
    export let translationScope = 'Global'
    export let translationField = 'fieldFilter'
    export let titleLabel = Language.translate('fieldValueFilters', 'labels', 'Global')

    let filters = [...allFilters]
    let selectedFilters = Storage.get(storageKey, scope) || []
    cleanFilters()

    let dropdownButton: HTMLElement;
    let dropdownDiv: HTMLElement;
    let dropdownMenu: HTMLElement;

    function unsetAll() {
        selectedFilters = []
        onFilterChange()
    }

    function onFilterChange() {
        cleanFilters()
        Storage.set(storageKey, scope, selectedFilters)

        onExecute(new CustomEvent('execute', {
            detail: {
                action: 'applyOverviewFilter'
            }
        }), selectedFilters)
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

    onMount(() => {
        const dropdown = new Dropdown(dropdownButton, dropdownMenu, {
            placement: 'bottom-start',
        });

        return () => {
            dropdown.destroy();
        }
    });

</script>

<div class="search-row" {...$$restProps}>
    <div class="form-group">
        <div class="button-group input-group filter-group">
            <div bind:this={dropdownDiv} class="dropdown" class:has-content={selectedFilters.length>0}>
                <button
                        bind:this={dropdownButton}
                        class="filter-switcher"
                        on:mousedown={event => event.preventDefault()}
                >
                    <i class="ph-radio-button" class:ph={selectedFilters.length === 0}
                       class:ph-fill={selectedFilters.length > 0}></i>
                    {#if selectedFilters.length > 0}
                        <span class="filter-names">{selectedFilters.map(item => Language.translateOption(item, translationField, translationScope)).join(', ')}</span>
                    {/if}
                </button>
                <div class="dropdown-menu" bind:this={dropdownMenu}>
                    {#if titleLabel}
                        <h5 style="margin-top: 0">{titleLabel}</h5>
                    {/if}
                    <ul style="padding: 0" on:click={event => event.stopPropagation()}>
                        {#each allFilters as filter }
                            <li class="checkbox">
                                <label>
                                    <input disabled="{!filters.includes(filter)}" type="checkbox"
                                           bind:group={selectedFilters} value="{filter}"
                                           on:change={onFilterChange}>
                                    {Language.translateOption(filter, translationField, translationScope)}
                                </label>
                            </li>
                        {/each}
                    </ul>
                </div>
            </div>
            {#if selectedFilters.length > 0 }
                <button type="button"
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
    }

    .has-content .filter-switcher {
        display: inline-flex;
        align-items: center;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    .dropdown .dropdown-menu {
        min-width: 180px;
        max-width: 260px;
        padding: 10px;
    }

    .has-content .filter-switcher > i {
        flex-shrink: 0;
        margin-right: 10px;
        color: #06c;
    }

    .has-content span.filter-names {
        margin-right: 5px;
        vertical-align: baseline;
        flex: 1;
        min-width: 0;
        text-overflow: ellipsis;
        overflow-x: clip;
    }

    .dropdown ul {
        padding: 0;
    }

    .dropdown ul li.checkbox {
        margin-top: 10px;
        margin-bottom: 10px;
    }
</style>