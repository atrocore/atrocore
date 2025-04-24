<script lang="ts">
    import ActionParams from "../interfaces/ActionParams";
    import ActionButton from "./ActionButton.svelte";

    export let scope: string;
    export let onFavoriteAdd: (scope: string) => void;
    export let onFavoriteRemove: (scope: string) => void;
    export let active: boolean = false;

    let params: ActionParams;
    let style: string = 'default';
    let iconStyle: string;

    $: {
        style = active ? 'primary outline' : 'default';
        iconStyle = active ? 'ph-fill' : 'ph';
        params = {
            action: "favorite",
            html: `<i class="${iconStyle} ph-star"></i>`,
            style: style
        } as ActionParams;
    }

    function execute(event: CustomEvent): void {
        if (active) {
            onFavoriteRemove(scope);
        } else {
            onFavoriteAdd(scope);
        }
    }
</script>

<ActionButton {params} on:execute={execute} className="btn-favorite"/>

<style>
    :global(.btn-favorite) {
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
    }
</style>