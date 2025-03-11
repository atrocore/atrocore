<script lang="ts">
    import {onMount} from "svelte";
    import {Metadata} from "../../../utils/Metadata";

    import EntityCallbacks from "./interfaces/EntityCallbacks";
    import EntityActionButtons from "./interfaces/EntityActionsButtons";

    import FavoriteEntityButton from "./buttons/FavoriteEntityButton.svelte";
    import ActionGroup from "./buttons/ActionGroup.svelte";
    import ViewModeSwitch from "./buttons/ViewModeSwitch.svelte";
    import ActionParams from "./interfaces/ActionParams";

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

    onMount(() => {
        hasFavoriteButton = Metadata.get(['scopes', scope, 'tab']);
        loadDynamicActions();
    });
</script>

<div class="buttons-container">
    <ActionGroup {actions} {dropdownActions} className="entity-actions" hasMoreButton={true}/>
    <div class="right-group">
        {#if hasFavoriteButton}
            <div class="entity-buttons">
                <FavoriteEntityButton
                        active={isFavoriteEntity}
                        onFavoriteAdd={callbacks.onAddFavorite}
                        onFavoriteRemove={callbacks.onRemoveFavorite}
                        {scope}
                />
            </div>
        {/if}

        <ViewModeSwitch mode={viewMode} {scope} on:view-change={onViewChange}/>
    </div>
</div>

<style>
    .buttons-container {
        display: flex;
        flex-wrap: wrap;
    }

    .buttons-container :global(.entity-actions) {
        gap: 10px;
    }

    .buttons-container :global(.entity-actions .btn) {
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
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
</style>