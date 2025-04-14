<script lang="ts">
    import {Storage} from "../../utils/Storage";
    import {onMount} from "svelte";
    import {Language} from "../../utils/Language";
    import Item from './interfaces/Item'
    import BaseSidebar from "./BaseSidebar.svelte";
    import QueryBuillder from "./search-filter/QueryBuillder.svelte";

    export let scope: string;
    export let mode: string;
    export let minWidth: number = 300;
    export let maxWidth: number = 600;
    export let currentWidth: number = minWidth;
    export let loadSummary: Function;

    export let loadActivities: Function;

    export let isCollapsed: boolean = false;

    export let hasStream: boolean = false;

    export let collection;
    export let createView;

    $: scopeKey = scope + mode;

    let isPin = true;
    let streamView: Object;
    let items: Item[] = [];
    let activeItem: Item;

    if (mode !== 'list') {
        items.push({
            name: "summary",
            label: Language.translate('Summary'),
        });

        activeItem = items[0];
    }

    function setActiveItem(item: any) {
        if (activeItem && activeItem.name === item.name) {
            return
        }

        activeItem = item;

        if (activeItem.name === 'activities') {
            refreshActivities()
        }

        Storage.set('right-side-view-active-item', scopeKey, activeItem.name);
    }

    function refreshActivities() {

        if (isCollapsed) {
            return;
        }

        if (streamView == null) {
            loadActivities((view) => {
                streamView = view;
            });
        } else {
            streamView?.refresh();
        }
    }

    function onSidebarResize(e: CustomEvent): void {
        Storage.set('rightSideView', scopeKey, currentWidth.toString());
    }

    function onSidebarPin(e: CustomEvent): void {
        Storage.set('right-side-view-pin', scopeKey, isPin ? 'pin' : 'not-pinned');
    }

    function onSidebarCollapse(e: CustomEvent): void {
        Storage.set('right-side-view-collapse', scopeKey, isCollapsed ? 'collapsed' : 'expanded');
        if (activeItem.name === 'activities') {
            refreshActivities();
        }
    }

    onMount(() => {
        const savedWidth = Storage.get('rightSideView', scopeKey);
        if (savedWidth) {
            currentWidth = parseInt(savedWidth) || minWidth;
        }

        let collapseState = Storage.get('right-side-view-collapse', scopeKey);
        if ((!collapseState && mode === 'list') || window.innerWidth <= 768) {
            isCollapsed = true;
        } else {
            isCollapsed = (collapseState === 'collapsed');
        }

        isPin = Storage.get('right-side-view-pin', scopeKey) !== 'not-pinned';

        if (mode !== 'list') {
            loadSummary();
        }

        if (mode === 'list') {
            items = [
                ...items,
                {
                    name: "filter",
                    label: Language.translate('filter')
                }
            ]
        }

        if (hasStream) {
            items = [
                ...items,
                {
                    "name": "activities",
                    "label": Language.translate('Activities')
                }]
        }

        let itemName = Storage.get('right-side-view-active-item', scopeKey);

        if (itemName && items.map(i => i.name).includes(itemName)) {
            setActiveItem(items.find(i => i.name === itemName));
        }

        if (!activeItem) {
            setActiveItem(items[0]);
        }
    });

</script>

