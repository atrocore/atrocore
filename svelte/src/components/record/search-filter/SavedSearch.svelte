<script lang="ts">
    import {createEventDispatcher, onMount} from "svelte";
    import {Language} from "../../../utils/Language";
    import {Notifier} from "../../../utils/Notifier";
    import {Acl} from "../../../utils/Acl";
    import Preloader from "../../icons/loading/Preloader.svelte";
    import {savedSearchStore} from "./stores/SavedSearch.ts";
    import SavedSearch from "./interfaces/SavedSearch.ts"
    import {get} from "svelte/store"

    export let scope: string;
    export let savedSearchList: Array<SavedSearch> = [];
    export let loading: boolean = true;
    export let searchManager: any;
    export let hideRowAction: boolean = false;
    export let editingItem: any = null;
    export let edit: Function = () => {};
    export let rename: Function = () => {};
    export let remove: Function = () => {};
    export let cancel: Function = () => {};

    export let selectedSavedSearchIds: Array<string> = [];


    const savedSearchSubscribe =  savedSearchStore.savedSearchItems.subscribe(value => {
        savedSearchList = value;
     });

    savedSearchStore.savedSearchItems.set(searchManager.savedSearchList || []);

    savedSearchStore.selectedSavedItemIds.set(searchManager.getSavedFilters().map(v => v.id));

   const selectedSavedItemIdsSub =  savedSearchStore.selectedSavedItemIds.subscribe(value => {
        selectedSavedSearchIds = value;
    });

    const loadingSubscribe = savedSearchStore.loading.subscribe(value => {
        loading = value;
    });
    function handleSavedSearchChecked(e, item) {
        savedSearchStore.toggleSavedItemSelection(item.id);
        let checked = get(savedSearchStore.selectedSavedItemIds);
        searchManager.update({
            savedFilters: get(savedSearchStore.savedSearchItems).filter(item => checked.includes(item.id))
        });
        updateCollection();
    }

    function updateCollection() {
        searchManager.collection.reset();
        Notifier.notify(Language.translate('loading', 'messages'));

        searchManager.collection.where = searchManager.getWhere();
        searchManager.collection.abortLastFetch();
        searchManager.collection.fetch().then(() => window.Backbone.trigger('after:search', searchManager.collection));
    }

    onMount(() => {
        savedSearchStore.fetchSavedSearch(scope);

        return () => {
            savedSearchSubscribe();
            selectedSavedItemIdsSub();
            loadingSubscribe();
        }
    })

</script>


<div class="checkboxes-filter">
    {#if loading }
        <div style="margin-top: 5px;">
            <Preloader heightPx={12} />
        </div>
    {:else if savedSearchList.length > 0}
        <h5>{Language.translate('Saved Filters')}</h5>
        <ul style="padding: 0">
            {#each savedSearchList as item}
                <li class="checkbox">
                    <label class:active={selectedSavedSearchIds.includes(item.id)}>
                        <input type="checkbox" checked={selectedSavedSearchIds.includes(item.id)} on:change={(e) => handleSavedSearchChecked(e, item)} name="{item.id}">
                        {item.name}
                        {#if item.isPublic}
                            <i class="ph ph-users-three visibility"></i>
                        {:else}
                            <i class="ph ph-shield visibility"></i>
                        {/if}
                    </label>
                    {#if (Acl.check('SavedSearch', 'edit') ||  Acl.check('SavedSearch', 'delete')) && !hideRowAction}
                        <div class="list-row-buttons btn-group">
                            {#if editingItem?.id === item.id}
                                <span style="position:absolute; right: 20px"><i class="ph ph-pencil-simple-line"></i></span>
                            {/if}
                            <a style="cursor: pointer" href="javascript:" class="dropdown-toggle" data-toggle="dropdown">
                                <i class="ph ph-dots-three-vertical"></i>
                            </a>
                            <ul class="dropdown-menu pull-right">
                                {#if Acl.check('SavedSearch', 'edit')}
                                    {#if editingItem?.id === item.id}
                                        <li><a on:click={cancel}>{Language.translate('Cancel Edit')}</a></li>
                                    {:else}
                                        <li><a on:click={() => {edit(item)}}>{Language.translate('Edit')}</a></li>
                                    {/if}
                                    <li><a on:click={() => rename(item)}>{Language.translate('Rename')}</a></li>
                                {/if}
                                {#if Acl.check('SavedSearch', 'delete')}
                                    <li><a on:click={() => remove(item)}>{Language.translate('Remove')}</a></li>
                                {/if}
                            </ul>
                        </div>
                    {/if}
                </li>
            {/each}
        </ul>
    {/if}
</div>

<style>
    .visibility {
        margin-left: 3px;
    }
</style>

