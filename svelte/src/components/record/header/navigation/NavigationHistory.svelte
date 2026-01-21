<script lang="ts">
    import { Language } from "$lib/core/language";
    import {onMount} from "svelte";
    import Preloader from "../../../icons/loading/Preloader.svelte";
    import {UserData} from "../../../../utils/UserData";

    interface LastEntityRecord {
        name: string;
        label: string;
        link: string;
        className?: string;
        tooltip?: string;
    }

    interface EntityHistoryResponse {
        total: number;
        collection: ActionHistoryRecord[];
    }

    interface ActionHistoryRecord {
        controllerName: string;
        targetId: string | null;
        targetName: string | null;
        targetUrl: string | null;
    }

    export let scope: string;
    export let id: string | null = null;
    export let tabId: string | null = null;

    let items: LastEntityRecord[];

    let lastEntities: ActionHistoryRecord[] = [];
    let loading = false;

    $: items = lastEntities.map((item: ActionHistoryRecord) => {
        let label = '';
        let link = '';
        let className = '';
        let tooltip = '';

        if (item.controllerName === 'App' && item.targetId) {
            let category = 'labels';
            let scope = 'Admin'
            if (!Language.has(item.targetId, category, scope)) {
                category = 'scopeNames';
                scope = 'Global'

                if (!Language.has(item.targetId, category, scope)) {
                    category = 'labels';
                }
            }

            label = Language.translate(item.targetId, category, scope);
            className = 'entity';
            if (item.targetUrl) {
                link = item.targetUrl;
            }
        } else if (item.targetId === null) {
            label = Language.translate(item.controllerName, 'scopeNamesPlural');
            link = `#${item.controllerName}`;
            className = 'entity';
        } else {
            label = `${item.targetName || item.targetId}`;
            link = `#${item.controllerName}/view/${item.targetId}`
            tooltip = Language.translate(item.controllerName, 'scopeNames');
        }

        return {
            name: item.targetId || item.controllerName,
            label: label,
            link: link,
            className: className,
            tooltip: tooltip,
        } as LastEntityRecord;
    });

    async function loadLastEntities(): Promise<EntityHistoryResponse> {
        let userData = UserData.get();
        if (!userData) {
            return {collection: [], total: 0};
        }

        lastEntities = [];
        try {
            let url = '/api/v1/LastViewed/action/getNavigationHistory'
            let params: Record<string, any> = {
                'maxSize': '32',
                'entity': scope
            };

            if (id) {
                params.id = id;
            }

            if (tabId) {
                params.tabId = tabId;
            }

            const requestParams = new URLSearchParams(params).toString();
            if (requestParams) {
                url += `?${requestParams}`;
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
            return {collection: [], total: 0};
        }
    }

    onMount(() => {
        loading = true;
        loadLastEntities()
            .then((list) => {
                lastEntities = list.collection;
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
            {#each items as item}
                <li class={item.className}><a title={item.tooltip} href={item.link}>{item.label}</a></li>
            {/each}
        </ul>
    {/if}
</nav>


<style>
    .entity-history {
        margin-bottom: 15px;
        height: 20px;
        overflow: hidden;
    }

    nav > ul {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        margin: 0;
        padding: 0;
    }

    nav > ul > li {
        display: inline;
        color: #888;
        font-size: 12px;
        line-height: 20px;
    }

    nav > ul > li:not(:first-child):before {
        content: "";
        display: inline-block;
        width: 10px;
        height: 12px;
        vertical-align: middle;
        margin: 0 .5em;
        mask-image: url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgZmlsbD0iIzAwMDAwMCIgdmlld0JveD0iMCAwIDI1NiAyNTYiPjxwYXRoIGQ9Ik0xNjUuNjYsMjAyLjM0YTgsOCwwLDAsMS0xMS4zMiwxMS4zMmwtODAtODBhOCw4LDAsMCwxLDAtMTEuMzJsODAtODBhOCw4LDAsMCwxLDExLjMyLDExLjMyTDkxLjMxLDEyOFoiPjwvcGF0aD48L3N2Zz4=");
        background-color: #bbb;
        mask-size: 100%;
        mask-repeat: no-repeat;
    }

    nav > ul > li > a {
        color: inherit;
    }

    nav > ul > li.entity {
        font-style: italic;
    }
</style>