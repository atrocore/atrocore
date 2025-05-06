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
    import BookmarkButton from "./buttons/BookmarkButton.svelte";
    import FollowButton from "./buttons/FollowButton.svelte";
    import RecordCallbacks from "./interfaces/RecordCallbacks";
    import NavigationButtons from "./buttons/NavigationButtons.svelte";
    import ContentFilter from "../ContentFilter.svelte";

    export let mode: string = 'detail';
    export let recordButtons: RecordActionButtons;
    export let callbacks: RecordCallbacks;
    export let permissions: Permissions;
    export let scope: string;
    export let id: string | null;

    let recordActions: ActionParams[] = [];
    let dropdownActions: ActionParams[] = [];
    let additionalActions: ActionParams[] = [];
    let dynamicActions: ActionParams[] = [];
    let dynamicActionsDropdown: ActionParams[] = [];
    let uiHandlerActions: ActionParams[] = [];
    let headerButtons: ActionParams[] = [];
    let loadingActions: boolean = false;
    let bookmarkId: string | null = null;

    $: {
        recordActions = (mode === 'edit' ? recordButtons?.editButtons : recordButtons?.buttons) ?? [];
        additionalActions = [...(recordButtons?.additionalButtons ?? []), ...dynamicActions];
        dropdownActions = (mode === 'edit' ? recordButtons?.dropdownEditButtons : recordButtons?.dropdownButtons) ?? [];
        uiHandlerActions = (mode === 'edit' ? [...(recordButtons?.additionalEditButtons ?? []), ...getUiHandlerButtons()] : []);
        headerButtons = (recordButtons?.headerButtons?.buttons ?? []).filter(button => !button.hidden);
    }

    window.addEventListener('detail:overview-filters-changed', (e: CustomEvent) => {
        const data = e.detail;

        if (!data || !recordButtons) {
            return;
        }

        recordButtons.isOverviewFilterActive = data.isOverviewFilterActive;
    });

    window.addEventListener('record:actions-reload', (e: CustomEvent) => {
        reloadDynamicActions();
    });


    window.addEventListener('record:buttons-update', (event: CustomEvent) => {
        if (recordButtons) {
            recordButtons = Object.assign(recordButtons, event.detail || {});
        } else {
            recordButtons = event.detail || {} as RecordActionButtons;
        }
    });

    window.addEventListener('record:followers-updated', (event: CustomEvent) => {
        if (recordButtons) {
            recordButtons.followers = event.detail;
        }
    })

    async function loadDynamicActions(): Promise<Record<string, any>[]> {
        let userData = UserData.get();
        if (!userData || !id) {
            return [];
        }

        try {
            const response = await fetch(`/api/v1/Action/${scope}/${id}/dynamicActions?type=record`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization-Token': btoa(userData.user.userName + ':' + userData.token)
                },
            });

            if (!response.ok) {
                return [];
            }

            return await response.json();
        } catch (error) {
            console.error(error);
            return [];
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

        dynamicActions = [];
        dynamicActionsDropdown = [];
        loadingActions = true;

        loadDynamicActions().then((list: Record<string, any>[]) => {
            const preparedList: ActionParams[] = list.map((item: Record<string, any>) => ({
                ...item,
                id: item.data.action_id ?? null
            } as ActionParams));

            const bookmarkAction: Record<string, any> | undefined = list.filter(item => ['bookmark', 'unbookmark'].includes(item.action)).pop();
            if (bookmarkAction) {
                bookmarkId = bookmarkAction.data.bookmark_id ?? null;
            }

            dynamicActions = preparedList.filter(item => item.display === 'single');
            dynamicActionsDropdown = preparedList.filter(item => item.display === 'dropdown');
        }).catch(error => {
            console.error(error);
        }).finally(() => loadingActions = false);
    }

    function executeAction(event: CustomEvent): void {
        recordButtons?.executeAction(event.detail.action, event.detail.data, event.detail.event || event);
    }

    onMount(() => {
        reloadDynamicActions();
    })
</script>

<div class="button-row">
    <ActionGroup actions={recordActions} {dropdownActions}
                 dynamicActionsDropdown={mode !== 'edit' ? dynamicActionsDropdown : []}
                 {executeAction} {loadingActions} hasMoreButton={true} className="record-actions">
        {#if mode === 'detail'}
            {#each additionalActions as action}
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
                    {#each headerButtons as button}
                        {#if button.name === 'filtering'}
                            <ContentFilter scope="{scope}" onExecute={executeAction}/>
                        {:else if ['bookmark', 'unbookmark'].includes(button.action)}
                            {#if id}
                                <BookmarkButton entity={scope} {id} bookmarkId={bookmarkId} loading={loadingActions}/>
                            {/if}
                        {:else if ['follow', 'unfollow'].includes(button.action)}
                            {#if id && recordButtons.followers}
                                <FollowButton entity={scope} {id} followers={recordButtons.followers}
                                              onFollow={callbacks.onFollow} onUnfollow={callbacks.onUnfollow}/>
                            {/if}
                        {:else if button.action === 'navigation'}
                            <NavigationButtons hasNext={recordButtons.hasNext} hasPrevious={recordButtons.hasPrevious}
                                               onExecute={executeAction}/>
                        {:else}
                            <ActionButton params={button} on:execute={executeAction}/>
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
        gap: 10px;
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