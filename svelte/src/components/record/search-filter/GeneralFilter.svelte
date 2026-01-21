<script lang="ts">
    import {onMount} from 'svelte'
    import {get} from 'svelte/store'
    import {Language} from '../../../utils/Language'
    import { Metadata } from '$lib/core/metadata';
    import {Notifier} from '../../../utils/Notifier'
    import {getGeneralFilterStore} from './stores/GeneralFilter'
    import { Acl } from "$lib/core/acl";
    import FilterGroup from "./FilterGroup.svelte";
    import {Storage} from "../../../utils/Storage";

    export let searchManager: any;

    export let scope: string;

    export let opened: boolean = false;

    export let uniqueKey: string = "default";

    let boolFilterList: string[] = [];

    let selectedBoolFilters: string[] = [];

    let generalFilterStore = getGeneralFilterStore(uniqueKey);

    initBoolFilter();

    const boolFilterSub = generalFilterStore.selectBoolFilters.subscribe((value) => {
        if (value.length === selectedBoolFilters.length) {
            return;
        }
        selectedBoolFilters = value;

        // needs for reports module
        let listQueryBuilder = Storage.get('listQueryBuilder', scope);
        if (!selectedBoolFilters.includes('reportSpecific') && listQueryBuilder && listQueryBuilder?.boolData?.reportSpecific) {
            delete listQueryBuilder.bool.reportSpecific;
            delete listQueryBuilder.boolData.reportSpecific;
            Storage.set('listQueryBuilder', scope, listQueryBuilder);
            boolFilterList.splice(boolFilterList.indexOf("reportSpecific"), 1);
        }
    })

    function updateCollection() {
        Notifier.notify(Language.translate('loading', 'messages'));
        searchManager.fetchCollection();
    }

    function toggleBoolFilter(filter: string) {
        generalFilterStore.toggleBoolFilters(filter);

        let value = get(generalFilterStore.selectBoolFilters);
        let bool = {};

        for (const filter of value) {
            bool[filter] = true;
        }
        searchManager.update({bool});

        updateCollection();
    }

    function initBoolFilter() {
        boolFilterList = [];
        (Metadata.get(['clientDefs', scope, 'boolFilterList']) || []).filter(function (item) {
            if (typeof item === 'string') return true;
            item = item || {};
            if (item.accessDataList) {
                if (!window.Espo.Utils.checkAccessDataList(item.accessDataList, Acl, Acl.getUser())) {
                    return false;
                }
            }
            return true;
        }).forEach(function (item) {
            // needs for reports module
            if (item === 'reportSpecific' && !Storage.get('listQueryBuilder', scope)?.bool?.reportSpecific) {
                return;
            }

            if (boolFilterList.includes(item)) {
                return;
            }
            if (typeof item === 'string') {
                boolFilterList.push(item);
                return;
            }
            item = item || {};
            if (item.name) {
                boolFilterList.push(item.name);
            }
        });

        if(Array.isArray(searchManager.additionalBoolFilterList)) {
            boolFilterList = boolFilterList.concat(searchManager.additionalBoolFilterList)
        }

        let hiddenBoolFilterList = Metadata.get(['clientDefs', scope, 'hiddenBoolFilterList']) || [];

        boolFilterList = boolFilterList.filter(function (item) {
            return !hiddenBoolFilterList.includes(item)
        });
        const boolData = searchManager.getBool();
        for (const filter in boolData) {
            if (boolData[filter] && boolFilterList.includes(filter)) {
                selectedBoolFilters.push(filter);
            }
        }
        generalFilterStore.selectBoolFilters.set(selectedBoolFilters);
    }

    onMount(() => {
        return () => {
            boolFilterSub();
        }

    });
</script>

{#if boolFilterList?.length > 0}
    <FilterGroup bind:opened={opened} className="checkboxes-filter" title={Language.translate('General Filters')}>
        <ul>
            {#each boolFilterList as filter}
                <li class="checkbox">
                    <label class:active={selectedBoolFilters.includes(filter)}>
                        <input type="checkbox" checked={selectedBoolFilters.includes(filter)}
                               on:change={() => toggleBoolFilter(filter)} name="{filter}">
                        <span>{Language.translate(filter, 'boolFilters', scope)}</span>
                    </label>
                </li>
            {/each}
        </ul>
    </FilterGroup>
{/if}

<style>
    ul {
        padding: 0;
    }
</style>
