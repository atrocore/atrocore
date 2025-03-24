<script lang="ts">
    import RowsLayout from './RowsLayout.svelte';
    import Field from "./interfaces/Field"
    import Params from "./interfaces/Params"
    import LayoutItem from "./interfaces/LayoutItem"
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import Group from "./interfaces/Group";
    import {Utils} from "../../../utils/Utils.js";

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
    let selectedFields: Field[] = [];
    let availableGroups: Group[] = []
    let editable: boolean = true;
    const ignoreList: string[] = [];
    const ignoreTypeList: string[] = [];

    function loadLayout(callback): void {
        LayoutManager.get(params.scope, params.type, params.relatedScope, params.layoutProfileId, (layout) => {
            if (callback) {
                readDataFromLayout(layout.layout);
                callback(layout);
            }
        }, false);
    }

    function getTranslation(scope: string, field: string) {
        if (field === '_self') {
            return Language.translate(scope, 'scopeNamesPlural', 'Global')
        }
        if (field === '_bookmark') {
            return Language.translate('Bookmark', 'scopeNamesPlural', 'Global')
        }
        return Language.translate(field, 'fields', scope)
    }

    function getAdditionalFields() {
        const fields = []
        if (params.type === 'leftSidebar') {
            if (!Metadata.get(['scopes', params.scope, 'bookmarkDisabled'])) {
                fields.push('_bookmark')
            }
            if (Metadata.get(['scopes', params.scope, 'type']) === 'Hierarchy' && !Metadata.get(['scopes', params.scope, 'disableHierarchy'])) {
                fields.push('_self')
            }
        }

        return fields;
    }

    function getFieldType(scope: string, field: string) {
        return Metadata.get(['entityDefs', scope, 'fields', field, 'type']) ?? ''
    }

    function getRelationScope(leftScope: string, rightScope: string) {
        const parts = rightScope.split('.')
        const link = Metadata.get(['entityDefs', parts[0], 'links', parts[1]]) ?? {}
        if (link.entity === leftScope && !!link.relationName) {
            return Espo.utils.upperCaseFirst(link.relationName)
        }
        return ''
    }

    function readDataFromLayout(layout: LayoutItem[]): void {
        const groups = []
        let relationScope = ''

        if (params.relatedScope && params.type === 'list') {
            relationScope = getRelationScope(params.scope, params.relatedScope)
            // load related scope field
            if (relationScope) {
                const group = {
                    name: relationScope,
                    scope: relationScope,
                    prefix: relationScope + '__'
                }
                let allFields = Object.keys(Metadata.get(['entityDefs', relationScope, 'fields']) || {}).filter(field =>
                    checkFieldType(getFieldType(relationScope, field)) && isFieldEnabled(relationScope, field)
                );

                // remove links
                allFields = allFields.filter(field => !(Metadata.get(['entityDefs', relationScope, 'fields', field, 'relationField']) ?? false))

                allFields = allFields.sort((v1, v2) =>
                    getTranslation(relationScope, v1).localeCompare(getTranslation(relationScope, v2))
                ).map(f => group.prefix + f)

                group.fields = allFields
                groups.push(group)
            }
        }

        let allFields = Object.keys(Metadata.get(['entityDefs', params.scope, 'fields']) || {}).filter(field =>
            checkFieldType(getFieldType(params.scope, field)) && isFieldEnabled(params.scope, field)
        );
        allFields.push(...getAdditionalFields())
        allFields = allFields.sort((v1, v2) =>
            getTranslation(params.scope, v1).localeCompare(getTranslation(params.scope, v2))
        )
        groups.unshift({
            name: params.scope,
            scope: params.scope,
            fields: allFields
        })

        const labelList: string[] = [];
        const duplicateLabelList: string[] = [];

        for (const group of groups) {
            group.fields = group.fields
                .map(field => {
                    const label = getTranslation(group.scope, group.prefix ? field.replace(group.prefix, '') : field);
                    if (!group.prefix) {
                        if (labelList.includes(label)) {
                            duplicateLabelList.push(label);
                        }
                        labelList.push(label);
                    }

                    const o: Field = {name: field, label};
                    const fieldType = Metadata.get(['entityDefs', group.scope, 'fields', field, 'type']);
                    if (fieldType && Metadata.get(['fields', fieldType, 'notSortable'])) {
                        o.notSortable = true;
                    }
                    return o;
                });
        }

        selectedFields = layout

        groups[0].fields.forEach(item => {
            if (duplicateLabelList.includes(item.label)) {
                item.label += ` (${item.name})`;
            }
            const selectedItem = selectedFields.find(i => !i.label && i.name === item.name)
            if (selectedItem) {
                selectedItem.label = item.label
            }
        });

        if (groups[1]) {
            groups[1].fields.forEach(item => {
                if (labelList.includes(item.label)) {
                    item.label += ` (Relation)`
                }
                const selectedItem = selectedFields.find(i => !i.label && i.name === item.name)
                if (selectedItem) {
                    selectedItem.label = item.label
                }
            })
        }


        for (const group of groups) {
            group.fields = group.fields.filter(item => !selectedFields.find(sf => sf.name === item.name))
        }

        availableGroups = groups.reverse()
    }

    function checkFieldType(type: string): boolean {
        if (params.fieldTypes) {
            return params.fieldTypes.includes(type)
        }
        return true;
    }

    function isFieldEnabled(scope: string, name: string): boolean {
        if (ignoreList.includes(name)) {
            return false;
        }
        if (ignoreTypeList.includes(getFieldType(scope, name))) {
            return false;
        }

        const disabledParameters = ['disabled', `layout${Utils.upperCaseFirst(params.type)}Disabled`];
        if (params.reelType) {
            disabledParameters.push(`layout${Utils.upperCaseFirst(params.reelType)}Disabled`)
        }
        for (let param of disabledParameters) {
            if (Metadata.get(['entityDefs', scope, 'fields', name, param])) {
                return false
            }
        }
        return true;
    }
</script>

<RowsLayout
        bind:this={rowsLayout}
        {params}
        {selectedFields}
        {availableGroups}
        {editable}
        {loadLayout}
/>