<script lang="ts">
    import {onDestroy, onMount} from 'svelte';
    import Sortable from 'sortablejs'
    import BaseLayout from './BaseLayout.svelte';
    import type Button from '../../admin/layouts/interfaces/Button';
    import type Params from "./interfaces/Params";
    import type KeyValue from "./interfaces/KeyValue";
    import type Item from "./interfaces/Item";
    import {Language} from "../../../utils/Language";
    import {Notifier} from "../../../utils/Notifier";

    let layoutElement: HTMLElement

    let sortableEnabled: Sortable;
    let sortableDisabled: Sortable;

    export let params: Params;
    export let enabledItems: Item[] = [];
    export let disabledItems: Item[] = [];

    export let buttonList: Button[]
    export let loadData: Function;
    export let refresh: Function = () => {};

    export let editItem: Function;

    export let fieldsInGroup: KeyValue;

    let baseLayout: BaseLayout;

    $: calculateFieldsInGroup(enabledItems)

    function calculateFieldsInGroup(enabledItems: Item[]) {
        let inGroup = false;
        let inGroupValues: KeyValue = {}
        enabledItems.forEach((item) => {
            if (item.isGroup) {
                inGroup = item.name !== ''
                return;
            }
            if (inGroup) {
                inGroupValues[item.name] = true;
            }
        });
        fieldsInGroup = inGroupValues
    }

    onMount(() => {
        initializeSortable();
    });

    onDestroy(() => {
        if (sortableEnabled) sortableEnabled.destroy();
        if (sortableDisabled) sortableDisabled.destroy();
    });


    function initializeSortable(): void {
        const options: Sortable.Options = {
            group: "fields",
            draggable: 'li',
            animation: 150
        };

        sortableEnabled = Sortable.create(layoutElement.querySelector('ul.enabled'), {
            ...options,
            onEnd: function (evt) {
                if (evt.to.closest('.connected').classList.contains('enabled')) {
                    const [movedItem] = enabledItems.splice(evt.oldIndex, 1);
                    enabledItems.splice(evt.newIndex, 0, movedItem);
                    for (let i = evt.newIndex; i < enabledItems.length; i++) {
                        enabledItems[i].sortOrder = (i > 0 ? enabledItems[i - 1].sortOrder : 0) + i;
                    }
                    calculateFieldsInGroup(enabledItems);
                    refresh();
                } else {
                    const movedItem = enabledItems[evt.oldIndex]
                    if (movedItem.canDisabled === false) {
                        refresh()
                        return;
                    }
                    enabledItems.splice(evt.oldIndex, 1)
                    disabledItems.splice(evt.newIndex, 0, movedItem)
                    refresh()
                }
            }
        });
        sortableDisabled = Sortable.create(layoutElement.querySelector('ul.disabled'), {
            ...options,
            onEnd: function (evt) {
                if (evt.to.closest('.connected').classList.contains('disabled')) {
                    const [movedItem] = disabledItems.splice(evt.oldIndex, 1);
                    disabledItems.splice(evt.newIndex, 0, movedItem);
                } else {
                    const movedItem = disabledItems[evt.oldIndex]
                    disabledItems.splice(evt.oldIndex, 1)
                    enabledItems.splice(evt.newIndex, 0, movedItem)
                    for (let i = evt.newIndex; i < enabledItems.length; i++) {
                        enabledItems[i].sortOrder = (i > 0 ? enabledItems[i - 1].sortOrder : 0) + i;
                    }
                    calculateFieldsInGroup(enabledItems);
                    refresh();
                }
            }
        });
    }


    export let fetch = () => {
        let data = [];
        let inGroup = false;
        for (const item of enabledItems) {
            if (item.isGroup) {
                inGroup = !item.groupEnd;
                data.push({
                    id: item.id,
                    name: item.name,
                    color: item.color,
                    iconClass: item.iconClass,
                    items: []
                });
                continue;
            }

            if (inGroup) {
                data[data.length - 1].items.push(item.name);
            } else {
                data.push(item.name)
            }
        }
        let groupBegan = false;
        data = data.filter(item => {
            if(typeof item === 'object') {
                if(item.name !== '' && !item.items.length) {
                    return false;
                }

                if(item.name !== '' && item.items.length) {
                    groupBegan = true;
                    return true;
                }

                if(item.name === '' && groupBegan) {
                    groupBegan = false
                    return true;
                }

                if(item.name === '' && !groupBegan) {
                    return false
                }
            }

            return true;
        })

        return data;
    }

    function removeItem(item: Item): void {
        enabledItems.splice(enabledItems.findIndex(f => f.id === item.id), 1)
        refresh();
    }

    function validate(itemToSaved: Array): boolean {
        if (itemToSaved.length === 0) {
            Notifier.notify('Menu cannot be empty', 'error');
            return false;
        }
        return true;
    }

    function toDom(str: string): string {
        return str.toLowerCase();
    }

    function prop(obj: any, key: string): any {
        return obj[key];
    }

    function getDataAttributeProps(item: Field): any {
        let dataAttributes = {};
        ['name', 'id'].forEach(attr => {
            if (prop(item, attr) != null) {
                dataAttributes[`data-${toDom(attr)}`] = prop(item, attr);
            }
        })
        return dataAttributes;
    }
