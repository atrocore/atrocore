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
    import {Metadata} from "../../../utils/Metadata";
    import {Acl} from "../../../utils/Acl";
    import {UserData} from "../../../utils/UserData";

    let layoutElement: HTMLElement

    let sortableEnabled: Sortable;
    let sortableDisabled: Sortable[];

    export let params: Params;
    export let selectedFields: Field[] = [];
    export let nonRemovableFields: string[] = [];
    export let availableGroups: Group[] = [];
    export let loadLayout: Function;

    let baseLayout: BaseLayout;
    let hasAttributes = Metadata.get(['scopes', params.scope, 'hasAttribute']);

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
                    if (nonRemovableFields.includes(movedItem.name)) {
                        // cancel drop
                        if (evt.oldIndex >= evt.from.children.length) {
                            evt.from.appendChild(evt.item);
                        } else {
                            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                        }
                        return
                    }

                    selectedFields.splice(evt.oldIndex, 1)

                    let itemGroup = availableGroups.find(group => !group.prefix)

                    availableGroups.forEach(group => {
                        if (group.prefix) {
                            if (movedItem.name.startsWith(group.prefix)) {
                                itemGroup = group
                            }
                        }
                    })

                    if (itemGroup.name !== ul.attributes['data-name'].value) {
                        const correctUl = document.querySelector(`#layout ul.disabled[data-name="${itemGroup.name}"]`)
                        if (correctUl.children.length) {
                            correctUl.insertBefore(evt.item, correctUl.children[0]);
                        } else {
                            correctUl.appendChild(evt.item);
                        }
                        itemGroup.fields.unshift(movedItem)
                    } else {
                        itemGroup.fields.splice(evt.newIndex, 0, movedItem)
                    }
                    availableGroups = [...availableGroups]
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
                    console.log('ul disabled', evt)
                    if (toUl.classList.contains('disabled') ) {
                        if (toUl !== ul) {
                            // cancel drop
                            if (evt.oldIndex >= evt.from.children.length) {
                                evt.from.appendChild(evt.item);
                            } else {
                                evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                            }
                            return
                        }

                        for (let group of availableGroups) {
                            if (group.name === toUl.attributes['data-name'].value) {
                                [movedItem] = group.fields.splice(evt.oldIndex, 1);
                                if (movedItem) {
                                    group.fields.splice(evt.newIndex, 0, movedItem)
                                }
                                break
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

    function openLabelDialog(field) {
        params.openEditLabelDialog(params.scope, field.name, (label) => {
            selectedFields = selectedFields.map(item => {
                if (item.name === field.name) {
                    item.label = label
                }
                return item
            })
        });
    }

    function isAdmin() {
        let data = UserData.get();

        return !!(data && data.user && data.user.isAdmin);
    }

    function removeField(item) {
        // remove item from available list
        selectedFields.splice(selectedFields.indexOf(item), 1)
        selectedFields = [...selectedFields]

        // find group of item
        let itemGroup = availableGroups.find(group => !group.prefix)
        availableGroups.forEach(group => {
            if (group.prefix) {
                if (item.name.startsWith(group.prefix)) {
                    itemGroup = group
                }
            }
        })

        itemGroup.fields.unshift(item)
        availableGroups = [...availableGroups]
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

    function addAttribute() {
        params.openAddAttributesDialog(params.scope, fields => {
            fields.forEach(field => {
                let attribute = {
                    id: field.name,
                    name: field.name,
                    label: field.detailViewLabel || field.label,
                    attributeId: field.attributeId
                };

                if (field.channelName) {
                    attribute.label += ` / ${field.channelName}`;
                }

                let exists = false;
                selectedFields.forEach(item => {
                    if (item.name === attribute.name) {
                        exists = true;
                    }
                });
                if (!exists) {
                    availableGroups.forEach(group => {
                        group.fields.forEach(item => {
                            if (item.name === attribute.name) {
                                exists = true;
                            }
                        })
                    })
                }
                if (!exists) {
                    selectedFields = [
                        ...selectedFields,
                        attribute
                    ];
                }
            })
        });
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
                <header>
                    <h5>{Language.translate('Current Layout', 'LayoutManager')}</h5>
                    {#if hasAttributes && !['navigation', 'insights', 'relationships'].includes(params.type)}
                        <a href="#"
                           on:click|preventDefault={addAttribute}>{Language.translate('Add Attribute', 'LayoutManager')}</a>
                    {/if}
                </header>
                <div class="rows-wrapper">
                    <ul class="enabled connected">
                        {#each selectedFields.sort((a, b) => a.sortOrder - b.sortOrder) as item (item.name)}
                            <li {...getDataAttributeProps(item)}>
                                <div class="left">
                                    <label style={item.attributeId ? 'font-style: italic' : ''}>{item.label}</label>
                                </div>
                                {#if params.editable}
                                    <div class="right">
                                        {#if isAdmin() && !item.attributeId}
                                            <a href="javascript:" data-action="change-label" class="change-label"
                                               on:click|preventDefault={() => openLabelDialog(item)}>
                                                <i class="ph ph-globe-simple"></i>
                                            </a>
                                        {/if}
                                        <a href="javascript:" data-action="editField" class="edit-field"
                                           on:click={()=>editField(item)}>
                                            <i class="ph ph-pencil-simple"></i>
                                        </a>
                                        {#if !nonRemovableFields.includes(item.name)}
                                            <a href="javascript:" class="remove-field"
                                               on:click={()=>removeField(item)}>
                                                <i class="ph ph-x"></i>
                                            </a>
                                        {/if}
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
                <header>{Language.translate(['navigation', 'insights', 'relationships'].includes(params.type) ? 'Available Panels' : 'Available Fields', 'Admin')}</header>
                <div class="rows-wrapper">
                    {#each availableGroups as group (group.name)}
                        <div class:group={availableGroups.length>1}>
                            {#if availableGroups.length > 1 }
                                <span class="title">{Language.translate(group.name, 'scopeNames')}</span>
                            {/if}
                            <ul class="disabled connected" data-name="{group.name}">
                                {#each group.fields.sort((a, b) => a.label.localeCompare(b.label)) as field (field.name)}
                                    <li {...getDataAttributeProps(field)}>
                                        <div class="left">
                                            <label style={field.attributeId ? 'font-style: italic' : ''}>{field.label}</label>
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

    .enabled li a.edit-field,
    .enabled li a.remove-field,
    .enabled li a.change-label {
        display: none;
    }

    .enabled li:hover a.edit-field,
    .enabled li:hover a.remove-field,
    .enabled li:hover a.change-label {
        display: inline;
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

    #layout header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    #layout header h5 {
        margin-top: 0;
    }

    #layout header a {
        font-weight: normal;
    }
</style>