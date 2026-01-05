<script lang="ts">
    import {onDestroy, onMount} from "svelte";

    import Params from "./interfaces/Params"
    import AnchorNavItem from "./interfaces/AnchorNavItem";

    import BaseHeader from "./BaseHeader.svelte";
    import AnchorNavigation from "./navigation/AnchorNavigation.svelte";
    import RecordActionButtons from "./interfaces/RecordActionsButtons";
    import RecordActionsGroup from "./RecordActionsGroup.svelte";
    import RecordCallbacks from "./interfaces/RecordCallbacks";

    export let params: Params;
    export let anchorNavItems: AnchorNavItem[] = [];
    export let recordButtons: RecordActionButtons | null = null;
    export let callbacks: RecordCallbacks;
    export let anchorScrollCallback = (panelName: string, event: Event) => {
    }

    let mode: string;
    let currentIsHeading: boolean = params?.currentIsHeading ?? true;

    $: mode = params.mode ?? 'detail';

    window.addEventListener('detail:panels-loaded', (event: CustomEvent) => {
        anchorNavItems = event.detail;
    });

    window.addEventListener('record-mode:changed', (event: CustomEvent) => {
        params.mode = event.detail;
    });

    if (currentIsHeading === true) {
        window.addEventListener('breadcrumbs:header-updated', (event: CustomEvent) => {
            currentIsHeading = !!event.detail;
        });
    }

    window.addEventListener('breadcrumbs:items-updated', (event: CustomEvent) => {
        params.breadcrumbs = event.detail;
    });

    onMount(() => {
        if (params.afterOnMount) {
            params.afterOnMount();
        }
    });

    onDestroy(() => {
        if (params.afterOnDestroy) {
            params.afterOnDestroy();
        }
    });
</script>

<BaseHeader breadcrumbs={params.breadcrumbs} {currentIsHeading} scope={params.scope} id={params.id}>
    {#if recordButtons}
        <div class="detail-button-container">
            <RecordActionsGroup {mode} scope={params.scope} id={params.id} permissions={params.scopePermissions}
                                {recordButtons} {callbacks}/>
        </div>
    {/if}
    {#if anchorNavItems.length > 0}
        <div class="anchor-nav-container">
            <AnchorNavigation items={anchorNavItems} scrollCallback={anchorScrollCallback}
                              hasLayoutEditor={recordButtons?.hasLayoutEditor && params.mode !== 'edit'}/>
        </div>
    {/if}
</BaseHeader>

<style>
    .detail-button-container {
        position: relative;
        z-index: 101;
        margin: 15px 0;
    }
</style>