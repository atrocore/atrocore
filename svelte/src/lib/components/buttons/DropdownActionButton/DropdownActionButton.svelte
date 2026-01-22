<script lang="ts">
    import {createEventDispatcher} from "svelte";
    import ActionButton from "$lib/components/buttons/ActionButton/ActionButton.svelte";
    import { Language } from "$lib/core/language"
    import DropdownActionParams from "./types/params";
    import { getToggleClasses } from "./utils/dropdown-utils";

    export let params: DropdownActionParams;
    export let className: string = '';

    const dispatch = createEventDispatcher();

    $: toggleClasses = getToggleClasses(params, className);

    const handleClick = (e: MouseEvent) => {
        const el = e.currentTarget as HTMLElement;
        dispatch('execute', {
            data: el.dataset,
            action: el.dataset.action,
            event: e
        });
    };
</script>

{#if !params.hidden}
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
                            on:click={handleClick}
                    >
                        {Language.translate(subItem.label ?? subItem.name ?? '')}
                    </a>
                </li>
            {/each}
        </ul>
    </div>
{/if}