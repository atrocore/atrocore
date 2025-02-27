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

    let mode;

    $: {
        mode = params?.mode ?? 'detail';
    }

    window.addEventListener('detailPanelsLoaded', (event: any) => {
        console.log('Received detail panels:', event);
        anchorNavItems = event.list;
    });

    window.addEventListener('record-mode:changed', (event: CustomEvent) => {
        params.mode = event.detail;
        console.log('Mode is changed: ', params.mode);
        console.log(event);
    });

    onMount(() => {
        if (params.afterOnMount) {
            params.afterOnMount();
        }
    });
</script>

<BaseHeader breadcrumbs={params.breadcrumbs}>
    <div class="detail-button-container">
        <RecordActionsGroup {mode} scope={params.scope} id={params.id} permissions={params.scopePermissions} {recordButtons} />
    </div>
    <AnchorNavigation items={anchorNavItems}/>
</BaseHeader>
