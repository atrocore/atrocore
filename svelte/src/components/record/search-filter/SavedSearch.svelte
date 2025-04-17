<script lang="ts">
    import {createEventDispatcher, onMount} from "svelte";
    import {CollectionFactory} from "../../../utils/CollectionFactory";
    import {Language} from "../../../utils/Language";
    import {Storage} from "../../../utils/Storage";

    export let scope: string;
    let loading: boolean = true;
    let savedSearchList: Array<any> = [];
    let selectedSavedSearches: Array<any> = [];

    let dispatch = createEventDispatcher();
    function handleSavedSearchChecked(e, item) {
        let isChecked = e.target.checked;
        if(isChecked) {
            selectedSavedSearches = [...selectedSavedSearches, item.id]
        }else{
            selectedSavedSearches = [...selectedSavedSearches.filter(v => v !== item.id)];
        }
        Storage.set('selectedSavedSearches', scope, selectedSavedSearches);
        dispatch('change', {savedSearches: savedSearchList.filter(v => selectedSavedSearches.includes(v.id))});
    }

    onMount(() =>{
        CollectionFactory.create('SavedSearch', (collection) => {
            collection.url = `SavedSearch`;
            collection.data.scope = scope;
            collection.fetch().then((data) => {
                savedSearchList = data.list;
                loading = false;
            })
        });
    })
</script>

<ul>
    {#if loading}
        <img class="preloader"  src="client/img/atro-loader.svg" alt="loader">
    {:else}
        <h5>{Language.translate('Saved Filters')}</h5>
        <ul>
            {#each savedSearchList as item}

                <li class="checkbox">
                    <label class:active={selectedSavedSearches.includes(item.id)}>
                        <input type="checkbox" checked={selectedSavedSearches.includes(item.id)} on:change={(e) => handleSavedSearchChecked(e, item)} name="{filter}">
                        {item.name}
                    </label>
                </li>
            {/each}
        </ul>
    {/if}
</ul>

