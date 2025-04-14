<script lang="ts">
    import {onMount} from 'svelte';
    import Swiper from 'swiper';
    import {Navigation, Thumbs, Zoom, FreeMode} from 'swiper/modules';
    import 'swiper/css';
    import 'swiper/css/navigation';
    import 'swiper/css/thumbs';
    import 'swiper/css/zoom';
    import 'swiper/css/free-mode';

    let mainSwiperEl: HTMLDivElement;
    let thumbsSwiperEl: HTMLDivElement;

    let thumbsSwiper: Swiper;
    let mainSwiper: Swiper;

    onMount(() => {
        thumbsSwiper = new Swiper(thumbsSwiperEl, {
            direction: 'vertical',
            slidesPerView: 4,
            spaceBetween: 10,
            freeMode: true,
            watchSlidesProgress: true,
            modules: [FreeMode],
        });

        mainSwiper = new Swiper(mainSwiperEl, {
            spaceBetween: 10,
            navigation: true,
            zoom: true,
            thumbs: {
                swiper: thumbsSwiper,
            },
            modules: [Navigation, Thumbs, Zoom],
        });
    });
</script>

<div class="gallery-wrapper">
    <div class="thumbs-wrapper">
        <div class="swiper thumbs-swiper" bind:this={thumbsSwiperEl}>
            <div class="swiper-wrapper">
                {#each [] as img}
                    <div class="swiper-slide">
                        <img src={img} class="thumb" />
                    </div>
                {/each}
            </div>
        </div>
    </div>

    <div class="main-gallery">
        <div class="swiper main-swiper" bind:this={mainSwiperEl}>
            <div class="swiper-wrapper">
                {#each [] as img}
                    <div class="swiper-slide">
                        <div class="swiper-zoom-container">
                            <img src={img} />
                        </div>
                    </div>
                {/each}
            </div>

            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>
</div>