<script lang="ts">
    import {Storage} from "../../utils/Storage";
    import {onMount} from "svelte";
    import {active} from "sortablejs";

    export let scope: string;
    export let minWidth: number = 300;
    export let maxWidth: number = 600;
    export let currentWidth: number = minWidth;
    export let loadSummary: Function;

    export let isCollapsed: boolean = false;


    let isDragging: boolean = false;
    let startX: number;
    let startWidth: number;
    let mouseLeaveTimer: number|null;
    let mouseEnterTimer: number|null;
    let isMouseOver = false;

    let items = [
        {
            "name": "summary",
            "label": "Summary"
        },
        // {
        //     "name":"activities",
        //     "label": "Activities"
        // }
    ];
    let activeItem = items[0];


    $: sideViewWidth = isCollapsed ? 'auto' : `${currentWidth}px`;

    function handleResize(e: MouseEvent) {
        if (!isDragging) return;
        e.preventDefault();

        const width = startWidth + (startX - e.pageX);
        if (width >= minWidth && width <= maxWidth) {
            currentWidth = width;
        }
    }

    function startResize(e: MouseEvent) {
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
        if (!isDragging) return;

        isDragging = false;
        Storage.set('rightSideView', scope, currentWidth.toString());

        // Remove event listeners
        document.removeEventListener('mousemove', handleResize);

        // Restore text selection
        document.body.style.userSelect = '';
    }


    function setActiveItem(item: any) {
        if (activeItem && activeItem.name === item.name) {
            return
        }

        activeItem = item;
    }

    function handleCollapsePanel(e: Event) {
        console.log(e.target)
        isCollapsed = !isCollapsed;
        Storage.set('right-side-view-collapse', scope, isCollapsed ? 'collapsed' : '');
    }

    function handleMouseLeave() {
        isMouseOver = false;

        // Clear any pending expand timer
        if(mouseEnterTimer !== null) {
            clearTimeout(mouseEnterTimer);
        }

        mouseEnterTimer = null;

        // Start timer to collapse
        mouseLeaveTimer = setTimeout(() => {
            if (!isMouseOver) {
                isCollapsed = true;
            }
        }, 500);
    }

    function handleMouseEnter() {
        isMouseOver = true;

        // Clear any pending collapse timer
        if(mouseLeaveTimer !== null) {
            clearTimeout(mouseLeaveTimer);
        }

        mouseLeaveTimer = null;

        // Start timer to expand if currently collapsed
        if (isCollapsed) {
            mouseEnterTimer = setTimeout(() => {
                if (isMouseOver) {
                    isCollapsed = false;
                }
            }, 500);
        }
    }

    onMount(() => {
        const savedWidth = Storage.get('rightSideView', scope);
        if (savedWidth) {
            currentWidth = parseInt(savedWidth) || minWidth;
        }

        isCollapsed = Storage.get('right-side-view-collapse', scope) === 'collapsed';
        loadSummary();

        return () => {
            if(mouseLeaveTimer !== null) {
                clearTimeout(mouseLeaveTimer);
            }
            if(mouseEnterTimer !== null) {
                clearTimeout(mouseEnterTimer);
            }
        };
    })

</script>

<aside class="right-side-view" style="width: {sideViewWidth}"
       on:click|self="{handleCollapsePanel}"
       class:collapsed={isCollapsed}
       class:expanded={!isCollapsed}
       on:mouseenter={handleMouseEnter}
       on:mouseleave={handleMouseLeave}
>
    <div class="content">
        <button type="button"
                class="btn btn-link collapse-panel"
                on:click={handleCollapsePanel}>
            <span class="toggle-icon-left fas fa-angle-left" class:hidden={!isCollapsed}></span>
            <span class="toggle-icon-right fas fa-angle-right" class:hidden={isCollapsed}></span>
        </button>
        <div class="btn-group">
            {#each items as item}
                {#if item.name !== activeItem.name}
                    <a href="javascript:" on:click={()=>setActiveItem(item)}
                       class="btn btn-link item">
                        {item.label}
                    </a>
                {/if}
            {/each}
        </div>
        <div style="display: flex; align-items: center">
            <h5 style="font-weight: bold; margin-right: 10px; font-size: 16px;">{activeItem.label}</h5>
            <div class="layout-editor-container" class:hidden={activeItem.name !== 'summary'}></div>
        </div>


        <div class="summary" class:hidden={activeItem.name !== 'summary'}>

        </div>

        <div class="activities" class:hidden={activeItem.name !== 'activities'}>

        </div>

        {#if !isCollapsed}
            <div class="side-panel-resizer" on:mousedown={startResize}></div>
        {/if}
    </div>
</aside>

<style>
    .right-side-view {
        position: sticky;
        height: calc(100vh - 46px);
        top: 0;
        z-index: 1300;
        background: #fff;
        padding: 10px 20px;
        border-top: 1px solid var(--primary-border-color);
        border-left: 1px solid var(--primary-border-color);
        overflow-y: auto;
    }

    .right-side-view > .content {
        opacity: 1;
        transition: opacity 0.3s ease;
    }

    .collapsed.right-side-view {
        padding: 10px 10px;
        cursor: pointer;
    }

    .collapsed > .content {
        display: none !important;
        opacity: 0;
    }

    .side-panel-resizer {
        width: 2px;
        height: 100%;
        position: absolute;
        top: 0;
        left: 0;
        cursor: col-resize;
    }

    .item {
        padding: 6px 20px 6px 0;
        color: #333;
        text-decoration: underline;
    }

    .item.active {
        color: #2895ea;
    }

    .item:hover:not(.active) {
        color: #2895ea85;
    }

    button.collapse-panel {
        position: fixed;
        bottom: 0;
        margin-left: -20px;
    }

    .collapsed button.collapse-panel {
        margin-left: -15px;
    }

    :global(.right-side-view .dropdown-menu.pull-right) {
        right: auto;
    }

    :global(.right-side-view .panel-default > .panel-heading) {
        background-color: transparent;
        border-bottom: transparent;
        display: flex;
        flex-direction: row-reverse;
        padding-right: 0;
    }

    :global(.right-side-view .panel-title) {
        text-transform: none;
        font-size: 12px;
        color: #999;
    }
</style>