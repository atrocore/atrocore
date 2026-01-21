<script lang="ts">
    import { Language } from "$lib/core/language"
    import {Notifier} from "../../../utils/Notifier";

    export let scope: string;
    export let searchManager: any;

    let searchValue = searchManager.geTextFilter();
    let hasSearchValue = searchValue.trim().length > 0;


    function refreshHasSearchValue() {
        hasSearchValue = searchValue.trim().length > 0;
    }
    function search() {
        searchManager.update({
            textFilter: searchValue.trim()
        });

        refreshHasSearchValue();

        updateCollection();
    }

    export function reset() {
        searchValue = "";
        search();
    }

    function updateCollection() {
        Notifier.notify(Language.translate('loading', 'messages'));

        searchManager.fetchCollection();
    }
</script>

<div class="search-row">
    <div class="form-group">
        <div class="input-group search">
            <input
                    type="text"
                    class="form-control text-filter"
                    placeholder={Language.translate("typeToSearch")}
                    name="textFilter"
                    bind:value={searchValue}
                    on:keypress={(e) => {e.key === 'Enter' ? search() : e}}
                    on:keyup={(e) => {e.key === 'Enter' ? search() : e}}
                    tabindex="1"
            >
            <div class="button-group">
                {#if hasSearchValue}
                    <button type="button" aria-expanded="false" data-button-id="search-reset" on:click={reset}>
                        <i class="ph ph-x"></i>
                    </button>
                {/if}
                <button
                        type="button"
                        class:has-search-value={hasSearchValue}
                        title={Language.translate("Search")}
                        aria-expanded="false"
                        disabled={searchValue.trim().length === 0}
                        on:click={search}
                >
                    {#if hasSearchValue}
                        <i class="ph-fill ph-magnifying-glass"></i>
                    {:else}
                        <i class="ph ph-magnifying-glass"></i>
                    {/if}
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .search-row {
        flex-basis: auto;
        padding-bottom: 0;
    }

    .search-row .search {
        flex-grow: 1;
        width: 300px;
    }

    .search-row .search input {
        background: transparent;
        border: 1px solid #e0e0e0;
        border-right: 0;
        border-top-left-radius: 3px;
        border-bottom-left-radius: 3px;
    }

    .search-row .input-group {
        border: 0;
    }

    .search-row .form-group {
        display: flex;
    }

    .search-row .button-group {
        flex-wrap: nowrap;
    }

    .search-row .button-group > button:first-child {
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    }

    button.has-search-value {
        color: #06c;
    }
</style>