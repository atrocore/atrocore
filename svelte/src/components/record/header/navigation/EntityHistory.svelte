<script lang="ts">
    import {Language} from "../../../../utils/Language";
    import {onMount} from "svelte";
    import Preloader from "../../../icons/loading/Preloader.svelte";
    import {UserData} from "../../../../utils/UserData";

    interface LastEntityRecord {
        name: string;
        label: string;
        link: string;
    }

    export let scope: string;
    export let currentIsHeading: boolean = true;

    let items: LastEntityRecord[];

    let lastEntities: string[] = [];
    let loading = false;

    $: items = lastEntities.reverse().map((item: string) => ({
        name: item,
        label: Language.translate(item, 'scopeNamesPlural'),
        link: `#${item}`,
    }) as LastEntityRecord).concat({
        name: scope,
        label: Language.translate(scope, 'scopeNamesPlural'),
        link: `#${scope}`,
    } as LastEntityRecord);

    async function loadLastEntities(): Promise<string[]> {
        let userData = UserData.get();
        if (!userData) {
            return [];
        }

        lastEntities = [];
        try {
            let url = '/api/v1/LastViewed/action/getLastEntities'
            const params = new URLSearchParams({
                'maxSize': '3',
                'entity': scope
            }).toString();

            if (params) {
                url += `?${params}`;
            }

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization-Token': btoa(userData.user.userName + ':' + userData.token)
                },
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            return await response.json();
        } catch (error) {
            console.error('Error:', error);
            return [];
        }
    }

    onMount(() => {
        loading = true;
        loadLastEntities()
            .then((list) => {
                lastEntities = list;
            })
            .catch((error) => {
                console.error("Error: ", error);
            })
            .finally(() => loading = false);
    });
</script>

<nav class="entity-history">
    {#if loading}
        <Preloader heightPx={10}/>
    {/if}

    {#if items.length > 0}
        <ul>
            {#each items as item, index}
                {#if index !== items.length - 1}
                    <li><a href={item.link}>{item.label}</a></li>
                {:else}
                    {#if currentIsHeading}
                        <li class="full-width"><h3 class="header-title">{item.label}</h3></li>
                    {:else}
                        <li><span>{item.label}</span></li>
                    {/if}
                {/if}
            {/each}
        </ul>
    {/if}
</nav>


<style>
    .entity-history {
        margin-bottom: 10px;
    }

    nav > ul {
        display: block;
        margin: 0;
        padding: 0;
    }

    nav > ul > li {
        display: inline;
        color: #000;
    }

    nav > ul > li.full-width {
        display: block;
        margin: 10px 0;
    }

    nav > ul > li:not(:last-child):after {
        content: "";
        display: inline-block;
        width: 16px;
        height: 16px;
        vertical-align: middle;
        margin: 0 .2em;
        mask-image: url('$assets/icons/chevron_right.svg');
        background-color: #1a75d1;
        mask-size: 100%;
        mask-repeat: no-repeat;
    }

    nav > ul > li > a {
        color: inherit;
    }

    h3 {
        font-size: 20px;
    }
</style>