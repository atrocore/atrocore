<script lang="ts">
    import ListLayout from './ListLayout.svelte';
    import RelationShipsLayout from "./RelationShipsLayout.svelte";
    import Params from "./interfaces/Params"
    import GridLayout from "./GridLayout.svelte";
    import {Metadata} from "../../../utils/Metadata";
    import InsightsLayout from "./InsightsLayout.svelte";

    export let params: Params;

    let LayoutComponent;
    let viewType

    const reelType = Metadata.get(['clientDefs', params.scope, 'additionalLayouts', params.type]) || params.type
    if (reelType) {
        params.reelType = reelType
    }


    $: {
        switch (reelType) {
            case 'list':
            case 'selection':
                LayoutComponent = ListLayout;
                break
            case 'insights':
                LayoutComponent = InsightsLayout;
                break
            case 'navigation':
                LayoutComponent = ListLayout;
                params.fieldTypes = ['link', 'linkMultiple']
                break
            case 'kanban':
                LayoutComponent = ListLayout;
                params.dataAttributeList = ['id', 'name', 'link', 'align', 'view', 'isLarge', 'cssStyle']
                params.dataAttributesDefs = {
                    link: {type: 'bool'},
                    isLarge: {type: 'bool'},
                    width: {type: 'float'},
                    cssStyle: {type: 'varchar'},
                    align: {
                        type: 'enum',
                        options: ["left", "right"]
                    },
                    view: {
                        type: 'varchar',
                        readOnly: true
                    },
                    name: {
                        type: 'varchar',
                        readOnly: true
                    }
                }
                break;
            case 'relationships':
                LayoutComponent = RelationShipsLayout;
                break;
            case 'summary':
            case 'detail':
                LayoutComponent = GridLayout;
                break;
        }
    }
</script>
<LayoutComponent {params} {viewType}/>
