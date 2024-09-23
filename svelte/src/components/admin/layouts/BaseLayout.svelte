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

    let buttonList: Button[] = [
        {name: 'save', label: Language.translate('Save', 'labels'), style: 'primary'},
        {name: 'cancel', label: Language.translate('Cancel', 'labels')},
        {name: 'resetToDefault', label: Language.translate('resetToDefault', 'labels', 'LayoutManager')}
    ];

    const profiles = Espo['link_LayoutProfile']
    if (profiles) {
        for (const profile of profiles) {
            if (profile.id === params.layoutProfileId && profile.isDefault) {
                buttonList.splice(2, 1);
            }
        }
    }


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
