<script lang="ts">
    import ActionGroup from "./buttons/ActionGroup.svelte";
    import ViewModeSwitch from "./buttons/ViewModeSwitch.svelte";

    import {onMount} from "svelte";
    import {Metadata} from "../../../utils/Metadata";
    import EntityActionButtons from "./interfaces/EntityActionsButtons";

    export let scope: string;
    export let viewMode: string;
    export let entityActions: EntityActionButtons;
    export let onViewChange: Function = (e: CustomEvent): void => {}

    let actions: ActionParams[];
    let dropdownActions: ActionParams[];
    let dynamicActions: ActionParams[] = [];
    let dynamicDropdownActions: ActionParams[] = [];

    $: {
        actions = [...entityActions.buttons ?? [], ...dynamicActions];
        dropdownActions = [...entityActions.dropdownButtons ?? [], ...dynamicDropdownActions];
    }

    function loadDynamicActions(): void {
        const single = new Map<string, ActionParams>();
        const dropdown = new Map<string, ActionParams>();

        (Metadata.get(['clientDefs', scope, 'dynamicEntityActions']) || []).forEach(dynamicAction => {
            // TODO: this.getAcl().check(dynamicAction.acl.scope, dynamicAction.acl.action)
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

            dynamicActions = Array.from(single.values());
            dynamicDropdownActions = Array.from(dropdown.values());
        });
    }

    onMount(() => {
        loadDynamicActions();
    });
</script>

<div class="buttons-container">
    <ActionGroup {actions} {dropdownActions} />
    <ViewModeSwitch mode={viewMode} {scope} on:view-change={onViewChange}/>
</div>

<style>
    .buttons-container {
        display: flex;
        flex-wrap: wrap;
    }

    .buttons-container :global(.view-mode-switch) {
        margin-left: auto;
        margin-right: 0;
    }
</style>