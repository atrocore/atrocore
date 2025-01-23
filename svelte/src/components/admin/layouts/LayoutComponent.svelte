<script lang="ts">
    import ListLayout from './ListLayout.svelte';
    import SideDetailLayout from "./SideDetailLayout.svelte";
    import RelationShipsLayout from "./RelationShipsLayout.svelte";
    import {Params} from "./Interfaces";
    import GridLayout from "./GridLayout.svelte";
    import {Metadata} from "../../../utils/Metadata";

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
                LayoutComponent = ListLayout;
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
            case 'detail':
                LayoutComponent = GridLayout;
                break;
            case 'sidePanelsDetail':
                viewType = "detail"
                LayoutComponent = SideDetailLayout
                break;
        }
    }
</script>
<LayoutComponent {params} {viewType}/>
