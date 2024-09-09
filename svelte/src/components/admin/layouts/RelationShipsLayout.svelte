<script lang="ts">
    import RowsLayout from './RowsLayout.svelte';
    import type {Field, LayoutItem, Params} from './Interfaces';
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {Notifier} from "../../../utils/Notifier";
    import {ModelFactory} from "../../../utils/ModelFactory";

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


    let enabledFields: Field[] = [];
    let disabledFields: Field[] = [];
    let editable: boolean = true;

    function loadLayout(callback): void {
        ModelFactory.create(params.scope, function (model) {
            LayoutManager.get(params.scope, params.type, params.layoutProfileId, (layout) => {
                readDataFromLayout(model, layout);
                if (callback) callback()
            }, false);
        })

    }

    function isLinkEnabled(model, name) {
        return !model.getLinkParam(name, 'disabled') && !model.getLinkParam(name, 'layoutRelationshipsDisabled');
    }

    function readDataFromLayout(model, layout: Layout) {
        let allFields: string[] = [];

        for (let field in model.defs.links) {
            if (['hasMany', 'hasChildren'].includes(model.defs.links[field].type)) {
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
        enabledFields = [];
        disabledFields = [];

        for (let i in layout) {
            let item = layout[i];
            let o: any;

            let options = bottomPanels.find(panel => panel.name === item.name);
            if (typeof item === 'string' || item instanceof String) {
                o = {
                    name: item,
                    label: options ? Language.translate(options.label, 'labels', params.scope) : Language.translate(item, 'links', params.scope)
                };
            } else {
                o = item;
                o.label = options ? Language.translate(options.label, 'labels', params.scope) : Language.translate(o.name, 'links', params.scope);

            }

            params.dataAttributeList.forEach(attribute => {
                if (attribute === 'name') return;
                if (attribute in o) return;

                let value = Metadata.get(['clientDefs', params.scope, 'relationshipPanels', o.name, attribute]);
                if (value === null) return;
                o[attribute] = value;
            });

            enabledFields.push(o);
            enabledFieldsList.push(o.name);
        }

        for (let field of allFields) {
            if (!enabledFieldsList.includes(field)) {
                let options = bottomPanels.find(panel => panel.name === field);
                disabledFields.push({
                    name: field,
                    label: options ? Language.translate(options.label, 'labels', params.scope) : Language.translate(field, 'links', params.scope)
                });
            }
        }
    }

</script>

<RowsLayout
        {params}
        {enabledFields}
        {disabledFields}
        {loadLayout}
/>