<BaseSidebar position="right" className="right-side-view" bind:isCollapsed={isCollapsed} bind:isPinned={isPin}
             bind:width={currentWidth} {minWidth} {maxWidth} on:sidebar-resize={onSidebarResize}
             on:sidebar-collapse={onSidebarCollapse} on:sidebar-pin={onSidebarPin}>
    <div class="content">
        <div class="btn-group">
            {#each items as item}
                {#if item.name !== activeItem.name}
                    <a href="javascript:" on:click={()=>setActiveItem(item)}
                       class="btn btn-link item">
                        {item.label}
                    </a>
                {/if}
            {/each}
        </div>

        <div class="sidebar-header">
            <h5>{activeItem?.label ?? ''}</h5>
            <div class="layout-editor-container" class:hidden={activeItem?.name !== 'summary'}></div>
        </div>


        <div class="filter" class:hidden={activeItem?.name !== 'filter'}>
            <QueryBuillder scope={scope} collection={collection} createView={createView}></QueryBuillder>
        </div>

        <div class="summary" class:hidden={activeItem?.name !== 'summary'}>
            <img class="preloader"  src="client/img/atro-loader.svg" alt="loader">
        </div>

        <div class="activities" class:hidden={activeItem?.name !== 'activities'}>
            <img class="preloader"  src="client/img/atro-loader.svg" alt="loader">
        </div>
    </div>
</BaseSidebar>

<style>
    .preloader {
        height: 12px;
        margin-top: 5px;
    }
    .content .btn-group {
        display: flex;
    }

    .btn-group .item {
        padding: 4px 20px 4px 0;
        color: #333;
        text-decoration: underline;
    }

    .btn-group .item:hover:not(.active) {
        color: #2895ea85;
    }

    :global(.right-side-view .row .cell .field) {
        padding-bottom: 6px;
        border-bottom: 1px solid var(--secondary-border-color);
    }

    :global(.right-side-view .panel-heading .panel-title .collapser) {
        display: none;
    }

    :global(.dropdown-menu.textcomplete-dropdown) {
        z-index: 1300 !important;
    }

    .activities :global(.panel-stream .panel-title),
    .activities :global(.panel-stream .panel-heading .btn-group) {
        display: none;
    }

    .activities :global(.stream-details-container .panel) {
        background-color: transparent;
    }

    :global(.right-side-view .field .attachment-preview) {
        display: block;
    }

    :global(.right-side-view .field .attachment-preview img) {
        display: block;
        margin: 0 auto;
    }

    :global(.right-side-view .layout-editor-container .dropdown-menu.pull-right) {
        right: auto;
    }

    .activities :global( .dropdown-menu.pull-right) {
        right: 0;
    }

    .activities :global(.panel-default > .panel-heading),
    .summary :global(.panel-default > .panel-heading) {
        background-color: transparent;
        border-bottom: transparent;
        display: flex;
        flex-direction: row-reverse;
        padding-right: 0;
        height: auto !important;
    }

    .activities :global(.panel-title),
    .summary :global(.panel-title) {
        text-transform: none;
        font-size: 12px;
        color: #999;
    }

    :global(.right-side-view .panel-heading > .btn-group) {
        right: 0;
    }

    .activities :global(.panel-default),
    .summary :global(.panel-default) {
        background-color: inherit;
    }

    .activities :global(.panel-body),
    .summary :global(.panel-body) {
        padding-top: 0;
        padding-left: 0;
        padding-right: 0;
    }

    .activities :global(.panel-body > div > .list),
    .summary :global(.panel-body > div > .list) {
        background-color: transparent;
        margin-left: 0;
        margin-right: 0;
        overflow-x: visible;
    }

    .activities :global(.panel-body .list-group-item) {
        background-color: transparent;
        padding-left: 0;
        padding-right: 0;
    }

    .activities :global(.panel-body > div > .list > .list-group),
    .summary :global(.panel-body > div > .list > .list-group) {
        padding-left: 5px;
        padding-right: 5px;
        background: transparent;
    }

    .activities :global(.panel-heading) {
        display: none !important;
    }

    .activities :global(table.table),
    .summary :global(table.table) {
        background-color: transparent;
    }

    :global(.right-side-view .panel-body > div > .list > table td:first-child),
    :global(.right-side-view .panel-body > div > .list > table th:first-child),
    :global(.right-side-view .panel-body > div > .group > .list-container > .list > table td:first-child),
    :global(.right-side-view .panel-body > div > .group > .list-container > .list > table th:first-child) {
        padding-left: 0 !important;
    }

    :global(.right-side-view .panel .list > table.table.full-table td.cell[data-name="buttons"]:not(.fixed-button)) {
        padding-right: 0;
    }

    :global(.right-side-view .list > table td.cell[data-name="buttons"] > .list-row-buttons > .dropdown-toggle) {
        background-color: transparent;
    }
</style>