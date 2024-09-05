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
        allFields.sort((v1, v2) =>
            Language.translate(v1, 'links', 'scope').localeCompare(Language.translate(v2, 'links', 'scope'))
        );

        let enabledFieldsList = [];
        enabledFields = [];
        disabledFields = [];

        for (let i in layout) {
            let item = layout[i];
            let o: any;

            if (typeof item === 'string') {
                o = {
                    name: item,
                    label: Language.translate(item, 'links', 'scope')
                };
            } else {
                o = item;
                o.label = Language.translate(o.name, 'links', 'scope');
            }

            params.dataAttributeList.forEach(attribute => {
                if (attribute === 'name') return;
                if (attribute in o) return;

                let value = Metadata.get(['clientDefs', 'scope', 'relationshipPanels', o.name, attribute]);
                if (value === null) return;
                o[attribute] = value;
            });

            enabledFields.push(o);
            enabledFieldsList.push(o.name);
        }

        for (let field of allFields) {
            if (!enabledFieldsList.includes(field)) {
                disabledFields.push({
                    name: field,
                    label: Language.translate(field, 'links', 'scope')
                });
            }
        }

        for (let item of enabledFields) {
            item.label = Language.translate(item.name, 'links', 'scope');
        }
    }

</script>

<RowsLayout
        {params}
        {enabledFields}
        {disabledFields}
        {loadLayout}
/>