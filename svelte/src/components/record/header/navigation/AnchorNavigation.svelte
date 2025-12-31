<script lang="ts">
    import AnchorNavItem from "../interfaces/AnchorNavItem";
    import {onDestroy, onMount, tick} from "svelte";

    export let items: AnchorNavItem[];
    export let scrollCallback = (panelName: string, event: Event): void => {
    };
    export let hasLayoutEditor: boolean = false;

    export let afterOnMount: Function = () => {}

    let container: HTMLDivElement;
    let navPills: HTMLUListElement;

    let isDown: boolean = false;
    let dragging: boolean = false;
    let startX: number = 0;
    let prevX: number = 0;
    let currentTranslate: number = 0;
    let prevTranslate: number = 0;
    let lastTime: number = 0;
    let velocity: number = 0;
    let momentumID: number | null = null;

    let containerWidth: number = 0;
    let contentWidth: number = 0;
    let maxTranslate: number = 0;
    const dragThreshold: number = 5;

    $: canScroll = contentWidth > containerWidth;
    $: showLeft = canScroll && currentTranslate < 0;
    $: showRight = canScroll && currentTranslate > maxTranslate;

    async function updateDimensions() {
        await tick();
        containerWidth = container.clientWidth;
        contentWidth = navPills.scrollWidth;
        maxTranslate = containerWidth - contentWidth;
        if (maxTranslate > 0) maxTranslate = 0;
        currentTranslate = clamp(currentTranslate, maxTranslate, 0);
        navPills.style.transform = `translateX(${currentTranslate}px)`;
        prevTranslate = currentTranslate;
    }

    function clamp(value: number, min: number, max: number) {
        return Math.min(Math.max(value, min), max);
    }

    function scrollIntoViewForElement(el: HTMLElement) {
        const elOffsetLeft = el.offsetLeft;
        const elWidth = el.offsetWidth;
        const viewStart = -currentTranslate;
        const viewEnd = viewStart + containerWidth;

        let newTranslate = currentTranslate;

        if (elOffsetLeft < viewStart) {
            newTranslate = -elOffsetLeft;
        } else if (elOffsetLeft + elWidth > viewEnd) {
            newTranslate = -(elOffsetLeft + elWidth - containerWidth);
        }

        newTranslate = clamp(newTranslate, maxTranslate, 0);
        currentTranslate = newTranslate;
        prevTranslate = currentTranslate;
        navPills.style.transform = `translateX(${currentTranslate}px)`;
    }

    function handleMouseDown(e: MouseEvent) {
        isDown = true;
        dragging = false;
        startX = e.pageX;
        prevX = startX;
        lastTime = performance.now();
        cancelMomentum();
    }

    function handleMouseMove(e: MouseEvent) {
        if (!isDown) return;
        const currentX = e.pageX;
        const deltaX = currentX - prevX;
        const now = performance.now();
        const dt = now - lastTime;
        velocity = deltaX / dt;
        if (Math.abs(currentX - startX) > dragThreshold) {
            dragging = true;
        }
        currentTranslate = clamp(prevTranslate + (currentX - startX), maxTranslate, 0);
        navPills.style.transform = `translateX(${currentTranslate}px)`;
        prevX = currentX;
        lastTime = now;
    }

    function handleMouseUp() {
        if (!isDown) return;
        isDown = false;
        prevTranslate = currentTranslate;
        startMomentum();
        setTimeout(() => dragging = false, 0);
    }

    function handleTouchStart(e: TouchEvent) {
        isDown = true;
        dragging = false;
        startX = e.touches[0].pageX;
        prevX = startX;
        lastTime = performance.now();
        cancelMomentum();
    }

    function handleTouchMove(e: TouchEvent) {
        if (!isDown) return;
        const currentX = e.touches[0].pageX;
        const deltaX = currentX - prevX;
        const now = performance.now();
        const dt = now - lastTime;
        velocity = deltaX / dt;
        if (Math.abs(currentX - startX) > dragThreshold) {
            dragging = true;
        }
        currentTranslate = clamp(prevTranslate + (currentX - startX), maxTranslate, 0);
        navPills.style.transform = `translateX(${currentTranslate}px)`;
        prevX = currentX;
        lastTime = now;
    }

    function handleTouchEnd() {
        if (!isDown) return;
        isDown = false;
        prevTranslate = currentTranslate;
        startMomentum();
        setTimeout(() => dragging = false, 0);
    }

    function startMomentum() {
        const decay = 0.95;

        function momentum() {
            velocity *= decay;
            if (Math.abs(velocity) > 0.02) {
                currentTranslate = clamp(currentTranslate + velocity * 16, maxTranslate, 0);
                navPills.style.transform = `translateX(${currentTranslate}px)`;
                momentumID = requestAnimationFrame(momentum);
            } else {
                cancelMomentum();
            }
            prevTranslate = currentTranslate;
        }

        momentumID = requestAnimationFrame(momentum);
    }

    function cancelMomentum() {
        if (momentumID !== null) {
            cancelAnimationFrame(momentumID);
            momentumID = null;
        }
    }

    function handleWheel(e: WheelEvent) {
        if (e.shiftKey) {
            e.preventDefault();
            currentTranslate = clamp(currentTranslate - e.deltaY, maxTranslate, 0);
            navPills.style.transform = `translateX(${currentTranslate}px)`;
            prevTranslate = currentTranslate;
        }
    }

    let observer: MutationObserver;
    let resizeObserver: ResizeObserver;
    onMount(() => {
        updateDimensions();

        resizeObserver = new ResizeObserver(() => {
            updateDimensions();
        });

        observer = new MutationObserver(() => {
            updateDimensions();
        });

        observer.observe(container, {childList: true, subtree: true});
        resizeObserver.observe(container);

        tick().then(() => {
            afterOnMount();
        })
    });

    onDestroy(() => {
        if (observer) observer.disconnect();
        if (resizeObserver) resizeObserver.disconnect();
    });

    function onClick(event: Event): void {
        event.preventDefault();

        if (dragging) {
            event.stopPropagation();
            return;
        }

        const el = event.currentTarget as HTMLElement;
        const li = el.closest('li') as HTMLElement;
        if (li) {
            scrollIntoViewForElement(li);
        }

        if (el.dataset.name) {
            scrollCallback(el.dataset.name as string, event);
        }
    }
