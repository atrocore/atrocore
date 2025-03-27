<script lang="ts">
    import {Storage} from "../../utils/Storage";
    import {onMount} from "svelte";
    import GfiImage from "../../assets/image_gfi.svg"
    import GfiHideImage from "../../assets/hide_image_gfi.svg"
    import {Language} from "../../utils/Language";
    import {Metadata} from "../../utils/Metadata";

    export let scope: string;
    export let minWidth: number = 300;
    export let maxWidth: number = 600;
    export let currentWidth: number = minWidth;
    export let loadSummary: Function;

    export let loadActivities: Function;

    export let isCollapsed: boolean = false;

    export let hasStream: boolean = false;


    let isDragging: boolean = false;
    let startX: number;
    let startWidth: number;
    let mouseLeaveTimer: number | null;
    let mouseEnterTimer: number | null;
    let isMouseOver = false;
    let isPin = true;
    let streamView: Object;

    let items = [
        {
            "name": "summary",
            "label": Language.translate('Summary'),
        }
    ];
    let activeItem = items[0];

    let isMobile = false;

    const checkScreenSize = () => {
        isMobile = window.innerWidth <= 768;
    };



    $: sideViewWidth = isMobile ? `${window.innerWidth * 0.9}px` : (  isCollapsed ? 'auto' : `${currentWidth}px`);

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

        if(activeItem.name === 'activities') {
            refreshActivities()
        }

        Storage.set('right-side-view-active-item', scope, activeItem.name);
    }

    function handleCollapsePanel(e: Event) {
        updateCollapse(!isCollapsed);
    }

    function updateCollapse(value: boolean) {
        isCollapsed = value;
        Storage.set('right-side-view-collapse', scope, isCollapsed ? 'collapsed' : '');
        if(activeItem.name === 'activities') {
            refreshActivities();
        }
    }

    function handleMouseLeave() {
        if (isPin) {
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
                updateCollapse(true)
            }
        }, 500);
    }

    function handleMouseEnter() {
        if (isPin) {
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
                    updateCollapse(false)
                }
            }, 500);
        }
    }

    function handlePin(e: Event) {
        isPin = !isPin;
        Storage.set('right-side-view-pin', scope, isPin ? 'pin' : 'not-pinned');
    }

    function refreshActivities() {

        if(isCollapsed) {
            return;
        }

        if(streamView == null) {
            loadActivities((view) => {
                streamView = view;
            });
        }else{
            streamView?.refresh();
        }
    }

    onMount(() => {
        const savedWidth = Storage.get('rightSideView', scope);
        if (savedWidth) {
            currentWidth = parseInt(savedWidth) || minWidth;
        }

        checkScreenSize();

        isCollapsed = (Storage.get('right-side-view-collapse', scope) === 'collapsed') || isMobile;

        isPin = Storage.get('right-side-view-pin', scope) !== 'not-pinned' ;



        window.addEventListener("resize", checkScreenSize);


        loadSummary();

        if(hasStream) {
            items = [
                ...items,
                {
                    "name":"activities",
                    "label": Language.translate('Activities')
                }]
        }

        let itemName =   Storage.get('right-side-view-active-item', scope);

        if(itemName && items.map(i => i.name).includes(itemName)) {

            setActiveItem(items.find(i => i.name === itemName));
        }

        return () => {
            if (mouseLeaveTimer !== null) {
                clearTimeout(mouseLeaveTimer);
            }
            if (mouseEnterTimer !== null) {
                clearTimeout(mouseEnterTimer);
            }
            window.removeEventListener("resize", checkScreenSize);
        };
    })

</script>

<div class:expanded={!isCollapsed }
     class:not-pinned={!isPin || isMobile}></div>
<aside class="right-side-view" style="width: {sideViewWidth}"

       class:collapsed={isCollapsed}
       class:expanded={!isCollapsed}
       class:pinned={isPin && !isMobile}
       on:mouseenter={handleMouseEnter}
       on:mouseleave={handleMouseLeave}
>

    <div class="collapse-strip" on:click|self="{handleCollapsePanel}"></div>

    <button type="button"
            class="btn btn-link collapse-panel"
            on:click={handleCollapsePanel}>
        <span class="toggle-icon-left fas fa-angle-left" class:hidden={!isCollapsed}></span>
        <span class="toggle-icon-right fas fa-angle-right" class:hidden={isCollapsed}></span>
    </button>
    <div class="content">

        <div class="btn-group">
            {#each items as item}
                {#if item.name !== activeItem.name}
                    <a href="javascript:" on:click={()=>setActiveItem(item)}
                       class="btn btn-link item">
                        {item.label}
                    </a>
                {/if}
            {/each}
            {#if !isMobile}
                <button class="btn btn-link" style="padding: 0;border: 0; margin-left:-2px" on:click={handlePin}
                        title="{Language.translate(isPin ? 'enableAutomaticClosing': 'disableAutomaticClosing')}">
                    <img src="{GfiImage}" alt="image" class:hidden={!isPin}>
                    <img src="{GfiHideImage}" alt="hide_image" class:hidden={isPin}>
                </button>
            {/if}
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
        transition: .6s width cubic-bezier(0.19, 1, .22, 1);
    }

    .right-side-view:not(.pinned):not(.collapsed) {
        position: absolute;
        right: 0;
    }

    .collapse-strip {
        height: 100%;
        position: fixed;
        top: 0;
        margin-left: -10px;
        width: 20px;
        background-color: transparent;
        cursor: pointer;
    }

    .expanded .collapse-strip {
        margin-left: -20px;
    }

    .right-side-view > .content {
        opacity: 1;
        transition: opacity 0.3s ease;
    }

    .collapsed.right-side-view {
        padding: 15px;
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

    div.not-pinned.expanded {
        width: 30px;
    }

    div.not-pinned:not(.expanded) {
        width: 0;
    }

    .content .btn-group {
        display: flex;
    }

    :global(.right-side-view .row .cell .field) {
        padding-bottom: 6px;
        border-bottom: 1px solid var(--secondary-border-color);
    }

    :global(.right-side-view .panel-heading .panel-title .collapser) {
        display: none;
    }

    :global(.dropdown-menu.textcomplete-dropdown){
        z-index: 1300 !important;
    }

    @media (max-width: 768px) {
        .collapsed.right-side-view {
            position: fixed;
            width:0 !important;
            right: 20px;
            padding: 0;
            border-left: 0 solid transparent;
        }

        button.collapse-panel {
            bottom: 20px;
        }
    }


</style>