<script lang="ts">
    import RowsLayout from './RowsLayout.svelte';
    import Field from "./interfaces/Field"
    import Params from "./interfaces/Params"
    import LayoutItem from "./interfaces/LayoutItem"
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {ModelFactory} from "../../../utils/ModelFactory";
    import {LayoutManager} from "../../../utils/LayoutManager";

    export let params: Params;

    if (!params.dataAttributeList) {
        params.dataAttributeList = ['id', 'name', 'width', 'widthPx', 'link', 'notSortable', 'align', 'view', 'customLabel', 'editable'];
    }
    if (!params.dataAttributesDefs) {
        params.dataAttributesDefs = {
            link: {type: 'bool'},
            width: {type: 'float'},
            notSortable: {type: 'bool'},
            align: {
                type: 'enum',
                options: ["left", "right"]
            },
            view: {
                type: 'varchar',
                readOnly: true
            },
            customLabel: {
                type: 'varchar',
                readOnly: true
            },
            widthPx: {
                type: 'float'
            },
            name: {
                type: 'varchar',
                readOnly: true
            },
            editable: {
                type: 'bool'
            }
        };
    }

    let rowsLayout: RowsLayout;
    let enabledFields: Field[] = [];
    let disabledFields: Field[] = [];
    let rowLayout: LayoutItem[] = [];
    let editable: boolean = true;
    const ignoreList: string[] = [];
    const ignoreTypeList: string[] = [];

    function loadLayout(callback): void {
        ModelFactory.create(params.scope, (model) => {
            LayoutManager.get(params.scope, params.type, params.relatedScope, params.layoutProfileId, (layout) => {
                if (callback) {
                    readDataFromLayout(model, layout.layout);
                    callback(layout);
                }
            }, false);
        });
    }

    function readDataFromLayout(model: any, layout: LayoutItem[]): void {
        const allFields = Object.keys(model.defs.fields).filter(field =>
            checkFieldType(model.getFieldParam(field, 'type')) && isFieldEnabled(model, field)
        ).sort((v1, v2) =>
            Language.translate(v1, 'fields', params.scope).localeCompare(Language.translate(v2, 'fields', params.scope))
        );

        const enabledFieldsList: string[] = [];
        const labelList: string[] = [];
        const duplicateLabelList: string[] = [];

        enabledFields = layout.map(item => {
            const label = Language.translate(item.name, 'fields', params.scope);
            if (labelList.includes(label)) {
                duplicateLabelList.push(label);
            }
            labelList.push(label);
            enabledFieldsList.push(item.name);
            return {
                ...item,
                label: item.label || label
            };
        });

        disabledFields = allFields.filter(field => !enabledFieldsList.includes(field)).map(field => {
            const label = Language.translate(field, 'fields', params.scope);
            if (labelList.includes(label)) {
                duplicateLabelList.push(label);
            }
            labelList.push(label);
            const o: Field = {name: field, label};
            const fieldType = Metadata.get(['entityDefs', params.scope, 'fields', field, 'type']);
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

        const disabledParameters = ['disabled', `layout${Espo.utils.upperCaseFirst(params.type)}Disabled`];
        if (params.reelType) {
            disabledParameters.push(`layout${Espo.utils.upperCaseFirst(params.reelType)}Disabled`)
        }
        for (let param of disabledParameters) {
            if (model.getFieldParam(name, param)) {
                return false
            }
        }
        return true;
    }
</script>

<RowsLayout
        bind:this={rowsLayout}
        {params}
        {enabledFields}
        {disabledFields}
        {editable}
        {loadLayout}
/>