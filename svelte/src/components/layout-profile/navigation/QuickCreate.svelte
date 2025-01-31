<script lang="ts">

    import RowsLayout from './RowsLayout.svelte';
    import type {Params, Item} from './Interfaces';
    import {Language} from "../../../utils/Language";
    import {Metadata} from "../../../utils/Metadata";


    export let params: Params;

    let rowsLayout: RowsLayout;
    let disabledItems: Item[] = [];
    let key: number = 0;

    let enabledItems: Item[] = params.list.map((item: String, key) => {
        return {
            name: item,
            label: Language.translate(item, 'scopeNamesPlural'),
            sortOrder: key
        }
    });

    Object.entries(Metadata.get(['scopes'])).forEach(([key, value]) => {
        if (value.disabled || value.emHidden || !value.tab) {
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

</script>

<div>
    <RowsLayout
            bind:this={rowsLayout}
            {params}
            {enabledItems}
            {disabledItems}
    />
</div>
