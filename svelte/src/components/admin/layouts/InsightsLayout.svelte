<script lang="ts">
    import RowsLayout from './RowsLayout.svelte';
    import Field from "./interfaces/Field"
    import Params from "./interfaces/Params"
    import LayoutItem from "./interfaces/LayoutItem"
    import { Language } from "$lib/core/language"
    import { Metadata } from '$lib/core/metadata';
    import {LayoutManager} from "../../../utils/LayoutManager";
    import Group from "./interfaces/Group";
    import {UserData} from "../../../utils/UserData";

    export let params: Params;

    if (!params.dataAttributeList) {
        params.dataAttributeList = ['id', 'name'];
    }
    if (!params.dataAttributesDefs) {
        params.dataAttributesDefs = {
            name: {
                type: 'varchar',
                readOnly: true
            }
        };
    }

    let rowsLayout: RowsLayout;
    let selectedFields: Field[] = [];
    let availableGroups: Group[] = []
    let editable: boolean = true;

    function loadLayout(callback): void {
        LayoutManager.get(params.scope, params.type, params.relatedScope, params.layoutProfileId, (layout) => {
            if (callback) {
                readDataFromLayout(layout.layout);
                callback(layout);
            }
        }, false, true);
    }

    function getTranslation(item: string) {
        return Language.translate(item, 'insightsPanels', params.scope)
    }

    function readDataFromLayout(layout: LayoutItem[]): void {
        let availablePanels = ['summary', 'accessManagement'];

        (Metadata.get(['clientDefs', params.scope, 'rightSidePanels']) || []).forEach(item => {
            availablePanels.push(item.name)
        })

        const groups = [{
            name: params.scope,
            scope: params.scope,
            fields: availablePanels.map(item => ({name: item, label: getTranslation(item)}))
        }]

        selectedFields = layout
        selectedFields.forEach(item => {
            item.label = getTranslation(item.name)
        })

        for (const group of groups) {
            group.fields = group.fields.filter(item => !selectedFields.find(sf => sf.name === item.name))
        }

        availableGroups = groups.reverse()
    }
</script>

<RowsLayout
        bind:this={rowsLayout}
        {params}
        {selectedFields}
        {availableGroups}
        {editable}
        {loadLayout}
/>