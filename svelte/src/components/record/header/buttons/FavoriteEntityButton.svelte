<script lang="ts">
    import ActionButtonParams from "$lib/components/buttons/ActionButton/types/params";
    import ActionButton from "$lib/components/buttons/ActionButton/ActionButton.svelte";

    export let scope: string;
    export let onFavoriteAdd: (scope: string) => void;
    export let onFavoriteRemove: (scope: string) => void;
    export let active: boolean = false;

    let params: ActionButtonParams;
    let style: string = '';
    let iconStyle: string;

    $: {
        style = active ? 'primary outline' : '';
        iconStyle = active ? 'ph-fill' : 'ph';
        params = {
            action: "favorite",
            html: `<i class="${iconStyle} ph-star"></i>`,
            style: style
        } as ActionButtonParams;
    }

    function execute(event: CustomEvent): void {
        if (active) {
            onFavoriteRemove(scope);
        } else {
            onFavoriteAdd(scope);
        }
    }
</script>

<ActionButton {params} on:execute={execute} className="button-favorite"/>

<style>
    :global(.button-favorite) {
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
    }
</style>