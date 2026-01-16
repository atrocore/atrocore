<script lang="ts">
    import {onMount} from "svelte";
    import {Metadata} from "../../../utils/Metadata";
    import {Language} from "../../../utils/Language";

    import EntityCallbacks from "./interfaces/EntityCallbacks";
    import EntityActionButtons from "./interfaces/EntityActionsButtons";

    import FavoriteEntityButton from "./buttons/FavoriteEntityButton.svelte";
    import ActionGroup from "./buttons/ActionGroup.svelte";
    import ViewModeSwitch from "./buttons/ViewModeSwitch.svelte";
    import ActionParams from "./interfaces/ActionParams";
    import TourButton from "./buttons/TourButton.svelte";

    export let scope: string;
    export let viewMode: string;
    export let isFavoriteEntity: boolean = false;
    export let entityActions: EntityActionButtons;
    export let callbacks: EntityCallbacks;
    export let onViewChange: (e: CustomEvent) => void;

    let actions: ActionParams[];
    let dropdownActions: ActionParams[];

    let dynamicActions: ActionParams[] = [];
    let dynamicDropdownActions: ActionParams[] = [];
    let hasFavoriteButton: boolean = false;
    let primaryEntityId: string | null = null;
    let stagingEntityId: string | null = null;

    $: {
        actions = [...entityActions.buttons ?? [], ...dynamicActions];
        dropdownActions = [...entityActions.dropdownButtons ?? [], ...dynamicDropdownActions];
    }

    window.addEventListener('favorites:update', (e: CustomEvent) => {
        const list: string[] = e.detail || [];
        isFavoriteEntity = list.includes(scope);
    });

    function loadDynamicActions(): void {
        const single = new Map<string, ActionParams>();
        const dropdown = new Map<string, ActionParams>();

        (Metadata.get(['clientDefs', scope, 'dynamicEntityActions']) || []).forEach((dynamicAction: Record<string, any>) => {
            if (!callbacks.canRunAction(dynamicAction.acl.scope, dynamicAction.acl.action)) {
                return;
            }

            if (dynamicAction.display === 'dropdown' && !dropdown.has(dynamicAction.id)) {
                dropdown.set(dynamicAction.id, {
                    label: dynamicAction.name,
                    action: "dynamicEntityAction",
                    id: dynamicAction.id
                } as ActionParams);
            }

            if (dynamicAction.display === 'single' && !single.has(dynamicAction.id)) {
                single.set(dynamicAction.id, {
                    label: dynamicAction.name,
                    action: "dynamicEntityAction",
                    id: dynamicAction.id
                } as ActionParams)
            }
        });

        dynamicActions = Array.from(single.values());
        dynamicDropdownActions = Array.from(dropdown.values());
    }

    function getStagingEntity(code: string): string | null {
        const scopes: Record<string, any> = Metadata.get(['scopes']);
        for (const [key, defs] of Object.entries(scopes)) {
            if (defs.primaryEntityId === code && defs.role === 'staging') {
                return key;
            }
        }

        return null;
    }

    onMount(() => {
        primaryEntityId = Metadata.get(['scopes', scope, 'primaryEntityId']);
        stagingEntityId = primaryEntityId ? null : getStagingEntity(scope);

        hasFavoriteButton = Metadata.get(['scopes', scope, 'tab']);
        loadDynamicActions();
    });
</script>

<div class="buttons-container">
    <div class="action-group-container">
        <ActionGroup {actions} {dropdownActions} className="entity-actions" hasMoreButton={true} dropdownPosition="right"/>
    </div>
    <div class="right-group">
        <div class="entity-buttons">
            <TourButton {scope} mode="list" />
            {#if stagingEntityId}
                <a role="button" title={Language.translate('openStagingEntity')} href="#{stagingEntityId}"><i class="ph ph-signpost"></i></a>
            {/if}
            {#if primaryEntityId}
                <a role="button" title={Language.translate('openPrimaryEntity')} href="#{primaryEntityId}"><i class="ph ph-crown"></i></a>
            {/if}
            {#if hasFavoriteButton}
                <FavoriteEntityButton
                        active={isFavoriteEntity}
                        onFavoriteAdd={callbacks.onAddFavorite}
                        onFavoriteRemove={callbacks.onRemoveFavorite}
                        {scope}
                />
            {/if}
        </div>

        <ViewModeSwitch mode={viewMode} {scope} on:view-change={onViewChange}/>
    </div>
</div>

<style>
    .buttons-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
        margin: 15px 0;
    }

    .buttons-container :global(.entity-actions) {
        gap: 10px;
    }

    .buttons-container :global(.entity-actions button) {
        border-radius: 3px;
    }

    .buttons-container .right-group {
        display: flex;
        margin-left: auto;
        margin-right: 0;
    }

    .buttons-container .right-group .entity-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .buttons-container .right-group .entity-buttons:not(:last-child) {
        margin-right: 20px;
    }

    .action-group-container {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
</style>