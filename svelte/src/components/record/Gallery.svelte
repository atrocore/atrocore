<script lang="ts">
    import {onMount, onDestroy, tick} from 'svelte';
    import Swiper from 'swiper';
    import {FreeMode, Mousewheel, Navigation, Scrollbar, Thumbs, Zoom} from 'swiper/modules';

    import ActionButton from '$lib/components/buttons/ActionButton/ActionButton.svelte';;
    import ActionParams from "./header/interfaces/ActionParams";
    import Preloader from "../icons/loading/Preloader.svelte";

    import { Language } from "$lib/core/language"

    import 'swiper/css';
    import 'swiper/css/navigation';
    import 'swiper/css/thumbs';
    import 'swiper/css/zoom';
    import 'swiper/css/free-mode';
    import 'swiper/css/scrollbar';

    interface GalleryMedia {
        id: string;
        name: string;
        url: string;
        smallThumbnail: string;
        largeThumbnail: string;
        isImage: boolean;
    }

    export let mediaList: GalleryMedia[] = [];
    export let currentMediaId: string | null = null;
    export let canLoadMore: boolean = false;
    export let onLoadMore: () => void = () => {};

    const downloadActionParams: ActionParams = {
        style: 'default',
        size: 'small',
        html: `<i class="ph ph-download-simple"></i><span>${Language.translate('Download')}</span>`,
        action: 'download'
    } as ActionParams;

    const zoomActionParams: ActionParams = {
        style: 'default',
        size: 'small',
        tooltip: Language.translate('doubleClickZoom'),
        action: 'zoom'
    } as ActionParams;

    let isLoadingMore: boolean = false;
    let isZoomActive: boolean = false;
    $: zoomActionParams.html = (isZoomActive ? `<i class="ph ph-magnifying-glass-minus"></i>` : `<i class="ph ph-magnifying-glass-plus"></i>`) + `<span>${Language.translate('galleryZoom')}</span>`;

    let currentIndex: number = 0;
    let currentMedia: GalleryMedia;
    $: currentMedia = mediaList[currentIndex];

    let showTransparentBackground: boolean = true;
    const transparentActionParams: ActionParams = {
        style: 'default',
        size: 'small',
        action: 'toggleAlpha',
    } as ActionParams;
    $: {
        transparentActionParams.html = (showTransparentBackground ? `<i class="ph ph-checkerboard"></i>` : `<i class="ph ph-square"></i>`) + `<span>${Language.translate('galleryBackground')}</span>`;
        transparentActionParams.disabled = !['png', 'svg', 'tiff', 'webp'].includes(currentMedia.name.split('.').pop() || '');
    }

    let thumbsSwiper: Swiper | null = null;
    let mainSwiper: Swiper;

    let mainSwiperEl: HTMLDivElement;
    let thumbsSwiperEl: HTMLDivElement;
    let thumbsScrollEl: HTMLDivElement;
    let swiperNextBtn: HTMLDivElement;
    let swiperPrevBtn: HTMLDivElement;

    let prevMediaList: GalleryMedia[] = [];
    window.addEventListener('gallery:load-more:success', (e: CustomEvent<{mediaList: GalleryMedia[], canLoadMore: boolean}>) => {
        isLoadingMore = false;
        prevMediaList = mediaList;
        mediaList = e.detail.mediaList;
        canLoadMore = e.detail.canLoadMore;

        if (JSON.stringify(prevMediaList) !== JSON.stringify(mediaList)) {
            tick().then(() => reloadSwiper());
        }
    });

    onMount(() => {
        const initialSlide: number = currentMediaId !== null ? mediaList.findIndex(media => media.id === currentMediaId) : 0;
        const swiperOptions: Record<string, any> = {
            initialSlide: initialSlide,
            spaceBetween: 10,
            zoom: true,
            navigation: {
                enabled: true,
                nextEl: swiperNextBtn,
                prevEl: swiperPrevBtn,
            },
            modules: [Navigation, Zoom],
            on: {
                init: () => {
                    thumbsSwiper?.slideTo(initialSlide);
                },
                slideChange: (swiper: Swiper) => {
                    currentIndex = swiper.activeIndex;
                    if (isZoomActive) {
                        swiper.zoom.out();
                    }
                },
                zoomChange: (_swiper: Swiper, scale: number) => {
                    isZoomActive = scale !== 1;
                }
            },
        };

        if (thumbsSwiperEl) {
            thumbsSwiper = new Swiper(thumbsSwiperEl, {
                direction: 'vertical',
                slidesPerView: 'auto',
                spaceBetween: 10,
                freeMode: {
                    enabled: true,
                    sticky: true,
                },
                watchSlidesProgress: true,
                mousewheel: {
                    enabled: true,
                    forceToAxis: true,
                },
                scrollbar: {
                    enabled: true,
                    el: thumbsScrollEl,
                    draggable: true,
                    snapOnRelease: true,
                },
                modules: [FreeMode, Mousewheel, Scrollbar],
            });
            swiperOptions.thumbs = {swiper: thumbsSwiper};
            swiperOptions.modules = [...swiperOptions.modules, Thumbs];
        }

        mainSwiper = new Swiper(mainSwiperEl, swiperOptions);
    });

    onDestroy(() => {
        mainSwiper?.destroy(true, true);
        thumbsSwiper?.destroy(true, true);
    });

    function reloadSwiper(): void {
        const needsThumbs = mediaList.length > 1 || canLoadMore;

        if (!needsThumbs && thumbsSwiper) {
            thumbsSwiper.destroy(true, true);
            thumbsSwiper = null;
        }

        if (needsThumbs && thumbsSwiperEl && !thumbsSwiper) {
            thumbsSwiper = new Swiper(thumbsSwiperEl, {
                direction: 'vertical',
                slidesPerView: 'auto',
                spaceBetween: 10,
                freeMode: {
                    enabled: true,
                    sticky: true,
                },
                watchSlidesProgress: true,
                mousewheel: {
                    enabled: true,
                    forceToAxis: true,
                },
                scrollbar: {
                    enabled: true,
                    el: thumbsScrollEl,
                    draggable: true,
                    snapOnRelease: true,
                },
                modules: [FreeMode, Mousewheel, Scrollbar],
            });

            if (mainSwiper) {
                mainSwiper.params.modules = mainSwiper.params.modules?.concat([Thumbs]);
                mainSwiper.params.thumbs = { swiper: thumbsSwiper };
            }
        }

        mainSwiper.update();
    }

    function onDownloadMedia(): void {
        const link = document.createElement('a');
        link.href = currentMedia.url;
        link.download = currentMedia.name || 'file';
        link.rel = 'noopener';
        link.target = '_self';
        link.click();
    }

    function onToggleZoom(): void {
        mainSwiper.zoom.toggle();
    }

    function onToggleAlpha(): void {
        showTransparentBackground = !showTransparentBackground;
    }

    function handleLoadMoreClick() {
        if (isLoadingMore) return;
        isLoadingMore = true;
        onLoadMore();
    }
