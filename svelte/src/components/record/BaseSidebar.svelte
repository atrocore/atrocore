<script lang="ts">
    import {fade} from 'svelte/transition';
    import {createEventDispatcher, onDestroy, onMount} from "svelte";
    import {Language} from "../../utils/Language";

    export let className: string = '';
    export let isCollapsed: boolean = false;
    export let isPinned: boolean = true;
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

        const diffX = position == 'left' ? (e.pageX - startX) : (startX - e.pageX)
        const panelWidth = startWidth + diffX;
        if (panelWidth >= minWidth && panelWidth <= maxWidth) {
            requestAnimationFrame(() => {
                width = panelWidth;
                dispatch('sidebar-resize', { width });
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
        {#if !isMobile}
            <button class="pin-button" type="button" on:click={togglePin} title={Language.translate(isPinned ? 'sidebarUnpin' : 'sidebarPin')}>
                {#if isPinned}
                    <i class="ph-fill ph-cards"></i>
                {:else}
                    <i class="ph ph-cards"></i>
                {/if}
            </button>
        {/if}
        {#if isPinned && !isMobile}
            <span class="collapse-text">&#10229; {Language.translate(isCollapsed ? 'sidebarExpand' : 'sidebarCollapse')} &#10230;</span>
        {/if}
        <button class="collapse-button" type="button" on:click={toggleCollapse}>
            {#if position === 'left'}
                <i class="ph" class:ph-caret-left={!isCollapsed} class:ph-caret-right={isCollapsed}></i>
            {:else}
                <i class="ph" class:ph-caret-left={isCollapsed} class:ph-caret-right={!isCollapsed}></i>
            {/if}
        </button>
    </div>
    {#if !isCollapsed}
        <div class="sidebar-resizer" on:mousedown={startResize}></div>
    {/if}
</aside>

<style>
    .sidebar {
        --sidebar-color: #fafafa;
        --field-hover-color: #f4f4f4;
        --field-inline-edit-color: #f0f0f0;

        height: 100%;
        background-color: var(--sidebar-color);
        display: flex;
        align-items: stretch;
        overflow-y: auto;
        overflow-x: clip;
        position: relative;
    }

    .sidebar.sidebar-left {
        flex-direction: row;
        border-right: 1px solid #e8eced;
    }

    .sidebar.sidebar-left:not(.pinned):not(.collapsed) {
        left: 0;
    }

    .sidebar.sidebar-right {
        flex-direction: row-reverse;
        border-left: 1px solid #e8eced;
    }

    .sidebar.sidebar-right:not(.pinned):not(.collapsed) {
        right: 0;
    }

    .sidebar > * {
        flex-shrink: 0;
        height: 100%;
    }

    .sidebar > .collapse-strip {
        position: sticky;
        top: 0;
        width: 20px;
        cursor: pointer;
        transition: background-color 0.2s;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .sidebar > .collapse-strip > .collapse-text {
        color: #ccc;
        font-size: 12px;
        line-height: 1;
        pointer-events: none;
        display: none;
    }

    .sidebar > .collapse-strip:hover > .collapse-text {
        display: inline;
    }

    .sidebar.sidebar-right > .collapse-strip > .collapse-text {
        writing-mode: vertical-lr;
    }

    .sidebar.sidebar-left > .collapse-strip > .collapse-text {
        writing-mode: sideways-lr;
    }

    .sidebar.sidebar-right:not(.collapsed) > .collapse-strip > .collapse-text {
        transform: translateX(-3px);
    }

    .sidebar.sidebar-left:not(.collapsed) > .collapse-strip > .collapse-text {
        transform: translateX(3px);
    }

    .sidebar > .collapse-strip > button {
        background: transparent;
        padding: 0;
        outline: 0;
        border: 0;
        line-height: 1;
        color: var(--sidebar-icon-color, #999);
    }

    .sidebar > .collapse-strip > button i {
        font-size: 14px;
    }

    .sidebar > .collapse-strip > .pin-button {
        position: absolute;
        top: 5px;
        left: 50%;
        transform: translateX(-50%);
    }

    .sidebar > .collapse-strip > .pin-button:hover {
        color: #333;
    }

    .sidebar > .collapse-strip > .collapse-button {
        position: absolute;
        bottom: 5px;
        left: 50%;
        transform: translateX(-50%);
        pointer-events: none;
    }

    .sidebar > .sidebar-inner {
        background-color: var(--sidebar-color);
        flex: 1;
        padding-top: 10px;
        padding-left: 20px;
        padding-right: 20px;
        height: fit-content;
        min-height: 100%;
        max-width: calc(100% - 22px);
        box-sizing: border-box;
    }

    .sidebar.sidebar-left > .sidebar-inner {
        padding-right: 0;
    }

    .sidebar.sidebar-right > .sidebar-inner {
        padding-left: 3px;
    }

    .sidebar > .sidebar-resizer {
        position: sticky;
        top: 0;
        width: 3px;
        cursor: col-resize;
        transition: background-color 0.2s;
    }

    .sidebar.sidebar-left > .sidebar-resizer {
        right: 0;
    }

    .sidebar.sidebar-right > .sidebar-resizer {
        left: 0;
    }

    .sidebar > .sidebar-resizer:hover {
        background: rgba(0, 0, 0, 0.1);
    }

    .sidebar :global(.sidebar-header) {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: var(--sidebar-color);
        display: flex;
        align-items: center;
        margin: 10px 0;
        padding: 10px 0;
    }

    .sidebar :global(.sidebar-header h5) {
        margin: 0 10px 0 0;
        font-weight: 700;
        font-size: 18px;
    }

    .sidebar.collapsed > .collapse-strip {
        width: 25px;
    }

    .sidebar.collapsed > .sidebar-inner {
        display: none;
    }

    .sidebar.collapsed > .sidebar-resizer {
        display: none;
    }

    .sidebar.sidebar-left:not(.pinned):not(.collapsed) :global(~ main) {
        margin-left: 26px;
    }

    :global(#main main:has(~ .sidebar.sidebar-right:not(.pinned):not(.collapsed))) {
        margin-right: 26px;
    }

    @media screen and (max-width: 768px) {
        .sidebar > .collapse-strip > .collapse-text {
            display: none;
        }

        .sidebar:not(.collapsed) {
            position: absolute;
            top: 0;
            z-index: 501;
            bottom: 0;
            left: 0;
            width: 100% !important;
        }

        .sidebar.collapsed {
            flex-basis: 0;
            width: 0;
            height: 0;
        }

        .sidebar.collapsed .collapse-strip {
            top: auto;
            bottom: 20px;
            z-index: 500;
            position: fixed;
            height: auto;
            width: auto;
        }

        .sidebar.collapsed .collapse-button {
            position: static;
            transform: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #fafafabf;
            border: 1px solid #F0F0F0;
            padding: 0;
        }

        .sidebar.sidebar-left.collapsed .collapse-strip {
            left: 20px;
        }

        .sidebar.sidebar-right.collapsed .collapse-strip {
            right: 20px;
        }
    }

    @media screen and (min-width: 768px) {
        .sidebar:not(.pinned):not(.collapsed) {
            position: absolute;
            z-index: 500;
            top: 0;
            box-shadow: 0 3px 4px 0 rgba(0, 0, 0, .3);
        }

        .sidebar:not(.collapsed) {
            min-width: 300px;
        }
    }
</style>