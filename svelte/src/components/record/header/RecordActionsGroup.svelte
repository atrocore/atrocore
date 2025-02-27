<script lang="ts">
    import Permissions from "./interfaces/Permissions";
    import {UserData} from "../../../utils/UserData";
    import {Metadata} from "../../../utils/Metadata";

    import {onMount} from "svelte";
    import Preloader from "../../icons/loading/Preloader.svelte";
    import {Language} from "../../../utils/Language";

    export let mode: string = 'detail';
    export let recordButtons: RecordActionButtons;
    export let permissions: Permissions;
    export let scope: string;
    export let id: string | null;

    let actions: ActionButton[] = [];
    let dropdownActions: ActionButton[] = [];
    let dynamicActions: ActionButton[] = [];
    let dynamicActionsDropdown: ActionButton[] = [];
    let uiHandlerActions: ActionButton[] = [];
    let actionsLoading: boolean = false;

    $: {
        actions = (mode === 'edit' ? recordButtons?.editButtons : recordButtons?.buttons) ?? [];
        dropdownActions = (mode === 'edit' ? recordButtons?.dropdownEditButtons : recordButtons?.dropdownButtons) ?? [];
        uiHandlerActions = (mode === 'edit' ? getUiHandlerButtons() : [])
    }

    async function loadDynamicActions(): Promise<ActionButton[]> {
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

    function getUiHandlerButtons(): ActionButton[] {
        const result = [];
        (this.getMetadata().get(['clientDefs', this.scope, 'uiHandler']) || []).forEach(handler => {
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

        actionsLoading = true;
        loadDynamicActions().then((list) => {
            console.log(list);
            list = list.map((item) => ({...item, id: item.data.action_id ?? null}));
            dynamicActions = [...(recordButtons?.additionalButtons ?? []), ...list.filter(item => item.display === 'single')]
            dynamicActionsDropdown = list.filter(item => item.display === 'dropdown');
        }).catch(error => {
            console.error(error);
        }).finally(() => actionsLoading = false);
    }

    function runAction(event: Event): void {
        const el = event.target as HTMLElement;
        recordButtons?.executeAction(el.dataset.action as string, el.dataset as Map<string, any>, event);
    }

    onMount(() => {
        reloadDynamicActions();
    })
</script>

<div class="button-row">
    <div class="btn-group">
        {#each actions as item}
            <button class="btn btn-{item.style ?? 'default'} action" data-action="{item.name}" type="button" on:click={runAction}>
                {#if item.html}{@html item.html}{:else}{Language.translate(item.label)}{/if}
            </button>
        {/each}
        {#if dropdownActions.length > 0 || dynamicActionsDropdown.length > 0 || actionsLoading}
            <button type="button" class="btn btn-primary dropdown-toggle dropdown-item-list-button"
                    data-toggle="dropdown">
                <span class="caret"></span>
            </button>
        {/if}

        {#if dropdownActions.length > 0 || dynamicActionsDropdown.length > 0}
            <ul class="dropdown-menu pull-left">
                {#each dropdownActions as item}
                    <li>
                        <a href="javascript:" class="action" data-action={item.name} title={item.tooltip}
                           on:click={runAction}>
                            {#if item.html}{@html item.html}{:else}{Language.translate(item.label)}{/if}
                        </a>
                    </li>
                {/each}

                {#if dropdownActions && (dynamicActionsDropdown || actionsLoading)}
                    <li class="divider"></li>
                {/if}

                {#if actionsLoading}
                    <li class="preloader"><a href="javascript:">
                        <Preloader heightPx="12"/>
                    </a></li>
                {/if}

                {#each dynamicActionsDropdown as item}
                    <li class="dynamic-action">
                        <a href="javascript:" class="action" data-action={item.action} data-id={item.id}
                           on:click={runAction}
                           title={item.tooltip}>
                            {#if item.html}{@html item.html}{:else}{item.label}{/if}
                        </a>
                    </li>
                {/each}
            </ul>
        {/if}

        {#if mode === 'detail'}
            {#each dynamicActions as item}
                <button type="button" class="btn btn-default additional-button action" data-action={item.action}
                        data-id={item.id} title={item.tooltip} on:click={runAction}>
                    {#if item.html}{@html item.html}{:else}{item.label}{/if}
                </button>
            {/each}

            {#if actionsLoading}
                <button class="btn preloader" style="margin-left: 20px" href="javascript:">
                    <Preloader heightPx="12"/>
                </button>
            {/if}
        {:else if mode === 'edit'}
            {#each recordButtons?.additionalEditButtons ?? [] as item}
                <button type="button" class="btn btn-default additional-button action" data-action={item.action}
                        data-id={item.id}
                        title={item.tooltip} on:click={runAction}>
                    {#if item.html}{@html item.html}{:else}{item.label}{/if}
                </button>
            {/each}
        {/if}
    </div>
</div>

<style>
    .button-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
    }

    .preloader {
        background-color: transparent;
        pointer-events: none;
    }
</style>