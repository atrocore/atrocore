<script lang="ts">
    import ActionButton from "../ActionButton/ActionButton.svelte";
    import { Language } from "../../../../utils/Language";
    import type { ActionParams } from "../ActionButton/types/button-style";
    import { getToggleClasses } from "./utils/dropdown-utils";

    export let params: ActionParams;
    export let className: string = '';

    $: toggleClasses = getToggleClasses(params, className);
</script>

{#if !params.hidden}
    {#if params.dropdownItems && params.dropdownItems.length > 0}
        <div class="btn-group">
            <ActionButton {params} {className} on:execute />

            <button class={toggleClasses} data-toggle="dropdown">
                <i class="ph ph-caret-down"></i>
            </button>

            <ul class="dropdown-menu pull-left filter-list">
                {#each params.dropdownItems as subItem}
                    <li data-name={subItem.name}>
                        <a
                                href="javascript:"
                                data-name={subItem.name}
                                data-id={subItem.id}
                                data-action={subItem.action}
                        >
                            {Language.translate(subItem.label ?? subItem.name ?? '')}
                        </a>
                    </li>
                {/each}
            </ul>
        </div>
    {:else}
        <ActionButton {params} {className} on:execute />
    {/if}
{/if}