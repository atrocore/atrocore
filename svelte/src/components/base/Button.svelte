<script lang="ts">
    enum ButtonType {
        Default,
        Primary,
        Secondary,
        Danger,
        Success,
        Warning,
    }

    enum ButtonSize {
        Regular,
        Small,
        Large
    }

    export let style: ButtonType = ButtonType.Default;
    export let size: ButtonSize = ButtonSize.Regular;
    export let html: string | null;
    export let label: string | null;
    export let onClick: Function | null;

    let className: string = 'btn';
</script>

<button type="button" class={className} on:click={onClick}>{#if html}{@html html}{:else}{label}{/if}</button>