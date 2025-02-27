<script lang="ts">
    import BaseHeader from "./BaseHeader.svelte";
    import Params from "./interfaces/Params"
    import {onMount} from "svelte";

    export let params: Params;
    export let renderSearch = (): void => {
    }

    onMount(() => {
        if (params.afterOnMount) {
            params.afterOnMount();
        }

        renderSearch();
    });
</script>

<BaseHeader breadcrumbs={params.breadcrumbs}></BaseHeader>

<div class="search-container"></div>