<!-- BaseLayout.svelte -->
<script lang="ts">
    import {onMount, createEventDispatcher} from 'svelte';
    import type {Button, Params} from './Interfaces';
    import {Notifier} from "../../../utils/Notifier";
    import {LayoutManager} from "../../../utils/LayoutManager";

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

    export function openEditDialog(attributes: any): void {
        dispatch('openEditDialog', attributes);
    }

    const dispatch = createEventDispatcher();

    let buttonList: Button[] = [
        {name: 'save', label: 'Save', style: 'primary'},
        {name: 'cancel', label: 'Cancel'},
        {name: 'resetToDefault', label: 'Reset to Default'}
    ];

    onMount(() => {
        Notifier.notify('Loading...')
        loadLayout(() => {
            Notifier.notify(false)
            if (params.afterRender) params.afterRender()
        });
    });

    export function save(): void {
        disableButtons();
        const layoutToSave = fetch();

        if (!validate(layoutToSave)) {
            enableButtons();
            return;
        }
        Notifier.notify('Saving...');

        LayoutManager.set(params.scope, params.type, params.layoutProfileId, layoutToSave, () => {
            Notifier.notify('Saved', 'success', 2000);
            emitUpdate()
            enableButtons();
        });
    }

    function emitUpdate() {
        console.log('emit update')
        const customEvent = new CustomEvent('layoutUpdated', {
            detail: {
                scope: params.scope,
                type: params.type,
                layoutProfileId: params.layoutProfileId
            }
        });
        window.dispatchEvent(customEvent);
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

    function disableButtons(): void {
        buttonList = buttonList.map(button => ({...button, disabled: true}));
    }

    function enableButtons(): void {
        buttonList = buttonList.map(button => ({...button, disabled: false}));
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

<div class="button-container">
    {#each buttonList as button}
        <button on:click={()=>onClick(button)}
                data-action="{button.name}"
                disabled={button.disabled}
                type="button"
                class={`btn action btn-${button.style ?? 'default'}`}>
            {button.label}
        </button>
    {/each}
</div>
<slot></slot>
