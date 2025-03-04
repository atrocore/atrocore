<!-- BaseLayout.svelte -->
<script lang="ts">
    import {onMount, createEventDispatcher, tick} from 'svelte';
    import type Button from './interfaces/Button'
    import type Params from './interfaces/Params'
    import {Notifier} from "../../../utils/Notifier";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {Language} from "../../../utils/Language";
    import {UserData} from "../../../utils/UserData";

    const dispatch = createEventDispatcher();
    export let params: Params;
    export let fetch: any

    let layoutData;

    export let loadLayout = () => {
        LayoutManager.get(scope, type, layoutProfileId, (fetchedLayout) => {
            layoutData = fetchedLayout.layout;
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
            {name: 'cancel', label: Language.translate('Cancel', 'labels')}
        ]

        if (params.inModal) {
            let data = UserData.get();
            if (data && data.user && data.user.isAdmin) {
                buttonList.push({name: 'fullEdit', label: Language.translate('Full Edit', 'labels', "LayoutManager")})
            }
        }

        if (!params.inModal || layoutData?.canEdit) {
            buttonList.unshift({name: 'save', label: Language.translate('Save', 'labels'), style: 'primary'})
        }


        if (layoutData && layoutData.storedProfile && layoutData.storedProfile.id === params.layoutProfileId && layoutData.canEdit) {
            buttonList.push({name: 'reset', label: Language.translate('reset', 'labels', 'LayoutManager')})
        }
    }

    onMount(async () => {
        await loadData()
        await tick();
        dispatch('ready')

        const externalContainer = document.querySelector('#layout-buttons');

        if (buttonContainer && externalContainer && (params.inModal || params.replaceButtons)) {
            externalContainer.closest('.modal-body')?.classList.add('modal-layout-manager')
            externalContainer.appendChild(buttonContainer);
        }
    });

    async function loadData() {
        Notifier.notify('Loading...')
        return new Promise((resolve, reject) => {
            loadLayout((data) => {
                layoutData = data
                Notifier.notify(false)
                resolve()
                if (params.afterRender) params.afterRender()
            });
        })
    }

    export function save(): void {
        disabled = true;
        const layoutToSave = fetch();

        if (!validate(layoutToSave)) {
            disabled = false;
            return;
        }
        Notifier.notify('Saving...');

        if (params.inModal) {
            if (!params.getActiveLayoutProfileId()) {
                emitUpdate(true)
                return;
            }
        }

        LayoutManager.set(params.scope, params.type, params.relatedScope, params.layoutProfileId, layoutToSave, () => {
            Notifier.notify('Saved', 'success', 2000);
            emitUpdate(false)
            disabled = false
            if (!params.inModal) {
                loadLayout()
            }
        });
    }

    function emitUpdate(reset) {
        if (params.onUpdate) {
            params.onUpdate(reset)
        }
    }

    function cancel(): void {
        loadLayout();
    }

    function reset(): void {
        Notifier.confirm('Are you sure you want to reset this layout?', () => {
            LayoutManager.resetToDefault(params.scope, params.type, params.relatedScope, layoutData.storedProfile?.id, () => {
                emitUpdate(true)
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
            case 'reset':
                reset()
                break
            case 'fullEdit':
                window.open(`#Admin/layouts/scope=${params.scope}&type=${params.type}${params.relatedScope ? ('&relatedScope=' + params.relatedScope) : ''}${params.layoutProfileId ? ('&layoutProfileId=' + params.layoutProfileId) : ''}`, '_blank');
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

<style>
    :global(.modal-layout-manager) {
        padding-top: 0 !important;
        overflow: auto !important;
    }
</style>