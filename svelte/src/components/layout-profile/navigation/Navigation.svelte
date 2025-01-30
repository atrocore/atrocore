<script lang="ts">
    import RowsLayout from './RowsLayout.svelte';
    import type {Button, Params} from '../../admin/layouts/Interfaces';
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {Config} from "../../../utils/Config"
    import {ModelFactory} from "../../../utils/ModelFactory";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {Notifier} from "../../../utils/Notifier";

    export let params: Params;

    let rowsLayout: RowsLayout;
    let enabledFields: Field[] = [];
    let disabledFields: Field[] = [];
    let rowLayout: LayoutItem[] = [];
    let editable: boolean = true;

    function loadData(): void {
      prepareTabList()
    }

    function prepareTabList(): void {

        for (const item of Config.get('tabList')) {
            if(typeof  item === 'string') {
                if(Metadata.get(['scopes', item, 'tab'])) {
                    enabledFields.push({
                        name: item,
                        label: Language.translate(item, 'scopeNamesPlural')
                    });
                }
            }else if (typeof  item === 'object') {
                enabledFields.push({
                    isGroup: true,
                    label: item.name
                });
                for (const subItem of item.items) {
                    if(Metadata.get(['scopes', item, 'tab'])) {
                        enabledFields.push({
                            name: subItem,
                            label: Language.translate(subItem, 'scopeNamesPlural')
                        });
                    }
                }
            }

            Object.entries(Metadata.get(['scopes'])).forEach(([key, value]) => {
                if(value.disabled || value.emHidden || !value.tab) {
                    return;
                }
                disabledFields.push({
                    name: key,
                    label: Language.translate(key, 'scopeNamesPlural')
                });
            });
        }

    }

</script>

<RowsLayout
        bind:this={rowsLayout}
        {params}
        {enabledFields}
        {disabledFields}
        {editable}
        {loadData}
/>