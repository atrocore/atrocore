<script lang="ts">

    import RowsLayout from './RowsLayout.svelte';
    import type {Field, LayoutItem} from './interfaces';
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {Notifier} from "../../../utils/Notifier";

    export let scope: string;
    export let type: string;
    export let layoutProfileId: string;
    export let viewType: string = 'detail';
    const dataAttributeList: string[] = ['id', 'name', 'style', 'sticked'];
    const dataAttributesDefs: any = {
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


    export let afterRender: any;

    let rowsLayout: RowsLayout;
    let enabledFields: Field[] = [];
    let disabledFields: Field[] = [];
    let rowLayout: LayoutItem[] = [];
    let editable: boolean = true;

    function loadLayout(): void {
        Notifier.notify('Loading...')
        LayoutManager.get(scope, type, layoutProfileId, (layout) => {
            readDataFromLayout(layout);
            Notifier.notify(false)
            if (afterRender) afterRender()
        }, false);
    }

    function readDataFromLayout(layout: Layout) {
        let panelListAll: string[] = [];
        let labels: Record<string, string> = {};
        let params: Record<string, any> = {};

        if (Metadata.get(['clientDefs', scope, 'defaultSidePanel', viewType]) !== false &&
            !Metadata.get(['clientDefs', scope, 'defaultSidePanelDisabled'])) {
            panelListAll.push('default');
            labels['default'] = 'Default';
        }

        (Metadata.get(['clientDefs', scope, 'sidePanels', viewType]) || []).forEach(item => {
            if (!item.name) return;
            panelListAll.push(item.name);
            if (item.label) {
                labels[item.name] = item.label;
            }
            params[item.name] = item;
        });

        disabledFields = [];

        layout = layout || {};

        rowLayout = [];

        panelListAll.forEach((item, index) => {
            let disabled = false;
            let itemData = layout[item] || {};
            if (itemData.disabled) {
                disabled = true;
            }
            let labelText;
            if (labels[item]) {
                labelText = Language.translate(labels[item], 'labels', scope);
            } else {
                labelText = Language.translate(item, 'panels', scope);
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
                if (o.name in params) {
                    dataAttributeList.forEach(attribute => {
                        if (attribute === 'name') return;
                        let itemParams = params[o.name] || {};
                        if (attribute in itemParams) {
                            o[attribute] = itemParams[attribute];
                        }
                    });
                }
                for (let i in itemData) {
                    o[i] = itemData[i];
                }
                o.index = ('index' in itemData) ? itemData.index : index;
                rowLayout.push(o);
            }
        });
        rowLayout.sort((v1, v2) => v1.index - v2.index);
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
        {dataAttributesDefs}
        {loadLayout}
/>