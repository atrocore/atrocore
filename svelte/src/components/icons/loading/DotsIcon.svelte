<span class="loading-icon dots"></span>

<style>
    .loading-icon.dots,
    .loading-icon.dots:before,
    .loading-icon.dots:after {
        border-radius: 50%;
        width: 2.5em;
        height: 2.5em;
        animation-fill-mode: both;
        animation: bblFadInOut 1.8s infinite ease-in-out;
    }

    .loading-icon.dots {
        color: #ccc;
        font-size: 7px;
        position: relative;
        text-indent: -9999em;
        transform: translateZ(0);
        animation-delay: -0.16s;
    }

    .loading-icon.dots:before,
    .loading-icon.dots:after {
        content: '';
        position: absolute;
        top: 0;
    }

    .loading-icon.dots:before {
        left: -3.5em;
        animation-delay: -0.32s;
    }

    .loading-icon.dots:after {
        left: 3.5em;
    }

    @keyframes bblFadInOut {
        0%, 80%, 100% { box-shadow: 0 2.5em 0 -1.3em }
        40% { box-shadow: 0 2.5em 0 0 }
    }
</style>