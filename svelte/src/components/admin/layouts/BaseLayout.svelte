<!-- BaseLayout.svelte -->
<script lang="ts">
    import { onMount, createEventDispatcher } from 'svelte';
    import type { Button, LayoutItem, Helper } from './interfaces';
    import {Metadata} from "../../../utils/Metadata";
    import {Notifier} from "../../../utils/Notifier";
    import {LayoutManager} from "../../../utils/LayoutManager";

    export let scope: string;
    export let type: string;
    export let helper: Helper;

    const dispatch = createEventDispatcher();

    let buttonList: Button[] = [
        { name: 'save', label: 'Save', style: 'primary' },
        { name: 'cancel', label: 'Cancel' },
        { name: 'resetToDefault', label: 'Reset to Default' }
    ];

    let dataAttributeList: string[] = [];
    let layout: LayoutItem[] = [];

    onMount(() => {
        setup();
        loadLayout();
    });

    function setup(): void {
        dataAttributeList = Metadata.get(['clientDefs', scope, 'additionalLayouts', type, 'dataAttributeList']) || [];
    }

    function loadLayout(): void {
        LayoutManager.get(scope, type, (fetchedLayout) => {
            layout = fetchedLayout;
        }, false);
    }

    export function save(layoutData?: LayoutItem[]): void {
        disableButtons();
        Notifier.notify('Saving...');

        const layoutToSave = layoutData || layout;

        if (!validate(layoutToSave)) {
            enableButtons();
            return;
        }

        LayoutManager.set(scope, type, layoutToSave, () => {
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
        buttonList = buttonList.map(button => ({ ...button, disabled: true }));
    }

    function enableButtons(): void {
        buttonList = buttonList.map(button => ({ ...button, disabled: false }));
    }

    function validate(layout: LayoutItem[]): boolean {
        return true;
    }

    export function openEditDialog(attributes: LayoutItem): void {
        dispatch('openEditDialog', attributes);
    }
</script>

<slot {helper} {buttonList}></slot>

<style>
    /* Add your styles here */
</style>