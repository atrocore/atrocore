<script lang="ts">
    import RecordActionButtons from "./interfaces/RecordActionsButtons";
    import Permissions from "./interfaces/Permissions";
    import {UserData} from "../../../utils/UserData";
    import { Metadata } from '$lib/core/metadata';

    import {onMount} from "svelte";
    import Preloader from "../../icons/loading/Preloader.svelte";
    import DropdownActionButton from "$lib/components/buttons/DropdownActionButton/DropdownActionButton.svelte";
    import ActionGroup from "./buttons/ActionGroup.svelte";
    import ActionParams from "./interfaces/ActionParams";
    import BookmarkButton from "./buttons/BookmarkButton.svelte";
    import FollowButton from "./buttons/FollowButton.svelte";
    import RecordCallbacks from "./interfaces/RecordCallbacks";
    import NavigationButtons from "./buttons/NavigationButtons.svelte";
    import ContentFilter from "../ContentFilter.svelte";
    import TourButton from "./buttons/TourButton.svelte";
    import {Language} from "../../../utils/Language";

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
    let dynamicEditActions: ActionParams[] = [];
    let dynamicActionsDropdown: ActionParams[] = [];
    let additionalEditActions: ActionParams[] = [];
    let headerButtons: ActionParams[] = [];
    let loadingActions: boolean = false;
    let bookmarkId: string | null = null;
    let navigationIconScope: string | null = null;

    $: {
        recordActions = (mode === 'edit' ? recordButtons?.editButtons : recordButtons?.buttons) ?? [];
        additionalActions = [...(recordButtons?.additionalButtons ?? []), ...dynamicActions];
        dropdownActions = (mode === 'edit' ? recordButtons?.dropdownEditButtons : recordButtons?.dropdownButtons) ?? [];
        additionalEditActions = (mode === 'edit' ? [...(recordButtons?.additionalEditButtons ?? []), ...dynamicEditActions] : []);
        headerButtons = (recordButtons?.headerButtons?.buttons ?? []).filter(button => !button.hidden);

        prepareNavigationIconScope();
    }

    function navigateToEntity() {
        if (navigationIconScope) {
            window.location.hash = navigationIconScope + "/view/" + recordButtons.model.id;
        }
    }

    function onFollowersUpdated(event: CustomEvent) {
        if (recordButtons) {
            recordButtons.followers = event.detail;
        }
    }

    function onOverviewFiltersChanged(e: CustomEvent) {
        const data = e.detail;

        if (!data || !recordButtons) {
            return;
        }

        recordButtons.isOverviewFilterActive = data.isOverviewFilterActive;
    }

    function onButtonsUpdate(event: CustomEvent) {
        if (recordButtons) {
            recordButtons = Object.assign(recordButtons, event.detail || {});
        } else {
            recordButtons = event.detail || {} as RecordActionButtons;
        }
    }

    async function loadDynamicActions(): Promise<Record<string, any>[]> {
        let userData = UserData.get();
        if (!userData) {
            return [];
        }

        try {
            const response = await fetch(`/api/v1/Action/action/dynamicActions?type=record&scope=${scope}` + (id ? '&id=' + id : ''), {
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

    function prepareNavigationIconScope() {
        if (scope === 'Entity' && recordButtons.model && recordButtons.model.id) {
            navigationIconScope = recordButtons.model.get('hasMasterDataEntity') ? 'MasterDataEntity' : null;
        } else if (scope === 'MasterDataEntity') {
            navigationIconScope = 'Entity';
        } else {
            navigationIconScope = null;
        }
    }

    function reloadDynamicActions(event: CustomEvent): void {

        prepareNavigationIconScope();

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

            dynamicActions = preparedList.filter(item => item.display === 'single' && !Metadata.get(['action', 'typesData', item.type || '', 'forEditModeOnly']));
            dynamicActionsDropdown = preparedList.filter(item => item.display === 'dropdown' && !Metadata.get(['action', 'typesData', item.type || '', 'forEditModeOnly']));
            dynamicEditActions = preparedList.filter(item => item.display === 'single' && Metadata.get(['action', 'typesData', item.type || '', 'forEditModeOnly']));
        }).catch(error => {
            console.error(error);
        }).finally(() => loadingActions = false);
    }

    function executeAction(event: CustomEvent): void {
        recordButtons?.executeAction(event.detail.action, event.detail.data, event.detail.event || event);
    }

    onMount(() => {
        window.addEventListener('detail:overview-filters-changed', onOverviewFiltersChanged);
        window.addEventListener('record:actions-reload', reloadDynamicActions);
        window.addEventListener('record:buttons-update', onButtonsUpdate);
        window.addEventListener('record:followers-updated', onFollowersUpdated)

        reloadDynamicActions();

        return () => {
            window.removeEventListener('detail:overview-filters-changed', onOverviewFiltersChanged);
            window.removeEventListener('record:actions-reload', reloadDynamicActions);
            window.removeEventListener('record:buttons-update', onButtonsUpdate);
            window.removeEventListener('record:followers-updated', onFollowersUpdated);
        }
    })
</script>

<div class="button-row">
    <ActionGroup actions={recordActions} {dropdownActions}
                 dynamicActionsDropdown={mode !== 'edit' ? dynamicActionsDropdown : []}
                 {executeAction} {loadingActions} hasMoreButton={true} className="record-actions">
        {#if mode === 'detail'}
            {#each additionalActions as action}
                <DropdownActionButton params={action} on:execute={executeAction} className="additional-button dynamic-action"/>
            {/each}

            {#if loadingActions}
                <button class="preloader additional-button">
                    <Preloader heightPx={12}/>
                </button>
            {/if}
            {#if navigationIconScope}
                <div class="icon-navigation">
                    <button title="{Language.translate(navigationIconScope, 'scopeName', 'Global')}"  on:click={navigateToEntity}><i class="ph-{Metadata.get(['clientDefs', navigationIconScope, 'iconClass'])} ph"></i></button>
                </div>
            {/if}
            {#if recordButtons?.headerButtons && headerButtons.find(item => item.name === 'filtering') }
                <ContentFilter scope="{scope}" onExecute={executeAction}
                               style="padding-bottom: 0;margin-left: 20px !important;"/>
            {/if}
        {:else if mode === 'edit'}
            {#each additionalEditActions as action}
                <DropdownActionButton params={action} on:execute={executeAction} className="additional-button"/>
            {/each}
        {/if}
    </ActionGroup>

    {#if mode === 'detail' && recordButtons?.headerButtons}
        <div class="header-buttons-container">
            <div class="header-buttons">
                <div class="header-items">
                    <TourButton {scope} {mode}/>
                    {#each headerButtons as button}
                        {#if button.name === 'filtering'}
                            <!--Skip-->
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
                            <DropdownActionButton params={button} on:execute={executeAction}/>
                        {/if}
                    {/each}
                </div>
            </div>
        </div>
    {/if}
</div>

<style>
    .icon-navigation {
        padding-bottom: 0;
        margin-left: 10px !important;
    }

    .button-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }

    .button-row :global(.record-actions) {
        gap: 10px;
    }

    .button-row :global(.record-actions > button) {
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
    }

    .button-row :global(.button-group > .additional-button:first-of-type) {
        margin-left: 10px;
    }

    .button-row .header-buttons .header-items {
        display: flex;
        flex-direction: row;
        white-space: nowrap;
        gap: 10px;
    }

    .preloader {
        background-color: transparent;
        pointer-events: none;
        border: 0;
    }
</style>