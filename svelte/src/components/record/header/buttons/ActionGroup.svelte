<script lang="ts">
    import DropdownActionButton from "$lib/components/buttons/DropdownActionButton/DropdownActionButton.svelte";
    import Preloader from "../../../icons/loading/Preloader.svelte";
    import ActionDropdownItem from "./ActionDropdownItem.svelte";
    import {Language} from "../../../../utils/Language";
    import ActionParams from "../interfaces/ActionParams";

    export let actions: ActionParams[] = [];
    export let dropdownActions: ActionParams[] = [];
    export let dynamicActionsDropdown: ActionParams[] = [];
    export let loadingActions: boolean = false;
    export let hasMoreButton: boolean = false;
    export let dropdownPosition: string = 'left';
    export let className: string = '';
    export let executeAction: (e: CustomEvent<any>) => void = () => {};

    let dropdownClass: string;
    $: {
        dropdownClass = 'dropdown-menu';

        if (dropdownPosition === 'right') {
            dropdownClass += ' dropdown-menu-right';
        }
    }
</script>

<div class="button-group {className}">
    {#each actions as action}
        <DropdownActionButton params={action} on:execute={executeAction} />
    {/each}

    {#if hasMoreButton && (dropdownActions.length > 0 || dynamicActionsDropdown.length > 0)}
        <button type="button" class="dropdown-toggle more-button" data-toggle="dropdown" aria-haspopup="true" >
            {Language.translate('More')} <i class="ph ph-caret-down"></i>
        </button>
    {:else if dropdownActions.length > 0 || dynamicActionsDropdown.length > 0}
        <button type="button" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" >
            <span class="caret"></span>
        </button>
    {/if}

    {#if dropdownActions.length > 0 || dynamicActionsDropdown.length > 0}
        <ul class={dropdownClass}>
            {#each dropdownActions as item}
                <li>
                    <ActionDropdownItem params={item} on:execute={executeAction} />
                </li>
            {/each}

            {#if dropdownActions && (dynamicActionsDropdown || loadingActions)}
                <li class="divider"></li>
            {/if}

            {#if loadingActions}
                <li class="preloader"><a href="javascript:"><Preloader heightPx={12}/></a></li>
            {/if}

            {#each dynamicActionsDropdown as item}
                <li class="dynamic-action">
                    <ActionDropdownItem params={item} on:execute={executeAction} />
                </li>
            {/each}
        </ul>
    {/if}

    <slot></slot>
</div>

<style>
    .more-button i {
        font-size: 14px;
    }
</style>