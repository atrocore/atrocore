<script lang="ts">
    import {onMount} from 'svelte';
    import Swiper from 'swiper';
    import {Navigation, Thumbs, Zoom, FreeMode} from 'swiper/modules';

    import 'swiper/css';
    import 'swiper/css/navigation';
    import 'swiper/css/thumbs';
    import 'swiper/css/zoom';
    import 'swiper/css/free-mode';

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

    let currentIndex: number = 0;
    let currentMedia: GalleryMedia;
    $: currentMedia = mediaList[currentIndex];

    let thumbsSwiper: Swiper;
    let mainSwiper: Swiper;

    let mainSwiperEl: HTMLDivElement;
    let thumbsSwiperEl: HTMLDivElement;
    let swiperNextBtn: HTMLDivElement;
    let swiperPrevBtn: HTMLDivElement;

    onMount(() => {
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
            },
            modules: [FreeMode],
        });

        mainSwiper = new Swiper(mainSwiperEl, {
            initialSlide: currentMediaId !== null ? mediaList.findIndex(media => media.id === currentMediaId) : 0,
            spaceBetween: 10,
            zoom: true,
            thumbs: {
                swiper: thumbsSwiper,
            },
            navigation: {
                enabled: true,
                nextEl: swiperNextBtn,
                prevEl: swiperPrevBtn,
            },
            virtual: {
                enabled: true,
            },
            modules: [Navigation, Thumbs, Zoom],
            on: {
                slideChange: (swiper) => currentIndex = swiper.activeIndex
            },
        });
    });
</script>

<div class="gallery-header">
    {#if currentMedia}
        <div class="buttons-container"></div>
        <a target="_blank" href={`#File/view/${currentMedia.id}`}>{currentMedia.name}</a>
    {/if}
</div>

<div class="gallery-wrapper">
    <div class="thumbs-wrapper">
        <div class="swiper thumbs-swiper" bind:this={thumbsSwiperEl}>
            <div class="swiper-wrapper">
                {#each mediaList as media}
                    <div class="swiper-slide">
                        <img src={media.smallThumbnail} alt={media.name} class="thumb"/>
                    </div>
                {/each}
            </div>
        </div>
    </div>

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
        justify-content: center;
        align-items: center;
        min-height: 43px;
        padding: 5px 0;
    }

    .gallery-header > a {
        color: var(--primary-font-color)
    }

    .gallery-wrapper {
        --swiper-navigation-size: 20px;
        --swiper-navigation-color: var(--action-icon-color);
        --swiper-preloader-color: #06c;

        display: flex;
        flex: 1;
        min-height: 0;
        margin-top: 10px;
        user-select: none;
    }

    .thumbs-wrapper {
        width: 100px;
        flex-shrink: 0;
        margin-right: 10px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
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
    }

    .thumb {
        max-width: 100%;
        max-height: 100%;
        object-fit: cover;
        border-radius: 4px;
        opacity: 0.6;
        transition: opacity 0.2s;
    }

    .gallery-wrapper :global(.swiper-slide-thumb-active .thumb) {
        opacity: 1;
        border: 2px solid #007aff;
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
</style>