</script>

<div class="panel-navigation">
    {#if items}
        <div class="items-container" class:has-left-scroll={showLeft} class:has-right-scroll={showRight}
             bind:this={container}
             on:mousedown={handleMouseDown}
             on:mousemove={handleMouseMove}
             on:mouseup={handleMouseUp}
             on:mouseleave={handleMouseUp}
             on:touchstart={handleTouchStart}
             on:touchmove={handleTouchMove}
             on:touchend={handleTouchEnd}
             on:wheel={handleWheel}
        >
            <ul class="nav-pills" bind:this={navPills}>
                {#each items as item}
                    <li class="item"><a href="javascript:" data-name={item.name} on:click={onClick}>{item.title ?? item.name}</a></li>
                {/each}
            </ul>
        </div>
    {/if}
    {#if hasLayoutEditor}
        <div class="layout-editor-container"></div>
    {/if}
</div>

<style>
    .panel-navigation {
        display: flex;
        position: relative;
        overflow-x: clip;
    }

    .panel-navigation > .layout-editor-container {
        position: sticky;
        left: 0;
        margin-left: 14px;
    }

    .items-container {
        position: relative;
        display: flex;
        overflow: hidden;
    }

    .items-container:before,
    .items-container:after {
        position: absolute;
        top: 0;
        bottom: 0;
        z-index: 1;
        width: 25px;
        pointer-events: none;
        display: block;
    }

    .items-container.has-left-scroll:before {
        content: '';
        left: 0;
        background: linear-gradient(to right, rgba(255, 255, 255, 0.8) 0%, transparent 100%);
    }

    .items-container.has-right-scroll:after {
        content: '';
        right: 0;
        background: linear-gradient(to left, rgba(255, 255, 255, 0.8) 0%, transparent 100%);
    }

    .panel-navigation > .layout-editor-container :global(> a) {
        width: 18px;
    }

    .nav-pills {
        display: flex;
        gap: 10px;
        padding: 0;
        margin: 0;
        list-style: none;
        cursor: grab;
        transition: transform 0.1s ease-out;
        will-change: transform;
    }

    .nav-pills > li > a {
        display: block;
        border-bottom: 2px solid transparent;
        padding: 0 7px 5px;
        color: #7C848B;
        transition: border-bottom-color .2s ease, color .2s ease;
        user-select: none;
        font-size: 13px;
        -webkit-user-drag: none;
        white-space: nowrap;
    }

    .nav-pills > li > a, .nav-pills > li > a:hover, .nav-pills > li > a:focus {
        background-color: transparent;
        text-decoration: none;
    }

    .nav-pills > li > a:hover, .nav-pills > li > a:focus, .nav-pills > li.active > a {
        color: #1a75d1;
        border-bottom-color: #1a75d1;
    }
</style>
