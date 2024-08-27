<script lang="ts">
    import { onMount } from 'svelte';
    import RowsLayout from './RowsLayout.svelte';
    import type { Field, LayoutItem } from './interfaces';

    export let scope: string;
    export let type: string = 'list';

    let rowsLayout: RowsLayout;
    let enabledFields: Field[] = [];
    let disabledFields: Field[] = [];
    let rowLayout: LayoutItem[] = [];
    let editable: boolean = true;

    const layoutDisabledParameter: string = 'layoutListDisabled';
    const dataAttributeList: string[] = ['id', 'name', 'width', 'widthPx', 'link', 'notSortable', 'align', 'view', 'customLabel'];
    const ignoreList: string[] = [];
    const ignoreTypeList: string[] = [];

    onMount(() => {
        loadLayout();
    });

    function loadLayout(): void {
        helper.modelFactory.create(scope, (model) => {
            helper.layoutManager.get(scope, type, (layout) => {
                readDataFromLayout(model, layout);
            }, false);
        });
    }

    function readDataFromLayout(model: any, layout: LayoutItem[]): void {
        const allFields = Object.keys(model.defs.fields).filter(field =>
            checkFieldType(model.getFieldParam(field, 'type')) && isFieldEnabled(model, field)
        ).sort((v1, v2) =>
            helper.language.translate(v1, 'fields', scope).localeCompare(helper.language.translate(v2, 'fields', scope))
        );

        const enabledFieldsList: string[] = [];
        const labelList: string[] = [];
        const duplicateLabelList: string[] = [];

        enabledFields = layout.map(item => {
            const label = helper.language.translate(item.name, 'fields', scope);
            if (labelList.includes(label)) {
                duplicateLabelList.push(label);
            }
            labelList.push(label);
            enabledFieldsList.push(item.name);
            return { name: item.name, label };
        });

        disabledFields = allFields.filter(field => !enabledFieldsList.includes(field)).map(field => {
            const label = helper.language.translate(field, 'fields', scope);
            if (labelList.includes(label)) {
                duplicateLabelList.push(label);
            }
            labelList.push(label);
            const o: Field = { name: field, label };
            const fieldType = helper.metadata.get(['entityDefs', scope, 'fields', field, 'type']);
            if (fieldType && helper.metadata.get(['fields', fieldType, 'notSortable'])) {
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
            label: enabledFields.find(field => field.name === item.name)?.label || helper.language.translate(item.name, 'fields', scope)
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
        {enabledFields}
        {disabledFields}
        {rowLayout}
        {editable}
/>