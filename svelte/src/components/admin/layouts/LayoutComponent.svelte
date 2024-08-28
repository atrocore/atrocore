<script lang="ts">
    import ListLayout from './ListLayout.svelte';
    import SideDetailLayout from "./SideDetailLayout.svelte";
    import RelationShipsLayout from "./RelationShipsLayout.svelte";

    export let type;
    export let scope;
    export let layoutProfileId;
    export let afterRender: Function;

    let LayoutComponent;
    let layoutDisabledParameter;
    let viewType

    $: {
        switch (type) {
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
                LayoutComponent = null;
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

<LayoutComponent {type} {scope} {layoutProfileId} {layoutDisabledParameter} {viewType} {afterRender}/>