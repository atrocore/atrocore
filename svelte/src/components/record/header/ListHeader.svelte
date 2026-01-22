<script lang="ts">
    import {onDestroy, onMount} from "svelte";

    import BaseHeader from "./BaseHeader.svelte";
    import Params from "./interfaces/Params"
    import EntityActionsGroup from "./EntityActionsGroup.svelte";
    import EntityActionButtons from "./interfaces/EntityActionsButtons";
    import EntityCallbacks from "./interfaces/EntityCallbacks";
    import { Language } from "$lib/core/language"

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

<BaseHeader scope={params.scope}>
    <h3 class="header-title">{Language.translate(params.scope, 'scopeNamesPlural')}</h3>
    <EntityActionsGroup {viewMode} scope={params.scope} {entityActions} {onViewChange} {callbacks} {isFavoriteEntity} />
</BaseHeader>

<style>
    h3 {
        font-size: 20px;
        margin-top: 40px;
    }
</style>
