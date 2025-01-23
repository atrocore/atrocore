<!-- BaseLayout.svelte -->
<script lang="ts">
    import {onMount, createEventDispatcher, tick} from 'svelte';
    import type {Button, Params} from './Interfaces';
    import {Notifier} from "../../../utils/Notifier";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {Language} from "../../../utils/Language";

    export let params: Params;
    export let fetch: any

    export let loadLayout = () => {
        LayoutManager.get(scope, type, layoutProfileId, (fetchedLayout) => {
            layout = fetchedLayout.layout;
        }, false);
    }

    export let validate = () => {
        return true;
    }

    let disabled = false;

    let buttonList: Button[] = [];
    let buttonContainer;

    const profiles = params.layoutProfiles ?? []

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

    onMount(async () => {
        loadData()

        await tick();
        const externalContainer = document.querySelector('#layout-buttons');
        if (buttonContainer && externalContainer) {
            externalContainer.appendChild(buttonContainer);
        }
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

        LayoutManager.set(params.scope, params.type, params.relatedScope, params.layoutProfileId, layoutToSave, () => {
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
                relatedScope: params.relatedScope,
                layoutProfileId: params.layoutProfileId
            })
        }
    }

    function cancel(): void {
        loadLayout();
    }

    function resetToDefault(): void {
        Notifier.confirm('Are you sure you want to reset to default?', () => {
            LayoutManager.resetToDefault(params.scope, params.type, params.relatedScope, params.layoutProfileId, () => {
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

<div class="button-container" style="padding-top: 10px" bind:this={buttonContainer}>
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

<slot></slot>
