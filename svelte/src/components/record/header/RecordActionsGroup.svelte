<script lang="ts">
    import Permissions from "./interfaces/Permissions";
    import {UserData} from "../../../utils/UserData";
    import {Metadata} from "../../../utils/Metadata";

    import {onMount} from "svelte";
    import Preloader from "../../icons/loading/Preloader.svelte";
    import ActionGroup from "./buttons/ActionGroup.svelte";
    import ActionButton from "./buttons/ActionButton.svelte";

    export let mode: string = 'detail';
    export let recordButtons: RecordActionButtons;
    export let permissions: Permissions;
    export let scope: string;
    export let id: string | null;

    let actions: ActionParams[] = [];
    let dropdownActions: ActionParams[] = [];
    let dynamicActions: ActionParams[] = [];
    let dynamicActionsDropdown: ActionParams[] = [];
    let uiHandlerActions: ActionParams[] = [];
    let loadingActions: boolean = false;

    $: {
        actions = (mode === 'edit' ? recordButtons?.editButtons : recordButtons?.buttons) ?? [];
        dropdownActions = (mode === 'edit' ? recordButtons?.dropdownEditButtons : recordButtons?.dropdownButtons) ?? [];
        uiHandlerActions = (mode === 'edit' ? [...(recordButtons?.additionalEditButtons ?? []), ...getUiHandlerButtons()] : [])
    }

    async function loadDynamicActions(): Promise<ActionParams[]> {
        let userData = UserData.get();
        if (!userData || !id) {
            return;
        }

        try {
            const response = await fetch(`/api/v1/Action/${scope}/${id}/dynamicActions`, {
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

    function getUiHandlerButtons(): ActionParams[] {
        const result = [];
        (Metadata.get(['clientDefs', scope, 'uiHandler']) || []).forEach(handler => {
            if (handler.type === 'setValue' && handler.triggerAction === 'onButtonClick') {
                result.push({
                    'action': 'uiHandler',
                    'id': handler.id,
                    'label': handler.name
                });

            }
        })

        return result;
    }

    function reloadDynamicActions(): void {
        if (Metadata.get(['scopes', scope, 'actionDisabled'])) {
            return;
        }

        loadingActions = true;
        loadDynamicActions().then((list) => {
            list = list.map((item) => ({...item, id: item.data.action_id ?? null}));
            dynamicActions = [...(recordButtons?.additionalButtons ?? []), ...list.filter(item => item.display === 'single')]
            dynamicActionsDropdown = list.filter(item => item.display === 'dropdown');
        }).catch(error => {
            dynamicActions = recordButtons?.additionalButtons ?? [];
            console.error(error);
        }).finally(() => loadingActions = false);
    }

    function executeAction(event: CustomEvent): void {
        recordButtons?.executeAction(event.detail.action, event.detail.data, event);
    }

    onMount(() => {
        reloadDynamicActions();
    })
</script>

<div class="button-row">
    <ActionGroup {actions} {dropdownActions} dynamicActionsDropdown={mode !== 'edit' ? dynamicActionsDropdown : []}
                 {executeAction} {loadingActions} className="record-actions">
        {#if mode === 'detail'}
            {#each dynamicActions as action}
                <ActionButton params={action} on:execute={executeAction} className="additional-button dynamic-action"/>
            {/each}

            {#if loadingActions}
                <button class="btn preloader additional-button">
                    <Preloader heightPx="12"/>
                </button>
            {/if}
        {:else if mode === 'edit'}
            {#each uiHandlerActions as action}
                <ActionButton params={action} on:execute={executeAction} className="additional-button"/>
            {/each}
        {/if}
    </ActionGroup>

    {#if mode === 'detail' && recordButtons?.headerButtons}
        <div class="header-buttons-container">
            <div class="header-buttons">
                <ActionGroup actions={recordButtons?.headerButtons?.buttons} {executeAction} className="header-items"/>
            </div>
        </div>
    {/if}
</div>

<style>
    .button-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }

    .button-row :global(.btn-group > .additional-button:first-of-type) {
        margin-left: 20px;
    }

    .button-row .header-buttons :global(.header-items) {
        gap: 10px
    }

    .preloader {
        background-color: transparent;
        pointer-events: none;
    }
</style>