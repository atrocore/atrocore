<script lang="ts">
    import {onMount} from "svelte";

    import Params from "./interfaces/Params"
    import AnchorNavItem from "./interfaces/AnchorNavItem";

    import BaseHeader from "./BaseHeader.svelte";
    import AnchorNavigation from "./navigation/AnchorNavigation.svelte";
    import RecordActionsGroup from "./RecordActionsGroup.svelte";

    export let params: Params;
    export let anchorNavItems: AnchorNavItem[] = [];
    export let recordButtons: RecordActionButtons | null = null;
    export let anchorScrollCallback = (panelName: string, event: Event) => {}

    let mode;
    let currentIsHeading = params?.currentIsHeading ?? true;

    $: {
        mode = params?.mode ?? 'detail';
    }

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

    onMount(() => {
        if (params.afterOnMount) {
            params.afterOnMount();
        }
    });
</script>

<BaseHeader breadcrumbs={params.breadcrumbs} {currentIsHeading}>
    <div class="detail-button-container">
        <RecordActionsGroup {mode} scope={params.scope} id={params.id} permissions={params.scopePermissions} {recordButtons} />
    </div>
    <div class="panel-navigation">
        <AnchorNavigation items={anchorNavItems} scrollCallback={anchorScrollCallback} />
    </div>
</BaseHeader>
