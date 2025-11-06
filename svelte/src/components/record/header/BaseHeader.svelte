<script lang="ts">
    import Breadcrumbs from "./navigation/Breadcrumbs.svelte";
    import BreadcrumbsItem from "./interfaces/BreadcrumbsItem";
    import NavigationHistory from "./navigation/NavigationHistory.svelte";

    export let breadcrumbs: BreadcrumbsItem[] = [];
    export let disableNavigationHistory: boolean = false;
    export let currentIsHeading: boolean = true;
    export let scope: string | null = null;
    export let id: string | null = null;

</script>

{#if !disableNavigationHistory}
    <NavigationHistory {scope} {id} tabId={sessionStorage.tabId} />
{/if}

<div class="header-wrapper">
    {#if breadcrumbs.length > 0}
        <nav class="header-breadcrumbs">
            <Breadcrumbs items={breadcrumbs} {currentIsHeading} />
        </nav>
    {/if}

    <slot></slot>
</div>
