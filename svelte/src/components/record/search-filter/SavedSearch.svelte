<script lang="ts">
    import {createEventDispatcher, onMount} from "svelte";
    import {Language} from "../../../utils/Language";
    import {Acl} from "../../../utils/Acl";
    import Preloader from "../../icons/loading/Preloader.svelte";

    export let scope: string;
    export let savedSearchList: Array<any> = [];
    export let loading: boolean = true;

    export let editingItem: any = null;

    export let edit = (item) => {};
    export let rename = (item) => {};
    export let remove = (item) => {};
    export let cancel = () => {}

    export let selectedSavedSearchIds: Array<any> = [];

    let dispatch = createEventDispatcher();
    function handleSavedSearchChecked(e, item) {
        let isChecked = e.target.checked;
        if(isChecked) {
            selectedSavedSearchIds = [...selectedSavedSearchIds, item.id]
        }else{
            selectedSavedSearchIds = [...selectedSavedSearchIds.filter(v => v !== item.id)];
        }
        dispatch('change', {selectedSavedSearchIds: selectedSavedSearchIds});
    }

</script>


<div class="checkboxes-filter">
    {#if loading }
        <div style="margin-top: 5px;">
            <Preloader heightPx={12} />
        </div>
    {:else if savedSearchList.length > 0}
        <h5>{Language.translate('Saved Filters')}</h5>
        <ul>
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
                    {#if Acl.check('SavedSearch', 'edit') ||  Acl.check('SavedSearch', 'delete')}
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

