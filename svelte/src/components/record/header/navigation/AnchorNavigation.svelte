<script lang="ts">
    import AnchorNavItem from "../interfaces/AnchorNavItem";

    export let items: AnchorNavItem[];
</script>

{#if items}
    <ul class="nav-pills">
        {#each items as item}
            <li class="item">{item.title}</li>
        {/each}
    </ul>
{/if}