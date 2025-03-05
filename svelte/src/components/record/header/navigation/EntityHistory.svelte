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

    let items: LastEntityRecord[];

    let lastEntities: string[] = [];
    let loading = false;

    $: items = lastEntities.reverse().map((item: string) => ({
        name: item,
        label: Language.translate(item, 'scopeNamesPlural'),
        link: `#${item}`,
    }) as LastEntityRecord);

    async function loadLastEntities(): Promise<string[]> {
        let userData = UserData.get();
        if (!userData) {
            return;
        }

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

{#if loading}
    <div class="entity-history">
        <Preloader heightPx="10"/>
    </div>
{:else if items.length > 0}
    <nav class="entity-history">
        <ul>
            {#each items as item}
                <li><a href={item.link}>{item.label}</a></li>
            {/each}
        </ul>
    </nav>
{/if}

<style>
    nav > ul {
        display: block;
        margin: 0;
        padding: 0;
    }

    nav > ul > li {
        display: inline;
        color: #000;
    }

    nav > ul > li:after {
        content: " > ";
        margin: 0 0.25em;
        color: #1a75d1;
    }

    nav > ul > li > a {
        color: inherit;
    }
</style>