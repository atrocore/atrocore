<!--
  - AtroCore Software
  -
  - This source file is available under GNU General Public License version 3 (GPLv3).
  - Full copyright and license information is available in LICENSE.txt, located in the root directory.
  -
  - @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
  - @license    GPLv3 (https://www.gnu.org/licenses/)
  -->

<script lang="ts">
    import ActionButton from "./ActionButton.svelte";
    import Preloader from "../../../icons/loading/Preloader.svelte";
    import ActionDropdownItem from "./ActionDropdownItem.svelte";

    export let actions: ActionParams[] = [];
    export let dropdownActions: ActionParams[] = [];
    export let dynamicActionsDropdown: ActionParams[] = [];
    export let loadingActions: boolean = false;
    export let className: string = '';
    export let executeAction: Function = () => {
    };
</script>

<div class="btn-group {className}">
    {#each actions as action}
        <ActionButton params={action} on:execute={executeAction} />
    {/each}

    {#if dropdownActions.length > 0 || dynamicActionsDropdown.length > 0 || loadingActions}
        <button type="button" class="btn btn-default dropdown-toggle dropdown-item-list-button"
                data-toggle="dropdown">
            <span class="caret"></span>
        </button>
    {/if}

    {#if dropdownActions.length > 0 || dynamicActionsDropdown.length > 0}
        <ul class="dropdown-menu pull-left">
            {#each dropdownActions as item}
                <li>
                    <ActionDropdownItem params={item} on:execute={executeAction} />
                </li>
            {/each}

            {#if dropdownActions && (dynamicActionsDropdown || loadingActions)}
                <li class="divider"></li>
            {/if}

            {#if loadingActions}
                <li class="preloader"><a href="javascript:"><Preloader heightPx="12"/></a></li>
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