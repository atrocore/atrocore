<script lang="ts">

    import RowsLayout from './RowsLayout.svelte';
    import type {Field, Params} from './Interfaces';
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {LayoutManager} from "../../../utils/LayoutManager";

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
    let enabledFields: Field[] = [];
    let disabledFields: Field[] = [];

    let fetch = () => {
        let res = {}
        enabledFields.forEach(f => {
            res[f.name] = f
        })
        disabledFields.forEach(f => {
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
        }, false);
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

        disabledFields = [];

        layout = layout || {};

        enabledFields = [];

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
                disabledFields.push({
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
                enabledFields.push(o);
            }
        });
        enabledFields.sort((v1, v2) => v1.index - v2.index);
    }

</script>

<RowsLayout
        bind:this={rowsLayout}
        {params}
        {enabledFields}
        {disabledFields}
        {loadLayout}
        {fetch}
/>