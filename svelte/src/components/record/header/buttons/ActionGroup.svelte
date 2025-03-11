<script lang="ts">
    import ActionButton from "./ActionButton.svelte";
    import Preloader from "../../../icons/loading/Preloader.svelte";
    import ActionDropdownItem from "./ActionDropdownItem.svelte";
    import {Language} from "../../../../utils/Language";
    import ActionParams from "../interfaces/ActionParams";

    export let actions: ActionParams[] = [];
    export let dropdownActions: ActionParams[] = [];
    export let dynamicActionsDropdown: ActionParams[] = [];
    export let loadingActions: boolean = false;
    export let hasMoreButton: boolean = false;
    export let className: string = '';
    export let executeAction: Function = () => {
    };
</script>

<div class="btn-group {className}">
    {#each actions as action}
        <ActionButton params={action} on:execute={executeAction} />
    {/each}

    {#if hasMoreButton && (dropdownActions.length > 0 || dynamicActionsDropdown.length > 0)}
        <button type="button" class="btn btn-default dropdown-toggle more-button" data-toggle="dropdown" aria-haspopup="true" >
            {Language.translate('More')} <span class="fa fa-chevron-down more-arrow"></span>
        </button>
    {:else if dropdownActions.length > 0 || dynamicActionsDropdown.length > 0}
        <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" >
            <span class="caret"></span>
        </button>
    {/if}

    {#if dropdownActions.length > 0 || dynamicActionsDropdown.length > 0}
        <ul class="dropdown-menu">
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
    .btn-group {
        display: flex;
        align-items: stretch;
    }

    .btn-group > .btn:not(:first-child) {
        margin-left: -1px;
    }

    .more-button .more-arrow {
        margin-left: .25em;
        font-size: 12px;
    }
</style>