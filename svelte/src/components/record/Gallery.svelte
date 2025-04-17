<script lang="ts">
    import {onMount} from 'svelte';
    import Swiper from 'swiper';
    import {FreeMode, Mousewheel, Navigation, Scrollbar, Thumbs, Zoom} from 'swiper/modules';

    import ActionButton from "./header/buttons/ActionButton.svelte";
    import ActionParams from "./header/interfaces/ActionParams";

    import {Language} from "../../utils/Language";

    import DownloadIcon from "$assets/icons/download.svg?raw";
    import ZoomInIcon from "$assets/icons/zoom_in.svg?raw";
    import ZoomOutIcon from "$assets/icons/zoom_out.svg?raw";

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

    const downloadActionParams: ActionParams = {
        style: 'default',
        html: `${DownloadIcon}<span>${Language.translate('Download')}</span>`,
        action: 'download'
    } as ActionParams;

    const zoomActionParams: ActionParams = {
        style: 'default',
        tooltip: Language.translate('doubleClickZoom'),
        action: 'zoom'
    } as ActionParams;

    let isZoomActive: boolean = false;
    $: zoomActionParams.html = isZoomActive ? `${ZoomOutIcon}<span>${Language.translate('zoomOut')}</span>` : `${ZoomInIcon}<span>${Language.translate('zoomIn')}</span>`;

    let currentIndex: number = 0;
    let currentMedia: GalleryMedia;
    $: currentMedia = mediaList[currentIndex];

    let thumbsSwiper: Swiper | null = null;
    let mainSwiper: Swiper;

    let mainSwiperEl: HTMLDivElement;
    let thumbsSwiperEl: HTMLDivElement;
    let thumbsScrollEl: HTMLDivElement;
    let swiperNextBtn: HTMLDivElement;
    let swiperPrevBtn: HTMLDivElement;

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
                init: (swiper: Swiper) => {
                    if (thumbsSwiper) {
                        thumbsSwiper.slideTo(initialSlide);
                    }
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
    </div>
</div>

<div class="gallery-wrapper">
    {#if mediaList.length > 1}
        <div class="thumbs-wrapper">
            <div class="swiper thumbs-swiper" bind:this={thumbsSwiperEl}>
                <div class="swiper-wrapper">
                    {#each mediaList as media}
                        <div class="swiper-slide thumb">
                            <img src={media.smallThumbnail} alt={media.name} />
                        </div>
                    {/each}
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
                                 loading="lazy"/>
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

    .gallery-header > .buttons-container > :global(.btn) {
        border-radius: 3px;
        line-height: 1;
        padding: 4px 8px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .gallery-header > .buttons-container > :global(.btn svg) {
        width: 19px;
        height: 19px;
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
        height: 100%;
        transition: transform 0.3s;
        user-select: none;
        object-fit: scale-down;
    }

    @media screen and (max-width: 768px) {
        .thumbs-wrapper {
            display: none;
        }
    }
</style>