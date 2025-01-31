<script lang="ts">

    import RowsLayout from './RowsLayout.svelte';
    import type {Button} from '../../admin/layouts/Interfaces';
    import type {Params, KeyValue, Item} from './Interfaces';
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";


    export let params: Params;

    let defaultDelimiter = '_delimiter_';
    let rowsLayout: RowsLayout;
    let enabledItems: Item[] = [];
    let disabledItems: Item[] = [];
    let key: number = 0;

    let fieldsInGroup: KeyValue = {};

    let buttonList: Button[] = [
        {name: 'save', label: Language.translate('Save', 'labels'), style: 'primary'},
        {name: 'cancel', label: Language.translate('Cancel', 'labels')},
        {
            name: 'addGroup',
            label: Language.translate('addGroup', 'labels', 'LayoutProfile'),
            action: () => {
                enabledItems.push({
                    id: defaultDelimiter + getRandomHash(),
                    isGroup: true,
                    canEdit: true,
                    canRemove: true,
                    isGroup: true,
                    canDisabled: false,
                    name: '',
                    label: '',
                    sortOrder: enabledItems[enabledItems.length - 1].sortOrder + 10
                });
                key += 1;
            }
        },
    ];

    function refresh(): void {
        key++;
    }

    function editItem(item): void {
        params.onEditItem(item, (newItem) => {
            console.log('new Item', newItem);
            let index = enabledItems.findIndex(i => i.id === newItem.id);
            enabledItems[index] = newItem;
            refresh();
        })
    }


    function getRandomHash(): string {
        return Math.floor((1 + Math.random()) * 0x100000000)
            .toString(16)
            .substring(1);
    }

    loadData()

    function loadData(): void {
        let navigation = params.navigation ?? [];
        let sortOrder = 0;
        for (let i = 0; i < navigation.length; i++) {
            let item = navigation[i];
            if (typeof item === 'string') {
                if (Metadata.get(['scopes', item, 'tab'])) {
                    enabledItems.push({
                        name: item,
                        label: Language.translate(item, 'scopeNamesPlural'),
                        sortOrder
                    });
                }
                sortOrder++;
            } else if (typeof item === 'object') {
                enabledItems.push({
                    id: item.id,
                    canEdit: true,
                    canRemove: true,
                    isGroup: true,
                    canDisabled: false,
                    name: item.name,
                    label: item.name,
                    sortOrder
                });
                sortOrder++;
                for (const subItem of item.items) {
                    if (Metadata.get(['scopes', subItem, 'tab'])) {
                        enabledItems.push({
                            name: subItem,
                            label: Language.translate(subItem, 'scopeNamesPlural'),
                            sortOrder
                        });
                        sortOrder++;
                    }
                }
                if (item.name !=='' && (i === navigation.length - 1 || (typeof navigation[i + i] === 'string') || item.items.length === 0)) {
                    enabledItems.push({
                        id: defaultDelimiter + getRandomHash(),
                        canEdit: true,
                        canRemove: true,
                        canDisabled: false,
                        isGroup: true,
                        name: '',
                        label: '',
                        sortOrder
                    });
                    sortOrder++;
                }
            }
        }

        Object.entries(Metadata.get(['scopes'])).forEach(([key, value]) => {
            if (value.disabled || value.emHidden || !value.tab) {
                return;
            }

            if(enabledItems.find(v => v.name === key)) {
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
                {editItem}
        />
    {/key}
</div>
