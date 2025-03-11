<script lang="ts">
    import RecordActionButtons from "./interfaces/RecordActionsButtons";
    import Permissions from "./interfaces/Permissions";
    import {UserData} from "../../../utils/UserData";
    import {Metadata} from "../../../utils/Metadata";

    import {onMount} from "svelte";
    import Preloader from "../../icons/loading/Preloader.svelte";
    import ActionButton from "./buttons/ActionButton.svelte";
    import ActionGroup from "./buttons/ActionGroup.svelte";
    import ActionParams from "./interfaces/ActionParams";
    import OverviewFilterButton from "./buttons/OverviewFilterButton.svelte";

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

    window.addEventListener('detail:overview-filters-changed', (e: CustomEvent) => {
        const data = e.detail;

        if (!data || !recordButtons) {
            return;
        }

        recordButtons.isOverviewFilterActive = data.isOverviewFilterActive;
    });

    async function loadDynamicActions(): Promise<Record<string, any>[] | undefined> {
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
        const result: ActionParams[] = [];
        (Metadata.get(['clientDefs', scope, 'uiHandler']) || []).forEach((handler: Record<string, any>) => {
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
        loadDynamicActions().then((list: Record<string, any>[]) => {
            const preparedList: ActionParams[] = list.map((item: Record<string, any>) => ({
                ...item,
                id: item.data.action_id ?? null
            } as ActionParams));

            dynamicActions = [...(recordButtons?.additionalButtons ?? []), ...preparedList.filter(item => item.display === 'single')]
            dynamicActionsDropdown = preparedList.filter(item => item.display === 'dropdown');
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
                 {executeAction} {loadingActions} hasMoreButton={true} className="record-actions">
        {#if mode === 'detail'}
            {#each dynamicActions as action}
                <ActionButton params={action} on:execute={executeAction} className="additional-button dynamic-action"/>
            {/each}

            {#if loadingActions}
                <button class="btn preloader additional-button">
                    <Preloader heightPx={12}/>
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
                <div class="header-items">
                    {#each recordButtons?.headerButtons?.buttons ?? [] as button}
                        {#if button.action === 'openOverviewFilter'}
                            <OverviewFilterButton filterApplied={recordButtons?.isOverviewFilterActive ?? false}
                                                  onExecute={executeAction}/>
                            {:else if }
                        {:else}
<!--                            <ActionButton params={button} on:execute={executeAction}/>-->
                        {/if}
                    {/each}
                </div>
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

    .button-row :global(.record-actions) {
        gap: 10px;
    }

    .button-row :global(.record-actions .btn) {
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
    }

    .button-row :global(.btn-group > .additional-button:first-of-type) {
        margin-left: 10px;
    }

    .button-row .header-buttons :global(.header-items) {
        gap: 10px
    }

    .preloader {
        background-color: transparent;
        pointer-events: none;
    }
</style>