</script>

<BaseLayout
        bind:this={baseLayout}
        {params}
        {validate}
        {fetch}
        {loadData}
        {buttonList}
>

    <div id="layout" class="row" bind:this={layoutElement}>
        <div class="col-sm-5">
            <div class="well">
                <header>{Language.translate('Selected', 'labels', 'Admin')}</header>
                <ul class="enabled connected">
                    {#each enabledItems.sort((a, b) => a.sortOrder - b.sortOrder) as item (item.name)}
                        <li {...getDataAttributeProps(item)} class="{item.isGroup ? 'group': ''} {item.groupEnd ? 'end' : ''} { (fieldsInGroup[item.name] && !item.isGroup) ? 'in-group': ''}">
                            <div class="left">
                                <label title="{item.label}">{item.label}</label>
                            </div>
                            <div class="right">
                                {#if item.canEdit}
                                    <a href="javascript:" data-action="editField" class="edit-field"
                                       on:click={()=>editItem(item)}>
                                        <i class="fas fa-pencil-alt fa-sm"></i>
                                    </a>
                                {/if}
                                {#if item.canRemove}

                                    <a href="javascript:" data-action="removeField" class="remove-field"
                                       on:click={()=>removeItem(item)}
                                    >
                                        <i class="fas fa-times"></i>
                                    </a>
                                {/if}
                            </div>

                        </li>
                    {/each}
                </ul>
            </div>
        </div>
        <div class="col-sm-2"></div>
        <div class="col-sm-5">
            <div class="well">
                <header>{Language.translate('Available', 'labels', 'Admin')}</header>
                <ul class="disabled connected">
                    {#each disabledItems as item (item.name)}
                        <li {...getDataAttributeProps(item)}>
                            <div class="left">
                                <label title="{item.label}">{item.label}</label>
                            </div>
                        </li>
                    {/each}
                </ul>
            </div>
        </div>
    </div>
</BaseLayout>

<style>
    header {
        font-weight: bold;
    }

    ul {
        width: 100%;
        min-height: 100px;
        padding: 0;
        list-style-type: none;
        margin: 0;
    }

    ul li {
        list-style: none;
        border: 1px solid #CCC;
        margin: 5px;
        padding: 5px;
        height: 32px;
    }

    ul > li .left {
        float: left;
        width: 100%;
    }

    ul > li {
        background-color: #FFF;
    }

    ul.enabled > li .right {
        float: right;
    }

    ul.disabled > li .right {
        display: none;
    }

    label {
        font-weight: normal;
        display: block;
        width: 100%;
        text-overflow: ellipsis;
        margin-bottom: 5px;
        white-space: nowrap;
        overflow: hidden;
    }

    .enabled .in-group, .group.end {
        margin-left: 20px;
    }

    .group {
        position:relative;
        color: black;
    }

    .group label {
        font-weight: bold;
    }

    .group.end {
        padding: 15px 50px 0 10px;
    }

    .group.end div.left {
        border-top: 1px solid #ccc;
    }

    .group .right {
        position: absolute;
        top: 5px;
        right: 5px;
    }

    .group .right a {
        position: relative;
    }

    .group .right a .fa-pencil-alt {
        position: absolute;
        top: 2px;
        right: 5px;
    }
</style>