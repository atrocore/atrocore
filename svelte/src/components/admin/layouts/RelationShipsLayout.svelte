<script lang="ts">
    import RowsLayout from './RowsLayout.svelte';
    import Field from "./interfaces/Field"
    import Params from "./interfaces/Params"
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {ModelFactory} from "../../../utils/ModelFactory";
    import Group from "./interfaces/Group";

    export let params: Params;

    if (!params.dataAttributeList) {
        params.dataAttributeList = ['id', 'name', 'style', 'hiddenPerDefault'];
    }
    if (!params.dataAttributesDefs) {
        params.dataAttributesDefs = {
            style: {
                type: 'enum',
                options: ['default', 'success', 'danger', 'primary', 'info', 'warning'],
                translation: 'LayoutManager.options.style'
            },
            hiddenPerDefault: {
                type: 'bool',
            },
            name: {
                readOnly: true
            }
        };
    }


    let selectedFields: Field[] = [];
    let availableGroups: Group[] = [];
    let editable: boolean = true;

    function loadLayout(callback): void {
        ModelFactory.create(params.scope, function (model) {
            LayoutManager.get(params.scope, params.type, null, params.layoutProfileId, (layoutData) => {
                if (callback) {
                    readDataFromLayout(model, layoutData.layout);
                    callback(layoutData);
                }
            }, false, true);
        })

    }

    function isLinkEnabled(model, name) {
        return !model.getLinkParam(name, 'disabled') && !model.getLinkParam(name, 'layoutRelationshipsDisabled');
    }

    function readDataFromLayout(model, layout: Layout) {
        let allFields: string[] = [];

        for (let field in model.defs.links) {
            if (['belongsTo', 'hasMany', 'hasChildren'].includes(model.defs.links[field].type)) {
                if (isLinkEnabled(model, field)) {
                    allFields.push(field);
                }
            }
        }
        const bottomPanels = Metadata.get(['clientDefs', params.scope, 'bottomPanels', 'detail']) || [];
        for (let panel of bottomPanels) {
            if (!panel.layoutRelationshipsDisabled) {
                allFields.push(panel.name);
            }
        }

        allFields.sort(function (v1, v2) {
            let v1Name, v2Name;
            let v1Options = bottomPanels.find(panel => panel.name === v1);
            let v2Options = bottomPanels.find(panel => panel.name === v2);

            if (v1 in model.defs.links) {
                v1Name = Language.translate(v1, 'links', params.scope);
            } else if (v1Options) {
                v1Name = Language.translate(v1Options.label, 'labels', params.scope);
            }

            if (v2 in model.defs.links) {
                v2Name = Language.translate(v2, 'links', params.scope);
            } else if (v2Options) {
                v2Name = Language.translate(v2Options.label, 'labels', params.scope);
            }
            return v1Name.localeCompare(v2Name);
        });

        let enabledFieldsList = [];
        selectedFields = [];
        const group = {
            name: params.scope,
            fields: []
        }

        for (let i in layout) {
            let item = layout[i];
            let o: any;

            let options = bottomPanels.find(panel => panel.name === item.name);
            if (typeof item === 'string' || item instanceof String) {
                o = {
                    name: item,
                    label: options ? Language.translate(options.label, 'labels', params.scope) : Language.translate(item, 'fields', params.scope)
                };
            } else {
                o = item;
                o.label = options ? Language.translate(options.label, 'labels', params.scope) : Language.translate(o.name, 'fields', params.scope);

            }

            params.dataAttributeList.forEach(attribute => {
                if (attribute === 'name') return;
                if (attribute in o) return;

                let value = Metadata.get(['clientDefs', params.scope, 'relationshipPanels', o.name, attribute]);
                if (value === null) return;
                o[attribute] = value;
            });

            selectedFields.push(o);
            enabledFieldsList.push(o.name);
        }

        for (let field of allFields) {
            if (!enabledFieldsList.includes(field)) {
                let options = bottomPanels.find(panel => panel.name === field);
                group.fields.push({
                    name: field,
                    label: options ? Language.translate(options.label, 'labels', params.scope) : Language.translate(field, 'fields', params.scope)
                });
            }
        }

        availableGroups = [group]
    }

</script>

<RowsLayout
        {params}
        {selectedFields}
        {availableGroups}
        {loadLayout}
/>