<script lang="ts">
    import {createEventDispatcher, onMount} from "svelte";
    import {Language} from "../../../utils/Language";
    import {Acl} from "../../../utils/Acl";

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
        <img class="preloader"  src="client/img/atro-loader.svg" alt="loader">
    {:else if savedSearchList.length > 0}
        <h5>{Language.translate('Saved Filters')}</h5>
        <ul>
            {#each savedSearchList as item}
                <li class="checkbox">
                    <label class:active={selectedSavedSearchIds.includes(item.id)}>
                        <input type="checkbox" checked={selectedSavedSearchIds.includes(item.id)} on:change={(e) => handleSavedSearchChecked(e, item)} name="{item.id}">
                        {item.name}
                        <svg class="icon visibility"><use href={item.isPublic ? 'client/img/icons/icons.svg#group': 'client/img/icons/icons.svg#shield'}></use></svg>
                    </label>
                    {#if Acl.check('SavedSearch', 'edit') ||  Acl.check('SavedSearch', 'delete')}
                        <div class="list-row-buttons btn-group">
                            {#if editingItem?.id === item.id}
                                <span style="position:absolute; right: 15px"><svg class="icon"><use href="client/img/icons/icons.svg#edit"></use></svg></span>
                            {/if}
                            <a  style="cursor: pointer" class="dropdown-toggle" data-toggle="dropdown">
                                <svg class="icon"><use href="client/img/icons/icons.svg#dots"></use></svg>
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
    .preloader {
        height: 12px;
        margin-top: 5px;
    }

    .visibility {
        position: absolute;
        top: -5px;
        width: 15px;
        height: 15px;
        margin-left: 5px;
    }

</style>

