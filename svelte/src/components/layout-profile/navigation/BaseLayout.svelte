<!-- BaseLayout.svelte -->
<script lang="ts">
    import {onMount, tick} from 'svelte';
    import type {Button, Params} from '../../admin/layouts/Interfaces';
    import {Notifier} from "../../../utils/Notifier";
    import {Language} from "../../../utils/Language";
    import {UserData} from "../../../utils/UserData";

    export let params: Params;
    export let fetch: any

    let layoutData;

    export let loadData = () => {

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
            {name: 'reset', label: Language.translate('reset', 'labels', 'LayoutManager')}
        ]
        
    }

    onMount(async () => {
        loadData()

    });


    export function save(): void {


    }


    function cancel(): void {

    }

    function reset(): void {
        Notifier.confirm('Are you sure you want to reset this layout?', () => {

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
