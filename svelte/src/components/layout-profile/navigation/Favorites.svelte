<script lang="ts">

    import RowsLayout from './RowsLayout.svelte';
    import type Button from '../../admin/layouts/interfaces/Button';
    import type Params from "./interfaces/Params";
    import type KeyValue from "./interfaces/KeyValue";
    import type Item from "./interfaces/Item";
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";
    import {Acl} from "../../../utils/Acl";


    export let params: Params;

    let rowsLayout: RowsLayout;
    let enabledItems: Item[] = [];
    let disabledItems: Item[] = [];
    let key: number = 0;

    let fieldsInGroup: KeyValue = {};

    let buttonList: Button[] = [
        {name: 'save', label: Language.translate('Save', 'labels'), style: 'primary'},
        {name: 'cancel', label: Language.translate('Cancel', 'labels')}
    ];

    if (params.canReset) {
        buttonList.push({name: 'resetToDefault', label: Language.translate('Reset to Default', 'labels')});
    }

    function refresh(): void {
        key++;
    }

    function validate(itemsToSave: Array<any>): boolean {
        return true;
    }

    loadData();

    function loadData(): void {
        let navigation = params.list ?? [];
        let sortOrder = 0;
        for (let i = 0; i < navigation.length; i++) {
            let item = navigation[i];
            if (typeof item === 'string') {
                if (Metadata.get(['scopes', item, 'tab']) && Acl.check(item, 'read')) {
                    enabledItems.push({
                        name: item,
                        label: Language.translate(item, 'scopeNamesPlural'),
                        sortOrder
                    });
                }
                sortOrder++;
            }
        }

        Object.entries(Metadata.get(['scopes'])).forEach(([key, value]) => {
            if (value.disabled || value.emHidden || !value.tab || !Acl.check(key, 'read')) {
                return;
            }

            if (enabledItems.find(v => v.name === key)) {
                return;
            }

            disabledItems.push({
                name: key,
                label: Language.translate(key, 'scopeNamesPlural')
            });
        });

        disabledItems.sort((a, b) => a.label.localeCompare(b.label));
    }

</script>

<div>
    {#key key}
        <RowsLayout
                bind:this={rowsLayout}
                {params}
                {enabledItems}
                {disabledItems}
                {buttonList}
                {fieldsInGroup}
                {refresh}
                {validate}
        />
    {/key}
</div>
