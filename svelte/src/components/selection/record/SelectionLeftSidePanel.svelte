<script lang="ts">

    import {onMount} from "svelte";
    import {Storage} from "../../../utils/Storage";
    import BaseSidebar from "../../record/BaseSidebar.svelte";
    import {GroupedItems, Item} from "./interfaces/Item";
    import {Language} from "../../../utils/Language";

    export let scope: string;
    export let records: Item[];
    export let selectedIds: string[];
    export let selectionViewMode: string = 'standard'

    export let onItemClicked: Function = (id: string) => {
    };

    export let onSelectAll: Function = (entityType: string) => {
    };

    export let onUnSelectAll: Function = (entityType: string) => {
    };

    $: selectedIdSet = new Set(selectedIds);

    $: hasSelectedByType = calculateSelectedStatus(records, selectedIdSet);

    function calculateSelectedStatus(recs: Record<any, any>[], ids: Set<string>) {
        const status: Record<string, boolean> = {};
        recs.forEach(r => {
            if (!status[r.entityType] && ids.has(r.id)) {
                status[r.entityType] = true;
            }
        });
        return status;
    }

    let isPinned: boolean = true;
    let data: GroupedItems = {};

    export  function setSelectedIds(ids: string[]) {
        selectedIds = ids;
    }

    export function setSelectionViewMode(value: string) {
        selectionViewMode = value;
    }

    export function setRecords(value: Item[]) {
        records = value;
        data = {};
        records.forEach((record: any) => {
            if (!data[record.entityType]) {
                data[record.entityType] = [];
            }
            data[record.entityType].push(record);
        });
    }


    function handledSelectAllButton(entityType: string): void {
        if(hasSelectedByType[entityType]) {
            onUnSelectAll(entityType);
        }else{
            onSelectAll(entityType);
        }
    }

    onMount(() => {

        setRecords(records);
    });

</script>


<div class="records">
    {#each Object.keys(data).sort((a, b) => a.localeCompare(b)) as entityType}
        <div>
            <div class="title">
                <span class="title">{entityType}</span>
                {#if selectionViewMode !== 'standard'}
                    <button class="small filter-button" on:click={() => handledSelectAllButton(entityType)}>{ hasSelectedByType[entityType] ? Language.translate('hideAll') : Language.translate('selectAll')}</button>
                {/if}
            </div>

            <ul>
                {#each data[entityType] as record }
                    <li title="{record.name}">
                        <a href="#{record.entityType}/view/{record.id}" target="_blank" on:click={(e) => { onItemClicked(e, record.id) }}
                           class:active="{selectionViewMode !== 'standard' && selectedIds.includes(record.id)}"><i class="ph" class:ph-eye={selectedIds.includes(record.id)} class:ph-eye-slash={!selectedIds.includes(record.id)}></i>{record.name}</a>
                    </li>
                {/each}
            </ul>
        </div>
    {/each}
</div>

<style>
    .title {
        display: flex;
        justify-content: space-between;
    }
    .records {
        margin-top: 20px;
    }

    div .title {
        font-size: 16px;
        font-weight: 500;
    }

    div ul {
        list-style: none;
        padding: 8px 0;
    }

    div ul li {
        padding: 0;
    }

    div ul li a {
        text-overflow: ellipsis;
        white-space: nowrap;
        overflow: hidden;
        display: inline-block;
        max-width: 100%;
        text-decoration: none;
        color: #777;
        line-height: normal;
    }

    div ul li a > i {
        margin-inline-end: .5em;
    }

    div ul li a:hover, div ul li a:focus {
        text-decoration: none;
    }

    div ul li a.active {
        color: var(--primary-font-color);
    }
</style>