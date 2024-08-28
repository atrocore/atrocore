<!-- BaseLayout.svelte -->
<script lang="ts">
    import {onMount, createEventDispatcher} from 'svelte';
    import type {Button, LayoutItem} from './interfaces';
    import {Metadata} from "../../../utils/Metadata";
    import {Notifier} from "../../../utils/Notifier";
    import {LayoutManager} from "../../../utils/LayoutManager";

    export let scope: string;
    export let type: string;
    export let layoutProfileId: string;
    export let dataAttributeList: string[] = [];
    export let layout: any = [];
    export let fetch: any


    export function validate(layout: any): boolean {
        return true;
    }


    export function openEditDialog(attributes: any): void {
        dispatch('openEditDialog', attributes);
    }

    export let loadLayout = () => {
        LayoutManager.get(scope, type, layoutProfileId, (fetchedLayout) => {
            layout = fetchedLayout;
        }, false);
    }

    const dispatch = createEventDispatcher();

    let buttonList: Button[] = [
        {name: 'save', label: 'Save', style: 'primary'},
        {name: 'cancel', label: 'Cancel'},
        {name: 'resetToDefault', label: 'Reset to Default'}
    ];

    onMount(() => {
        loadLayout();
    });

    export function save(): void {
        disableButtons();
        const layoutToSave = fetch();

        if (!validate(layoutToSave)) {
            enableButtons();
            return;
        }
        Notifier.notify('Saving...');

        LayoutManager.set(scope, type, layoutProfileId, layoutToSave, () => {
            Notifier.notify('Saved', 'success', 2000);
            enableButtons();
        });
    }

    function cancel(): void {
        loadLayout();
    }

    function resetToDefault(): void {
        Notifier.confirm('Are you sure you want to reset to default?', () => {
            LayoutManager.resetToDefault(scope, type, () => {
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
