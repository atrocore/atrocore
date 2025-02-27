<script lang="ts">
    import Breadcrumbs from "./navigation/Breadcrumbs.svelte";
    import BreadcrumbsItem from "./interfaces/BreadcrumbsItem";

    export let breadcrumbs: BreadcrumbsItem[] = [];


</script>

{#if breadcrumbs.length > 0}
    <div class="header-breadcrumbs">
        <Breadcrumbs items={breadcrumbs} />
    </div>
{/if}

<slot></slot>