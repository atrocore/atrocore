<script lang="ts">
    import {onDestroy, onMount} from 'svelte';
    import BaseLayout from './BaseLayout.svelte';
    import Field from "./interfaces/Field"
    import Params from "./interfaces/Params"
    import LayoutItem from "./interfaces/LayoutItem"
    import {Language} from "../../../utils/Language";
    import Sortable from 'sortablejs'
    import {Notifier} from "../../../utils/Notifier";

    let layoutElement: HTMLElement

    let sortableEnabled: Sortable;
    let sortableDisabled: Sortable;

    export let params: Params;
    export let enabledFields: Field[] = [];
    export let disabledFields: Field[] = [];
    export let loadLayout: Function;

    let baseLayout: BaseLayout;

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
                    const [movedItem] = enabledFields.splice(evt.oldIndex, 1);
                    enabledFields.splice(evt.newIndex, 0, movedItem);
                } else {
                    const movedItem = enabledFields[evt.oldIndex]
                    enabledFields.splice(evt.oldIndex, 1)
                    disabledFields.splice(evt.newIndex, 0, movedItem)
                    disabledFields = [...disabledFields]
                }
                enabledFields = [...enabledFields];
            }
        });
        sortableDisabled = Sortable.create(layoutElement.querySelector('ul.disabled'), {
            ...options,
            onEnd: function (evt) {
                if (evt.to.closest('.connected').classList.contains('disabled')) {
                    const [movedItem] = disabledFields.splice(evt.oldIndex, 1);
                    disabledFields.splice(evt.newIndex, 0, movedItem);
                } else {
                    const movedItem = disabledFields[evt.oldIndex]
                    disabledFields.splice(evt.oldIndex, 1)
                    enabledFields.splice(evt.newIndex, 0, movedItem)
                    enabledFields = [...enabledFields]
                }
                disabledFields = [...disabledFields];
            }
        });
    }

    function editField(field): void {
        params.openEditDialog(field, params.scope, params.dataAttributeList, params.dataAttributesDefs, (attributes) => {
            enabledFields = enabledFields.map(item => {
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
        return enabledFields;
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
>

    <div id="layout" class="row" bind:this={layoutElement}>
        <div class="col-sm-5">
            <div class="well">
                <header>{Language.translate('Selected', 'labels', 'Admin')}</header>
                <div class="rows-wrapper">
                    <ul class="enabled connected">
                        {#each enabledFields.sort((a, b) => a.sortOrder - b.sortOrder) as item (item.name)}
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
                    <ul class="disabled connected">
                        {#each disabledFields as field (field.name)}
                            <li {...getDataAttributeProps(field)}>
                                <div class="left">
                                    <label>{field.label}</label>
                                </div>
                            </li>
                        {/each}
                    </ul>
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
        display: flex;
        flex-direction: column;
        max-height: 70vh;
    }

    .well .rows-wrapper {
        display: flex;
        flex-direction: column;
        flex: 1;
        overflow-x: clip;
        overflow-y: auto;
        padding-right: 5px;
        margin-right: -5px;
    }

    .well .rows-wrapper ul {
        flex: 1;
    }

    #layout {
        height: 100%;
    }

    #layout > * {
        height: 100%;
    }
</style>