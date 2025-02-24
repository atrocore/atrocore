<script lang="ts">
    import BaseHeader from "./BaseHeader.svelte";
    import Params from "./interfaces/Params"
    import {onMount} from "svelte";

    export let params: Params;

    onMount(() => {
        if (params.afterOnMount) {
            params.afterOnMount();
        }
    });
</script>

<BaseHeader breadcrumbs={params.breadcrumbs}></BaseHeader>

<h1>This is list header content</h1>
