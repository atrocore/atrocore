<script lang="ts">

    import RowsLayout from './RowsLayout.svelte';
    import Field from "./interfaces/Field"
    import Params from "./interfaces/Params"
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import Group from "./interfaces/Group";

    export let params: Params;
    export let viewType: string = 'detail';

    if (!params.dataAttributeList) {
        params.dataAttributeList = ['id', 'name', 'style', 'sticked'];
    }
    if (!params.dataAttributesDefs) {
        params.dataAttributesDefs = {
            style: {
                type: 'enum',
                options: ['default', 'success', 'danger', 'primary', 'info', 'warning'],
                translation: 'LayoutManager.options.style'
            },
            sticked: {
                type: 'bool'
            },
            name: {
                readOnly: true
            }
        };
    }

    let rowsLayout: RowsLayout;
    let selectedFields: Field[] = [];
    let availableGroups: Group[] = [];

    let fetch = () => {
        let res = {}
        selectedFields.forEach(f => {
            res[f.name] = f
        })
        availableGroups[0].fields.forEach(f => {
            res[f.name] = {...f, disabled: true}
        })
        return res
    }

    function loadLayout(callback): void {
        LayoutManager.get(params.scope, params.type, null, params.layoutProfileId, (layoutData) => {
            if (callback) {
                readDataFromLayout(layoutData.layout);
                callback(layoutData);
            }
        }, false, true);
    }

    function readDataFromLayout(layout: Layout) {
        let panelListAll: string[] = [];
        let labels: Record<string, string> = {};
        let panelsMap: Record<string, any> = {};

        if (Metadata.get(['clientDefs', params.scope, 'defaultSidePanel', viewType]) !== false &&
            !Metadata.get(['clientDefs', params.scope, 'defaultSidePanelDisabled'])) {
            panelListAll.push('default');
            labels['default'] = 'Default';
        }

        (Metadata.get(['clientDefs', params.scope, 'sidePanels', viewType]) || []).forEach(item => {
            if (!item.name) return;
            panelListAll.push(item.name);
            if (item.label) {
                labels[item.name] = item.label;
            }
            panelsMap[item.name] = item;
        });

        const group = {
            name: params.scope,
            fields: []
        };

        layout = layout || {};

        selectedFields = [];

        panelListAll.forEach((item, index) => {
            let disabled = false;
            let itemData = layout[item] || {};
            if (itemData.disabled) {
                disabled = true;
            }
            let labelText;
            if (labels[item]) {
                labelText = Language.translate(labels[item], 'labels', params.scope);
            } else {
                labelText = Language.translate(item, 'panels', params.scope);
            }

            if (disabled) {
                group.fields.push({
                    name: item,
                    label: labelText
                });
            } else {
                let o: any = {
                    name: item,
                    label: labelText
                };
                if (o.name in panelsMap) {
                    params.dataAttributeList.forEach(attribute => {
                        if (attribute === 'name') return;
                        let itemParams = panelsMap[o.name] || {};
                        if (attribute in itemParams) {
                            o[attribute] = itemParams[attribute];
                        }
                    });
                }
                for (let i in itemData) {
                    o[i] = itemData[i];
                }
                o.index = ('index' in itemData) ? itemData.index : index;
                selectedFields.push(o);
            }
        });

        selectedFields.sort((v1, v2) => v1.index - v2.index);
        availableGroups = [group]
    }

</script>

<RowsLayout
        bind:this={rowsLayout}
        {params}
        {selectedFields}
        {availableGroups}
        {loadLayout}
        {fetch}
/>