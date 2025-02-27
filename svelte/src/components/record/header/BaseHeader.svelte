<script lang="ts">
    import Breadcrumbs from "./navigation/Breadcrumbs.svelte";
    import BreadcrumbsItem from "./interfaces/BreadcrumbsItem";

    export let breadcrumbs: BreadcrumbsItem[] = [];
    export let currentIsHeading: boolean = true;
</script>

{#if breadcrumbs.length > 0}
    <div class="header-breadcrumbs">
        <Breadcrumbs items={breadcrumbs} {currentIsHeading} />
    </div>
{/if}

<slot></slot>