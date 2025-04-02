<script lang="ts">
    import ChevronLeftIcon from '$assets/icons/chevron_left.svg?raw';
    import ChevronRightIcon from '$assets/icons/chevron_right.svg?raw';
    import PinIcon from '$assets/icons/keep.svg?raw';
    import UnpinIcon from '$assets/icons/keep_off.svg?raw';

    import {fade} from 'svelte/transition';
    import {createEventDispatcher, onDestroy, onMount} from "svelte";

    export let className: string = '';
    export let isCollapsed: boolean = false;
    export let isPinned: boolean = false;
    export let minWidth: number = 250;
    export let maxWidth: number = 600;
    export let width: number = minWidth;
    export let position: 'left' | 'right';

    const dispatch = createEventDispatcher();

    let isDragging: boolean = false;
    let isMouseOver: boolean = false;
    let startX: number;
    let startWidth: number;
    let mouseLeaveTimer: number | null;
    let mouseEnterTimer: number | null;

    let computedClassName: string;
    $: {
        computedClassName = 'sidebar';

        if (position === 'left') {
            computedClassName += ' sidebar-left';
        } else {
            computedClassName += ' sidebar-right';
        }

        if (className) {
            computedClassName += ` ${className}`;
        }
    }

    let computedWidth: string;
    $: computedWidth = isCollapsed ? 'auto' : `${width}px`;

    let isMobile = false;
    const checkScreenSize = () => {
        isMobile = window.innerWidth <= 768;
    };

    function togglePin(e: Event) {
        isPinned = !isPinned;
        dispatch('sidebar-pin', { isPinned });
    }

    function setCollapsed(collapsed: boolean) {
        isCollapsed = collapsed;

        dispatch('sidebar-collapse', { isCollapsed });
    }

    function toggleCollapse() {
        setCollapsed(!isCollapsed);
    }

    function startResize(e: MouseEvent) {
        isDragging = true;
        startX = e.pageX;
        startWidth = width;

        document.addEventListener('mousemove', handleResize);
        document.addEventListener('mouseup', stopResize, {once: true});
        document.body.style.userSelect = 'none';
    }

    function stopResize() {
        if (!isDragging) return;
        isDragging = false;

        dispatch('sidebar-resize', { width });

        document.removeEventListener('mousemove', handleResize);
        document.body.style.userSelect = '';
    }

    function handleResize(e: MouseEvent) {
        if (!isDragging) return;
        e.preventDefault();

        const panelWidth = startWidth + (e.pageX - startX);
        if (panelWidth >= minWidth && panelWidth <= maxWidth) {
            requestAnimationFrame(() => {
                width = panelWidth;
            });
        }
    }

    function handleMouseLeave() {
        if (isPinned) {
            return;
        }
        isMouseOver = false;

        // Clear any pending expand timer
        if (mouseEnterTimer !== null) {
            clearTimeout(mouseEnterTimer);
        }

        mouseEnterTimer = null;

        // Start timer to collapse
        mouseLeaveTimer = setTimeout(() => {
            if (!isMouseOver) {
                setCollapsed(true)
            }
        }, 500);
    }

    function handleMouseEnter() {
        if (isPinned) {
            return;
        }
        isMouseOver = true;

        // Clear any pending collapse timer
        if (mouseLeaveTimer !== null) {
            clearTimeout(mouseLeaveTimer);
        }

        mouseLeaveTimer = null;

        // Start timer to expand if currently collapsed
        if (isCollapsed) {
            mouseEnterTimer = setTimeout(() => {
                if (isMouseOver) {
                    setCollapsed(false)
                }
            }, 500);
        }
    }

    onMount(() => {
        checkScreenSize();

        window.addEventListener("resize", checkScreenSize);
    })

    onDestroy(() => {
        if (isDragging) {
            stopResize();
        }

        if (mouseLeaveTimer !== null) {
            clearTimeout(mouseLeaveTimer);
        }
        if (mouseEnterTimer !== null) {
            clearTimeout(mouseEnterTimer);
        }

        window.removeEventListener("resize", checkScreenSize);
    })
</script>

<aside class={computedClassName} class:collapsed={isCollapsed} class:pinned={isPinned && !isMobile} transition:fade
       style:width={computedWidth} on:mouseenter={handleMouseEnter} on:mouseleave={handleMouseLeave}>
    <div class="sidebar-inner">
        <slot/>
    </div>
    <div class="collapse-strip" on:click|self="{toggleCollapse}">
        <button class="pin-button" type="button" on:click={togglePin}>
            {@html isPinned ? UnpinIcon : PinIcon}
        </button>
        <button class="collapse-button" type="button" on:click={toggleCollapse}>
            {#if position === 'left'}
                {@html isCollapsed ? ChevronRightIcon : ChevronLeftIcon}
            {:else}
                {@html isCollapsed ? ChevronLeftIcon : ChevronRightIcon}
            {/if}
        </button>
    </div>
    {#if !isCollapsed}
        <div class="sidebar-resizer" on:mousedown={startResize}></div>
    {/if}
</aside>

<style>
    .sidebar {
        height: 100%;
        background-color: #fafafa;
        display: flex;
        align-items: stretch;
    }

    .sidebar.sidebar-left {
        flex-direction: row;
        border-right: 1px solid #e8eced;
    }

    .sidebar.sidebar-right {
        flex-direction: row-reverse;
        border-left: 1px solid #e8eced;
    }

    .sidebar > * {
        height: 100%;
    }

    .sidebar > .collapse-strip {
        position: sticky;
        top: 0;
        width: 20px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .sidebar > .collapse-strip:hover,
    .sidebar > .collapse-strip:hover ~ .sidebar-resizer {
        background-color: #f0f0f0;
    }

    .sidebar > .collapse-strip > button {
        background: transparent;
        padding: 0;
        outline: 0;
        border: 0;
        line-height: 0;
        width: 20px;
    }

    .sidebar > .collapse-strip > .pin-button {
        position: absolute;
        top: 5px;
        left: 50%;
        transform: translateX(-50%);
        width: 15px;
    }

    .sidebar > .collapse-strip > .collapse-button {
        position: absolute;
        bottom: 5px;
        left: 50%;
        transform: translateX(-50%);
        pointer-events: none;
    }

    .sidebar > .sidebar-inner {
        /*overflow: auto;*/
        background-color: inherit;
        flex: 1;
        padding-left: 20px;
        padding-right: 20px;
    }

    .sidebar > .sidebar-resizer {
        position: sticky;
        top: 0;
        width: 3px;
        cursor: col-resize;
        transition: background-color 0.2s;
    }

    .sidebar > .sidebar-resizer:hover {
        background: rgba(0, 0, 0, 0.1);
    }

    .sidebar.collapsed > .sidebar-inner {
        display: none;
    }

    .sidebar.collapsed > .sidebar-resizer {
        display: none;
    }

    .sidebar.collapsed > .collapse-strip {
        width: 25px;
    }
</style>