<script lang="ts">
    import {onMount} from 'svelte';
    import BaseLayout from './BaseLayout.svelte';
    import type {Button, Field, LayoutItem} from './interfaces';
    import {Language} from "../../../utils/Language";

    export let scope: string;
    export let type: string;
    export let enabledFields: Field[] = [];
    export let disabledFields: Field[] = [];
    export let rowLayout: LayoutItem[] = [];
    export let editable: boolean = false;

    let baseLayout: BaseLayout;
    let dataAttributeList: string[] = [];
    let buttonList: Button[] = [];

    onMount(() => {
        initializeSortable();
    });

    function initializeSortable(): void {
        // Implement sortable initialization here
        // This might use a library like Sortable.js
    }

    function editField(event: Event): void {
        const target = event.target as HTMLElement;
        const listItem = target.closest('li');
        if (!listItem) return;

        const data: LayoutItem = {};
        dataAttributeList.forEach(attr => {
            data[attr] = listItem.dataset[attr] || null;
        });
        baseLayout.openEditDialog(data);
    }

    function fetch(): LayoutItem[] {
        const layout: LayoutItem[] = [];
        document.querySelectorAll("#layout ul.enabled > li").forEach((el: Element) => {
            const o: LayoutItem = {} as LayoutItem;
            dataAttributeList.forEach(attr => {
                const value = (el as HTMLElement).dataset[attr];
                if (value) {
                    o[attr] = value;
                }
            });
            layout.push(o);
        });
        return layout;
    }

    function validate(layout: LayoutItem[]): boolean {
        if (layout.length === 0) {
            helper.notify('Layout cannot be empty', 'error');
            return false;
        }
        return true;
    }

    function handleSave(): void {
        const layout = fetch();
        if (validate(layout)) {
            baseLayout.save(layout);
        }
    }

    function toDom(str: string): string {
        return str.toLowerCase();
    }

    function prop(obj: any, key: string): any {
        return obj[key];
    }

    function getDataAttributeProps(item: LayoutItem): any {
        dataAttributes = {};
        dataAttributeList.forEach(attr => {
            dataAttributes[`data-${toDom(attr)}`] = prop(item, attr);
        })
        return dataAttributes;
    }
</script>

<BaseLayout
        bind:this={baseLayout}
        {scope}
        {type}
        on:save={handleSave}
        let:buttonList
>
    <div class="button-container">
        {#each buttonList as button}
            <button on:click={() => baseLayout[button.name]()}
                    class={button.style}>
                {button.label}
            </button>
        {/each}
    </div>

    <div id="layout" class="row">
        <div class="col-sm-5">
            <div class="well">
                <header>{Language.translate('Enabled', 'labels', 'Admin')}</header>
                <ul class="enabled connected">
                    {#each rowLayout as item}
                        <li draggable="true" {...getDataAttributeProps(item, dataAttributeList)}>
                            <div class="left">
                                <label>{item.label}</label>
                            </div>
                            {#if editable}
                                <div class="right">
                                    <a href="javascript:" data-action="editField" class="edit-field"
                                       on:click={editField}>
                                        <i class="fas fa-pencil-alt fa-sm"></i>
                                    </a>
                                </div>
                            {/if}
                        </li>
                    {/each}
                </ul>
            </div>
        </div>
        <div class="col-sm-5">
            <div class="well">
                <header>{Language.translate('Disabled', 'labels', 'Admin')}</header>
                <ul class="disabled connected">
                    {#each disabledFields as field}
                        <li draggable="true" {...getDataAttributeProps(field)}>
                            <div class="left">
                                <label>{field.label}</label>
                            </div>
                            {#if editable}
                                <div class="right">
                                    <a href="javascript:" data-action="editField" class="edit-field"
                                       on:click={editField}>
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
</BaseLayout>

<style>
    #layout ul {
        width: 100%;
        min-height: 100px;
        padding: 0;
        list-style-type: none;
        margin: 0;
    }

    #layout ul li {
        list-style: none;
        border: 1px solid #CCC;
        margin: 5px;
        padding: 5px;
        height: 32px;
    }

    #layout header {
        font-weight: bold;
    }

    #layout ul > li .left {
        float: left;
    }

    #layout ul > li {
        background-color: #FFF;
    }

    #layout ul.enabled > li .right {
        float: right;
    }

    #layout ul.disabled > li .right {
        display: none;
    }

    #layout ul > li .width {
        font-size: small;
    }

    #layout ul.disabled > li .width {
        display: none;
    }

    #layout label {
        font-weight: normal;
    }

    .enabled li a.edit-field {
        display: none;
    }

    .enabled li:hover a.edit-field {
        display: block;
    }
</style>