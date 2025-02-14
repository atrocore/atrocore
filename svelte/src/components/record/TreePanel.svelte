<script lang="ts">
    import {createEventDispatcher, onMount} from 'svelte';
    import {fade} from 'svelte/transition';
    import {Storage} from "../../utils/Storage";

    export let scope: string;
    export let treeScope: string = scope;
    export let minWidth: number = 220;
    export let maxWidth: number = 600;
    export let currentWidth: number = minWidth;
    export let isCollapsed: boolean = false;
    export let categorySearch: any = null;
    export let scopesEnum: any = null;
    export let maxSize: number = 20;

    const dispatch = createEventDispatcher();

    let treeElement: HTMLElement;
    let panelElement: HTMLElement;
    let isDragging: boolean = false;
    let startX: number;
    let startWidth: number;

    $: if (currentWidth) {
        dispatch('tree-width-changed', currentWidth);
    }

    $: treePanelWidth = isCollapsed ? 'auto' : `${currentWidth}px`;

    function handleCollapsePanel() {
        isCollapsed = !isCollapsed;
        dispatch('collapse-panel', {isCollapsed});
        Storage.set('catalog-tree-panel', scope, isCollapsed ? 'collapsed' : '');

        if (!isCollapsed) {
            dispatch('rebuild-tree');
        }
    }

    function handleResetFilter(e: Event) {
        e.preventDefault();
        dispatch('tree-reset');
    }

    function handleResize(e: MouseEvent) {
        console.log('handle resize');
        if (!isDragging) return;
        e.preventDefault();

        const width = startWidth + (e.pageX - startX);
        if (width >= minWidth && width <= maxWidth) {
            currentWidth = width;
        }
    }

    function startResize(e: MouseEvent) {
        console.log('start resize');
        isDragging = true;
        startX = e.pageX;
        startWidth = currentWidth;

        // Add the event listeners to document instead of window
        document.addEventListener('mousemove', handleResize);
        document.addEventListener('mouseup', stopResize, {once: true});

        // Prevent text selection during drag
        document.body.style.userSelect = 'none';
    }

    function stopResize() {
        console.log('stop resize');
        if (!isDragging) return;

        isDragging = false;
        Storage.set('panelWidth', scope, currentWidth.toString());

        // Remove event listeners
        document.removeEventListener('mousemove', handleResize);

        // Restore text selection
        document.body.style.userSelect = '';
    }

    onMount(() => {
        const savedWidth = Storage.get('panelWidth', scope);
        if (savedWidth) {
            currentWidth = parseInt(savedWidth) || minWidth;
        }

        if (window.innerWidth <= 767 || Storage.get('catalog-tree-panel', scope)) {
            isCollapsed = true;
        }

        return () => {
            // Cleanup any remaining event listeners
            if (isDragging) {
                stopResize();
            }
        };
    });
</script>

<aside class="catalog-tree-panel" class:collapsed={isCollapsed} transition:fade
       style="width: {treePanelWidth}"
>
    <button
            type="button"
            class="btn btn-default collapse-panel"
            class:collapsed={isCollapsed}
            on:click={handleCollapsePanel}
    >
        <span class="toggle-icon-left fas fa-angle-left" class:hidden={isCollapsed}></span>
        <span class="toggle-icon-right fas fa-angle-right" class:hidden={!isCollapsed}></span>
    </button>

    <div class="category-panel" class:hidden={isCollapsed}>
        <div class="panel-group text-center">
            <div class="btn-group">
                <a
                        href="#{scope}"
                        class="btn btn-default active reset-tree-filter"
                        on:click={handleResetFilter}
                >
                    Unset Selection
                </a>
            </div>
        </div>

        <div class="panel-group category-search">
            <slot name="category-search">
                {#if categorySearch}
                    <svelte:component this={categorySearch}/>
                {/if}
            </slot>
        </div>

        {#if scopesEnum}
            <div class="panel-group scopes-enum">
                <slot name="scopes-enum">
                    <svelte:component this={scopesEnum}/>
                </slot>
            </div>
        {/if}

        <div class="panel-group category-tree" bind:this={treeElement}>
            <slot name="tree"></slot>
        </div>

        {#if !isCollapsed}
            <div
                    class="category-panel-resizer"
                    on:mousedown={startResize}
            ></div>
        {/if}
    </div>
</aside>

<style>
    .category-panel-resizer {
        position: absolute;
        right: 0;
        top: 0;
        width: 5px;
        height: 100%;
        cursor: ew-resize;
        background: transparent;
    }

    .category-panel-resizer:hover {
        background: rgba(0, 0, 0, 0.1);
    }
</style>