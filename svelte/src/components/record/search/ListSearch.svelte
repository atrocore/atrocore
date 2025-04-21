<script lang="ts">
    import {onMount} from "svelte";

    export let searchManager: any;

    onMount(() => {
        console.log('mounted');
    })
</script>

<div class="row search-row">
    <div class="form-group ">
        <div class="input-group" style="">
            <input type="text" class="form-control text-filter" placeholder="Type and press the enter key or click on the search icon to search..." name="textFilter" value="" tabindex="1">
            <div class="input-group-btn">
                <button type="button" class="btn btn-default reset filter-btn" data-action="reset" data-original-title="Reset" style="display: none;" aria-expanded="false" data-tippy="true">
                    <svg class="icon"><use href="client/img/icons/icons.svg#close"></use></svg>
                </button>
                <button type="button" class="btn btn-primary search btn-icon btn-icon-x-wide filter-btn" data-action="search" data-original-title="Search" aria-expanded="false" data-tippy="true">
                    <svg class="icon"><use href="client/img/icons/icons.svg#search"></use></svg>
                </button>
            </div>
        </div>
    </div>
</div>


<style>
    .search-row .input-group {
        border: 1px solid #eee;
        border-radius: 5px;
    }
</style>