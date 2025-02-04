<!-- BaseLayout.svelte -->
<script lang="ts">
    import type Button from '../../admin/layouts/interfaces/Button';
    import type Params from "./interfaces/Params";
    import {Language} from "../../../utils/Language";

    export let params: Params;
    export let fetch: any


    export let validate = () => {
        return true;
    }

    let disabled = false;

    export let buttonList: Button[] = [
        {name: 'save', label: Language.translate('Save', 'labels'), style: 'primary'},
        {name: 'cancel', label: Language.translate('Cancel', 'labels')}
    ];
    let buttonContainer;

    export function save(): void {
        disabled = true;
        const itemsToSave = fetch();
        if (validate(itemsToSave)) {
            params.onSaved(itemsToSave)
        }
        disabled = false
    }


    function cancel(): void {
    }


    function onClick(button): void {
        if (button.action) {
            button.action();
            return;
        }
        switch (button.name) {
            case 'save':
                save()
                break
            case 'cancel':
                cancel()
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
