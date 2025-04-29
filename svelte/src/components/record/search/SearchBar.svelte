<script lang="ts">
    import {Language} from "../../../utils/Language";
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
            <div class="input-group-btn">
                {#if hasSearchValue}
                    <button
                            type="button"
                            class="btn btn-default"
                            aria-expanded="false"
                            on:click={reset}
                    >
                        <i class="ph ph-x"></i>
                    </button>
                {/if}
                <button
                        type="button"
                        class="btn btn-default" class:has-search-value={hasSearchValue}
                        title={Language.translate("Search")}
                        aria-expanded="false"
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
        border: 1px solid #eee;
        border-top-left-radius: 3px;
        border-bottom-left-radius: 3px;
    }

    .search-row .input-group {
        border: 0;
    }

    .search-row .input-group-btn button {
        border: 1px solid #eee;
        background-color: transparent;
    }

    .search-row .input-group-btn:last-child button:last-child {
        border-top-right-radius: 3px;
        border-bottom-right-radius: 3px;
    }

    .search-row .form-group {
        display: flex;
    }

    .btn.has-search-value {
        color: #06c;
    }
</style>