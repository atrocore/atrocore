<script lang="ts">
    import {onDestroy, onMount} from "svelte";

    import BaseHeader from "./BaseHeader.svelte";
    import Params from "./interfaces/Params"
    import EntityActionsGroup from "./EntityActionsGroup.svelte";
    import EntityActionButtons from "./interfaces/EntityActionsButtons";
    import EntityHistory from "./navigation/EntityHistory.svelte";
    import EntityCallbacks from "./interfaces/EntityCallbacks";

    export let params: Params;
    export let entityActions: EntityActionButtons;
    export let callbacks: EntityCallbacks;
    export let viewMode: string = 'list';
    export let isFavoriteEntity: boolean = false;
    export let onViewModeChange: Function = (e: CustomEvent): void => {}

    function onViewChange(e: CustomEvent): void {
        viewMode = e.detail.name;
        onViewModeChange(e.detail.name)
    }

    onMount(() => {
        if (params.afterOnMount) {
            params.afterOnMount();
        }
    });

    onDestroy(() => {
        if (params.afterOnDestroy) {
            params.afterOnDestroy();
        }
    })
</script>

<EntityHistory scope={params.scope} />
<BaseHeader>
    <EntityActionsGroup {viewMode} scope={params.scope} {entityActions} {onViewChange} {callbacks} {isFavoriteEntity} />
</BaseHeader>
