<script lang="ts">
    import ActionParams from "../interfaces/ActionParams";
    import ActionButton from "./ActionButton.svelte";

    export let entity: string;
    export let id: string;
    export let bookmarkId: string | null = null;
    export let loading: boolean = false;

    let style: string;
    let params: ActionParams;

    $: {
        style = bookmarkId ? 'primary' : 'default';
        params = {
            name: 'bookmarking',
            action: bookmarkId ? 'unbookmark' : 'bookmark',
            html: '<span class="fas fa-bookmark"></span>',
            style: style,
        } as ActionParams;
    }

    function addBookmark(): void {}

    function removeBookmark(): void {}

    function execute(e: CustomEvent): void {
        if (params.action === 'unbookmark') {
            removeBookmark();
        } else {
            addBookmark();
        }
    }
</script>

<ActionButton {params} on:execute={execute}/>