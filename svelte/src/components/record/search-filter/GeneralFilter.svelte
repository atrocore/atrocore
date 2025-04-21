<script lang="ts">
   import {onMount} from 'svelte'
   import {get} from 'svelte/store'
    import {Language} from  '../../../utils/Language'
    import {Metadata} from  '../../../utils/Metadata'
    import {Notifier} from  '../../../utils/Notifier'
    import {generalFilterStore} from './stores/GeneralFilter'
    export let searchManager;

    export let scope: string;

    let boolFilterList: string[] = [];

    let selectedBoolFilters = [];

    initBoolFilter();

   const boolFilterSub = generalFilterStore.selectBoolFilters.subscribe((value) => {
        if(value.length === selectedBoolFilters.length) {
            return;
        }
        selectedBoolFilters = value;
    })

    function updateCollection() {
        Notifier.notify(Language.translate('loading', 'messages'));
        searchManager.collection.reset();

        searchManager.collection.where = searchManager.getWhere();
        searchManager.collection.abortLastFetch();
        searchManager.collection.fetch().then(() => window.Backbone.trigger('after:search', searchManager.collection));
    }

    function toggleBoolFilter(filter){
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
        boolFilterList = (Metadata.get(['clientDefs', scope, 'boolFilterList']) || []).filter(function (item) {
            if (typeof item === 'string') return true;
            item = item || {};
            if (item.accessDataList) {
                if (!window.Espo.Utils.checkAccessDataList(item.accessDataList, Acl, Acl.getUser())) {
                    return false;
                }
            }
            return true;
        }).map(function (item) {
            if (typeof item === 'string') return item;
            item = item || {};
            return item.name;
        });

        let hiddenBoolFilterList = Metadata.get(['clientDefs', scope, 'hiddenBoolFilterList']) || [];

        boolFilterList = boolFilterList.filter(function (item) {
            return !hiddenBoolFilterList.includes(item)
        });
        const boolData = searchManager.getBool();
        for (const filter in boolData) {
            if(boolData[filter] && boolFilterList.includes(filter)) {
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
<div class="checkboxes-filter">
    {#if boolFilterList?.length > 0}
        <h5>{Language.translate('General Filters')}</h5>
        <ul style="padding:0">
            {#each boolFilterList as filter}
                <li class="checkbox">
                    <label class:active={selectedBoolFilters.includes(filter)}>
                        <input type="checkbox" checked={selectedBoolFilters.includes(filter)}
                               on:change={() => toggleBoolFilter(filter)} name="{filter}">
                        {Language.translate(filter, 'boolFilters', scope)}
                    </label>
                </li>
            {/each}
        </ul>
    {/if}
</div>
