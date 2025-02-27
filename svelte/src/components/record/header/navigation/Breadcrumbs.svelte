<script lang="ts">
    import BreadcrumbsItem from "../interfaces/BreadcrumbsItem";

    export let items: BreadcrumbsItem[] = [];
    export let currentIsHeading: boolean = true;
</script>

<ul class="breadcrumbs-wrapper">
    {#each items as item, index}
        <li class="breadcrumbs-item" class:full-width={index === items.length - 1 && currentIsHeading}>
            {#if index !== items.length - 1}
                <a href="{item.url}" class={item.className}>{#if item.html}{@html item.html}{:else}{item.label}{/if}</a>
            {:else}
                {#if currentIsHeading}
                    <h3 class={item.className}>{#if item.html}{@html item.html}{:else}{item.label}{/if}</h3>
                {:else}
                    <span class={item.className}>{#if item.html}{@html item.html}{:else}{item.label}{/if}</span>
                {/if}
            {/if}
        </li>
    {/each}
</ul>

<style>
    .breadcrumbs-wrapper {
        display: block;
        padding: 0;
        margin: 0;
        line-height: 2;
    }

    .breadcrumbs-item {
        display: inline;
        color: #000;
    }

    .breadcrumbs-item.full-width {
        display: block;
    }

    .breadcrumbs-item:not(:last-child):after {
        content: " / ";
        margin: 0 0.25em;
        color: #1a75d1;
    }

    .breadcrumbs-item > a {
        color: inherit;
    }

    .breadcrumbs-item > h3 {
        font-size: 20px;
    }
</style>