<script lang="ts">
    import AnchorNavItem from "../interfaces/AnchorNavItem";

    export let items: AnchorNavItem[];
    export let scrollCallback = (panelName: string, event: Event): void => {}

    function onClick(event: Event): void {
        event.preventDefault();

        const el = event.target as HTMLElement;

        if (el.dataset.name) {
            scrollCallback(el.dataset.name as string, event);
        }
    }
</script>

{#if items}
    <ul class="nav-pills">
        {#each items as item}
            <li class="item"><a href="javascript:" data-name={item.name} on:click={onClick}>{item.title ?? item.name}</a></li>
        {/each}
    </ul>
{/if}

<style>
    .nav-pills {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 0;
        margin: 0;
        list-style: none;
        cursor: grab;
    }

    .nav-pills > li > a {
        display: block;
        border-bottom: 2px solid transparent;
        padding: 0 7px 5px;
        color: #7C848B;
        transition: border-bottom-color .2s ease, color .2s ease;
        user-select: none;
        font-size: 13px;
        -webkit-user-drag: none;
    }

    .nav-pills > li > a, .nav-pills > li > a:hover, .nav-pills > li > a:focus {
        background-color: transparent;
        text-decoration: none;
    }

    .nav-pills > li > a:hover, .nav-pills > li > a:focus, .nav-pills > li.active > a  {
        color: #1a75d1;
        border-bottom-color: #1a75d1;
    }
</style>