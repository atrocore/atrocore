<script lang="ts">
    export let size: number = 40;
    export let thickness: number = 4;
</script>

<div class="loading-icon spinner" style:width="{size}px" style:height="{size}px" style:border-width="{thickness}px"></div>

<style>
    .loading-icon.spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #0081d1;
        border-bottom-color: transparent;
        border-radius: 50%;
        display: inline-block;
        box-sizing: border-box;
        animation: rotation 1s linear infinite;
    }

    @keyframes rotation {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }
</style>