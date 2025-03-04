<script lang="ts">
    import {onDestroy, onMount, tick} from 'svelte';
    import BaseLayout from './BaseLayout.svelte';
    import Field from "./interfaces/Field"
    import Params from "./interfaces/Params"
    import LayoutItem from "./interfaces/LayoutItem"
    import {Language} from "../../../utils/Language";
    import Sortable from 'sortablejs'
    import {Notifier} from "../../../utils/Notifier";
    import Group from "./interfaces/Group";

    let layoutElement: HTMLElement

    let sortableEnabled: Sortable;
    let sortableDisabled: Sortable[];

    export let params: Params;
    export let selectedFields: Field[] = [];
    export let availableGroups: Group[] = [];
    export let loadLayout: Function;

    let baseLayout: BaseLayout;

    onDestroy(() => {
        if (sortableEnabled) sortableEnabled.destroy();
        if (sortableDisabled) sortableDisabled.forEach(sortable => sortable.destroy());
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
                const ul = evt.to.closest('.connected')
                if (ul.classList.contains('enabled')) {
                    const [movedItem] = selectedFields.splice(evt.oldIndex, 1);
                    selectedFields.splice(evt.newIndex, 0, movedItem);
                } else {
                    const movedItem = selectedFields[evt.oldIndex]
                    selectedFields.splice(evt.oldIndex, 1)

                    availableGroups = availableGroups.map(group => {
                        if (group.name === ul.attributes['data-name'].value) {
                            group.fields.splice(evt.newIndex, 0, movedItem)
                        }
                        return group
                    })
                }
                selectedFields = [...selectedFields];
            }
        });

        sortableDisabled = []
        for (let ul of layoutElement.querySelectorAll('ul.disabled')) {
            const sortable = Sortable.create(ul, {
                ...options,
                onEnd: function (evt) {
                    const toUl = evt.to.closest('.connected')
                    let movedItem = null
                    if (toUl.classList.contains('disabled')) {
                        // remove from old Group
                        for (let group of availableGroups) {
                            if (group.name === ul.attributes['data-name'].value) {
                                [movedItem] = group.fields.splice(evt.oldIndex, 1);
                                break
                            }
                        }
                        if (movedItem) {
                            for (let group of availableGroups) {
                                if (group.name === toUl.attributes['data-name'].value) {
                                    group.fields.splice(evt.newIndex, 0, movedItem)
                                    break
                                }
                            }
                        }
                    } else {
                        availableGroups = availableGroups.map(group => {
                            if (group.name === evt.from.closest('ul').attributes['data-name'].value) {
                                [movedItem] = group.fields.splice(evt.oldIndex, 1)
                            }
                            return group
                        })
                        selectedFields.splice(evt.newIndex, 0, movedItem)
                        selectedFields = [...selectedFields]
                    }

                    console.log(availableGroups, selectedFields);
                }
            });
            sortableDisabled.push(sortable)
        }

    }

    function editField(field): void {
        params.openEditDialog(field, params.scope, params.dataAttributeList, params.dataAttributesDefs, (attributes) => {
            selectedFields = selectedFields.map(item => {
                if (item.name === field.name) {
                    for (let key in attributes) {
                        item[key] = attributes[key]
                    }
                }
                return item
            })
        });
    }

    export let fetch = () => {
        return selectedFields;
    }

    function validate(layout: LayoutItem[]): boolean {
        if (layout.length === 0) {
            Notifier.notify('Layout cannot be empty', 'error');
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

    function getDataAttributeProps(item: LayoutItem): any {
        const dataAttributes = {};
        params.dataAttributeList.forEach(attr => {
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
        {loadLayout}
        on:ready={initializeSortable}
>

    <div id="layout" class="row" bind:this={layoutElement}>
        <div class="col-sm-5">
            <div class="well">
                <header>{Language.translate('Selected', 'labels', 'Admin')}</header>
                <div class="rows-wrapper">
                    <ul class="enabled connected">
                        {#each selectedFields.sort((a, b) => a.sortOrder - b.sortOrder) as item (item.name)}
                            <li {...getDataAttributeProps(item)}>
                                <div class="left">
                                    <label>{item.label}</label>
                                </div>
                                {#if params.editable}
                                    <div class="right">
                                        <a href="javascript:" data-action="editField" class="edit-field"
                                           on:click={()=>editField(item)}>
                                            <i class="fas fa-pencil-alt fa-sm"></i>
                                        </a>
                                    </div>
                                {/if}
                            </li>
                        {/each}
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-sm-1" style="width: 35px"></div>
        <div class="col-sm-5">
            <div class="well">
                <header>{Language.translate('Available', 'labels', 'Admin')}</header>
                <div class="rows-wrapper">
                    {#each availableGroups as group (group.name)}
                        <div class:group={availableGroups.length>1}>
                            {#if availableGroups.length > 1 }
                                <span class="title">{Language.translate(group.name, 'scopeNames')}</span>
                            {/if}
                            <ul class="disabled connected" data-name="{group.name}">
                                {#each group.fields as field (field.name)}
                                    <li {...getDataAttributeProps(field)}>
                                        <div class="left">
                                            <label>{field.label}</label>
                                        </div>
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/each}
                </div>
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
        margin: 5px 0;
        padding: 5px;
        height: 32px;
    }


    ul > li .left {
        float: left;
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
    }

    .enabled li a.edit-field {
        display: none;
    }

    .enabled li:hover a.edit-field {
        display: block;
    }

    .well {
        height: 100%;
        border: 1px solid #ededed;
        border-radius: 3px;
    }

    .well .rows-wrapper {
        overflow-x: clip;
        overflow-y: auto;
        padding-right: 5px;
        margin-right: -5px;
        max-height: 70vh;
    }

    .group {
        border: 1px solid #ededed;
        border-radius: 2px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .group .title {
        font-weight: bold;
    }

    #layout {
        height: 100%;
    }

    #layout > * {
        height: 100%;
    }
</style>