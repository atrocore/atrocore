<script lang="ts">
    import ListLayout from './ListLayout.svelte';
    import SideDetailLayout from "./SideDetailLayout.svelte";
    import RelationShipsLayout from "./RelationShipsLayout.svelte";
    import {Params} from "./Interfaces";
    import GridLayout from "./GridLayout.svelte";

    export let params: Params;

    let LayoutComponent;
    let layoutDisabledParameter;
    let viewType

    $: {
        switch (params.type) {
            case 'list':
                layoutDisabledParameter = "layoutListDisabled"
                LayoutComponent = ListLayout;
                break
            case 'listSmall':
                layoutDisabledParameter = "layoutListSmallDisabled"
                LayoutComponent = ListLayout;
                break
            case 'kanban':
                LayoutComponent = ListLayout;
                break;
            case 'relationships':
                LayoutComponent = RelationShipsLayout;
                break;
            case 'detail':
            case 'detailSmall':
                LayoutComponent = GridLayout;
                break;
            case 'sidePanelsDetail':
                viewType = "detail"
                LayoutComponent = SideDetailLayout
                break;
            case 'sidePanelsDetailSmall':
                viewType = "detailSmall"
                LayoutComponent = SideDetailLayout
                break;
            case 'sidePanelsEdit':
                viewType = "edit";
                LayoutComponent = SideDetailLayout
                break;
            case 'sidePanelsEditSmall':
                viewType = "editSmall"
                LayoutComponent = SideDetailLayout
                break;
        }
    }
</script>

<LayoutComponent {params} {layoutDisabledParameter} {viewType}/>