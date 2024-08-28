<script lang="ts">
    import {onMount} from 'svelte';
    import RowsLayout from './RowsLayout.svelte';
    import type {Field, LayoutItem} from './interfaces';
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {ModelFactory} from "../../../utils/ModelFactory";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {Notifier} from "../../../utils/Notifier";

    export let scope: string;
    export let type: string = 'list';
    export let layoutProfileId: string;

    const dataAttributeList: string[] = ['id', 'name', 'width', 'widthPx', 'link', 'notSortable', 'align', 'view', 'customLabel'];
    export let layoutDisabledParameter: string = 'layoutListDisabled';


    export let afterRender: any;

    let rowsLayout: RowsLayout;
    let enabledFields: Field[] = [];
    let disabledFields: Field[] = [];
    let rowLayout: LayoutItem[] = [];
    let editable: boolean = true;
    const ignoreList: string[] = [];
    const ignoreTypeList: string[] = [];

    function loadLayout(): void {
        ModelFactory.create(scope, (model) => {
            Notifier.notify('Loading...')
            LayoutManager.get(scope, type, (layout) => {
                readDataFromLayout(model, layout);
                Notifier.notify(false)
                if (afterRender) afterRender()
            }, false);
        });
    }

    function readDataFromLayout(model: any, layout: LayoutItem[]): void {
        const allFields = Object.keys(model.defs.fields).filter(field =>
            checkFieldType(model.getFieldParam(field, 'type')) && isFieldEnabled(model, field)
        ).sort((v1, v2) =>
            Language.translate(v1, 'fields', scope).localeCompare(Language.translate(v2, 'fields', scope))
        );

        const enabledFieldsList: string[] = [];
        const labelList: string[] = [];
        const duplicateLabelList: string[] = [];

        enabledFields = layout.map(item => {
            const label = Language.translate(item.name, 'fields', scope);
            if (labelList.includes(label)) {
                duplicateLabelList.push(label);
            }
            labelList.push(label);
            enabledFieldsList.push(item.name);
            return {name: item.name, label};
        });

        disabledFields = allFields.filter(field => !enabledFieldsList.includes(field)).map(field => {
            const label = Language.translate(field, 'fields', scope);
            if (labelList.includes(label)) {
                duplicateLabelList.push(label);
            }
            labelList.push(label);
            const o: Field = {name: field, label};
            const fieldType = Metadata.get(['entityDefs', scope, 'fields', field, 'type']);
            if (fieldType && Metadata.get(['fields', fieldType, 'notSortable'])) {
                o.notSortable = true;
            }
            return o;
        });

        [enabledFields, disabledFields].forEach(fieldList => {
            fieldList.forEach(item => {
                if (duplicateLabelList.includes(item.label)) {
                    item.label += ` (${item.name})`;
                }
            });
        });

        rowLayout = layout.map(item => ({
            ...item,
            label: enabledFields.find(field => field.name === item.name)?.label || Language.translate(item.name, 'fields', scope)
        }));
    }

    function checkFieldType(type: string): boolean {
        return true;
    }

    function isFieldEnabled(model: any, name: string): boolean {
        if (ignoreList.includes(name)) {
            return false;
        }
        if (ignoreTypeList.includes(model.getFieldParam(name, 'type'))) {
            return false;
        }
        return !model.getFieldParam(name, 'disabled') && !model.getFieldParam(name, layoutDisabledParameter);
    }
</script>

<RowsLayout
        bind:this={rowsLayout}
        {scope}
        {type}
        {layoutProfileId}
        {enabledFields}
        {disabledFields}
        {rowLayout}
        {editable}
        {dataAttributeList}
        {loadLayout}
/>