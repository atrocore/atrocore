<script lang="ts">

    import {onMount} from "svelte";
    import {Storage} from "../../../utils/Storage";
    import BaseSidebar from "../../record/BaseSidebar.svelte";

    export let scope: string;
    export let minWidth: number = 300;
    export let maxWidth: number = 600;
    export let currentWidth: number = minWidth;
    export let isCollapsed: boolean = false;

    let isPinned: boolean = true;

    function onSidebarPin(e: CustomEvent): void {
        Storage.set('catalog-tree-panel-pin', scope, isPinned ? 'pin' : 'not-pinned');
    }

    function onSidebarCollapse(e: CustomEvent): void {
        Storage.set('catalog-tree-panel', scope, isCollapsed ? 'collapsed' : '');
    }

    function onSidebarResize(e: CustomEvent): void {
        Storage.set('panelWidth', scope, currentWidth.toString());
    }

    onMount(() => {
        const savedWidth = Storage.get('panelWidth', scope);
        if (savedWidth) {
            currentWidth = parseInt(savedWidth) || minWidth;
        }
        isPinned = Storage.get('catalog-tree-panel-pin', scope) !== 'not-pinned';
    })
</script>

<BaseSidebar className="catalog-tree-panel" position="left" bind:width={currentWidth} bind:isCollapsed={isCollapsed}
             bind:isPinned={isPinned} {minWidth} {maxWidth} on:sidebar-resize={onSidebarResize}
             on:sidebar-collapse={onSidebarCollapse} on:sidebar-pin={onSidebarPin}>
    <div class="test">

    </div>

</BaseSidebar>