<!-- BaseLayout.svelte -->
<script lang="ts">
    import {onMount, createEventDispatcher} from 'svelte';
    import type {Button, Params} from './Interfaces';
    import {Notifier} from "../../../utils/Notifier";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {Language} from "../../../utils/Language";

    export let params: Params;
    export let fetch: any

    export let loadLayout = () => {
        LayoutManager.get(scope, type, layoutProfileId, (fetchedLayout) => {
            layout = fetchedLayout;
        }, false);
    }

    export let validate = () => {
        return true;
    }

    let disabled = false;

    let buttonList: Button[] = [];

    const profiles = Espo['link_LayoutProfile'] ?? []


    $:{
        buttonList = [
            {name: 'save', label: Language.translate('Save', 'labels'), style: 'primary'},
            {name: 'cancel', label: Language.translate('Cancel', 'labels')},
            {name: 'resetToDefault', label: Language.translate('resetToDefault', 'labels', 'LayoutManager')}
        ]

        for (const profile of profiles) {
            if (profile.id === params.layoutProfileId && profile.isDefault) {
                buttonList[2].label = Language.translate('resetToSystem', 'labels', 'LayoutManager')
            }
        }
    }

    onMount(() => {
        loadData()
    });

    function loadData() {
        Notifier.notify('Loading...')
        loadLayout(() => {
            Notifier.notify(false)
            if (params.afterRender) params.afterRender()
        });
    }

    export function save(): void {
        disabled = true;
        const layoutToSave = fetch();

        if (!validate(layoutToSave)) {
            disabled = false;
            debugger
            return;
        }
        Notifier.notify('Saving...');

        LayoutManager.set(params.scope, params.type, params.layoutProfileId, layoutToSave, () => {
            Notifier.notify('Saved', 'success', 2000);
            emitUpdate()
            disabled = false
        });
    }

    function emitUpdate() {
        if (params.onUpdate) {
            params.onUpdate({
                scope: params.scope,
                type: params.type,
                layoutProfileId: params.layoutProfileId
            })
        }
    }

    function cancel(): void {
        loadLayout();
    }

    function resetToDefault(): void {
        Notifier.confirm('Are you sure you want to reset to default?', () => {
            LayoutManager.resetToDefault(params.scope, params.type, params.layoutProfileId, () => {
                emitUpdate()
                cancel();
            });
        });
    }

    function onClick(button): void {
        switch (button.name) {
            case 'save':
                save()
                break
            case 'cancel':
                cancel()
                break
            case 'resetToDefault':
                resetToDefault()
                break
        }
    }
</script>

<div style="display: flex; justify-content: space-between; align-items: center;">
    <div class="button-container">
        {#each buttonList as button}
            <button on:click={()=>onClick(button)}
                    data-action="{button.name}"
                    disabled={disabled}
                    type="button"
                    class={`btn action btn-${button.style ?? 'default'}`}>
                {button.label}
            </button>
        {/each}
    </div>
    {#if params.allowSwitch}
        <div>
            <label class="control-label">{Language.translate('layoutProfile', 'fields', 'Layout')}</label>
            <select disabled="{disabled}" class="form-control" bind:value={params.layoutProfileId} on:change={loadData}
                    style="width: 150px; display:inline-block">
                <option value="custom">Custom</option>
                {#each profiles as profile}
                    <option value="{profile.id}">{profile.name}</option>
                {/each}
            </select>
        </div>
    {/if}
</div>

<slot></slot>