</script>

<div class="gallery-header">
    {#if currentMedia}
        <a target="_blank" href={`#File/view/${currentMedia.id}`}>{currentMedia.name}</a>
    {/if}
    <div class="buttons-container">
        {#if currentMedia?.url}
            <ActionButton params={downloadActionParams} on:execute={onDownloadMedia} />
        {/if}
        <ActionButton params={zoomActionParams} on:execute={onToggleZoom} />
        <ActionButton params={transparentActionParams} on:execute={onToggleAlpha} />
    </div>
</div>

<div class="gallery-wrapper">
    {#if mediaList.length > 1 || canLoadMore}
        <div class="thumbs-wrapper">
            <div class="swiper thumbs-swiper" bind:this={thumbsSwiperEl}>
                <div class="swiper-wrapper">
                    {#each mediaList as media}
                        <div class="swiper-slide thumb">
                            <img src={media.smallThumbnail} alt={media.name} />
                        </div>
                    {/each}

                    {#if canLoadMore}
                        <div class="swiper-slide thumb load-more-thumb" class:no-border={isLoadingMore}>
                            {#if isLoadingMore}
                                <Preloader />
                            {:else}
                                <span on:click|stopPropagation={handleLoadMoreClick}>Load more</span>
                            {/if}
                        </div>
                    {/if}
                </div>
                <div class="thumbs-scrollbar swiper-scrollbar" bind:this={thumbsScrollEl}></div>
            </div>
        </div>
    {/if}

    <div class="main-gallery">
        <div class="swiper main-swiper" bind:this={mainSwiperEl}>
            <div class="swiper-wrapper">
                {#each mediaList as media}
                    <div class="swiper-slide">
                        <div class="swiper-zoom-container">
                            <img src={media.isImage ? media.url : media.largeThumbnail} alt={media.name}
                                 loading="lazy" class:transparent-bg={showTransparentBackground} />
                            <div class="swiper-lazy-preloader"></div>
                        </div>
                    </div>
                {/each}
            </div>

            <div bind:this={swiperNextBtn} class="swiper-button-next"></div>
            <div bind:this={swiperPrevBtn} class="swiper-button-prev"></div>
        </div>
    </div>
</div>

<style>
    .gallery-header {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
        align-items: baseline;
        min-height: 43px;
        padding: 10px 60px 10px 10px;
        border-bottom: 1px solid #f9f9f9;
    }

    .gallery-header > .buttons-container {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 0;
    }

    .gallery-header > a {
        color: var(--primary-font-color)
    }

    .gallery-wrapper {
        --swiper-navigation-size: 13px;
        --swiper-navigation-color: var(--action-icon-color);
        --swiper-preloader-color: #06c;

        display: flex;
        flex: 1;
        min-height: 0;
        gap: 20px;
        margin-top: 10px;
        user-select: none;
    }

    .gallery-wrapper .swiper-button-prev,
    .gallery-wrapper .swiper-button-next {
        background: #fafafa;
        width: 45px;
        height: 45px;
        border-radius: 50%;
        border: 1px solid var(--primary-border-color);
        transition: background 0.2s;
    }

    .gallery-wrapper .swiper-button-prev:not(.swiper-button-disabled):hover,
    .gallery-wrapper .swiper-button-next:not(.swiper-button-disabled):hover {
        background-color: #f0f0f0;
    }

    .gallery-wrapper .swiper-button-prev:not(.swiper-button-disabled):active,
    .gallery-wrapper .swiper-button-next:not(.swiper-button-disabled):active {
        background-color: #efefef;
    }

    .thumbs-wrapper {
        width: 100px;
        flex-shrink: 0;
        display: flex;
        flex-direction: column;
        overflow-y: clip;
        position: relative;
    }

    .thumbs-wrapper .swiper-slide {
        height: auto !important;
        max-height: 100px;
        width: 100px !important;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .thumbs-swiper {
        height: 100%;
        position: relative;
        overflow-y: clip;
        overflow-x: visible;
    }

    .thumbs-scrollbar {
        right: auto;
        left: calc(100% + 5px);
    }

    .thumb {
        padding: 2px;
        border-radius: 4px;
        transition: opacity 0.2s;
        border: 1px solid var(--primary-border-color);
        cursor: pointer;
    }

    .thumb > img {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
        opacity: 0.7;
        border-radius: inherit;
        transition: opacity 0.2s;
    }

    .thumb:hover > img {
        opacity: 1;
    }

    .thumb:global(.swiper-slide-thumb-active) {
        border-color: #06c;
    }

    .thumb:global(.swiper-slide-thumb-active) > img {
        opacity: 1;
    }

    .main-gallery {
        flex: 1;
        position: relative;
        min-width: 0;
    }

    .main-swiper {
        max-width: 100%;
        max-height: 100%;
        width: 100%;
        height: 100%;
    }

    .swiper-zoom-container img {
        max-width: 100%;
        max-height: 100%;
        height: auto;
        width: auto;
        object-fit: unset;
        user-select: none;
        transition: transform 0.3s;
    }

    .swiper-zoom-container img.transparent-bg {
        background-image: linear-gradient(45deg, #ccc 25%, transparent 25%),
            linear-gradient(-45deg, #ccc 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, #ccc 75%),
            linear-gradient(-45deg, transparent 75%, #ccc 75%);
        background-size: 20px 20px;
        background-position: 0 0, 0 10px, 10px -10px, -10px 0;
    }

    .load-more-thumb {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        min-height: 30px;
    }

    .load-more-thumb:hover {
        background-color: #eaeaea;
    }

    .load-more-thumb.no-border {
        border: none !important;
    }

    .load-more-thumb > span {
        font-size: 12px;
        color: var(--primary-font-color);
        cursor: pointer;
        user-select: none;
        text-align: center;
        padding: 5px;
    }

    .load-more-thumb:global(.swiper-slide-thumb-active) {
        border-color: var(--primary-border-color);
    }

    .load-more-thumb:global(.swiper-slide-thumb-active) > span {
        opacity: 0.8;
    }

    @media screen and (max-width: 768px) {
        .gallery-header {
            padding-top: 15px;
        }

        .gallery-header > .buttons-container {
            flex-basis: 100%;
        }

        .gallery-wrapper {
            margin-top: 0;
        }

        .thumbs-wrapper {
            display: none;
        }
    }
</style>