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


    function reset() {
        searchValue = "";
        search();
    }

    function updateCollection() {
        searchManager.collection.reset();
        Notifier.notify(Language.translate('loading', 'messages'));

        searchManager.collection.where = searchManager.getWhere();

        searchManager.collection.fetch().then(() => window.Backbone.trigger('after:search', searchManager.collection));
    }
</script>


<div class="search-container">
    <div class="row search-row">
        <div class="form-group ">
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
                                class="btn btn-default "
                                data-original-title="Reset"
                                aria-expanded="false"
                                data-tippy="true"
                                on:click={reset}
                        >
                            <svg class="icon"><use href="client/img/icons/icons.svg#close"></use></svg>
                        </button>
                    {/if}
                    <button
                            type="button"
                            class="btn btn-primary"
                            data-original-title="Search"
                            aria-expanded="false"
                            data-tippy="true"
                            on:click={search}
                    >
                        <svg class="icon"><use href="client/img/icons/icons.svg#search"></use></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>


<style>
    .search-container {
        flex-basis: auto !important;
    }

    .search-container .search-row {
        padding-bottom: 0;
    }

    .search-container .search {
        flex-grow: 1;
        width: 250px;
    }

    .search-row .input-group {
        border: 1px solid #eee;
        border-radius: 3px;
    }

    .search-row .input-group-btn button {
        border: 0;
        border-left: 1px solid #eee;
        background-color: transparent;
        color: #333;
    }

    .search-row .form-group {
        display: flex;
    }

    .search-row .search input {
        background: transparent;
    }

</style>