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
    import {Language} from "../../../../utils/Language";
    import {createEventDispatcher} from "svelte";

    const dispatch = createEventDispatcher();

    export let params: ActionParams;
    export let className: string = '';

    function runAction(e: Event) {
        const el = e.target as HTMLElement;

        dispatch('execute', {
            data: el.dataset,
            action: el.dataset.action,
            event: e
        });
    }
</script>

<button class="btn btn-{params.style ?? 'default'} action {className}" data-name={params.name} data-action={params.action || params.name} data-id={params.id} type="button"
        on:click={runAction} title={params.tooltip}>
    {#if params.html}{@html params.html}{:else}{Language.translate(params.label)}{/if}
</button>