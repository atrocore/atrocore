<script lang="ts">
    import ActionButtonParams from "$lib/components/buttons/ActionButton/types/action-button-params";
    import { Language } from "$lib/core/language";
    import ActionButton from "$lib/components/buttons/ActionButton/ActionButton.svelte";

    export let hasPrevious: boolean = false;
    export let hasNext: boolean = false;
    export let onExecute: (e: CustomEvent<any>) => void;

    let navigating: boolean = false;
    let paramsList: ActionButtonParams[];

    $: {
        paramsList = [
            {
                name: 'navigation',
                action: 'previous',
                html: '<i class="ph ph-caret-left"></i>',
                tooltip: Language.translate('Previous Entry'),
                disabled: !hasPrevious || navigating
            },
            {
                name: 'navigation',
                action: 'next',
                html: '<i class="ph ph-caret-right"></i>',
                tooltip: Language.translate('Next Entry'),
                disabled: !hasNext || navigating
            }
        ];
    }

    function execute(e: CustomEvent<any>) {
        navigating = true;
        onExecute(e);
    };
</script>

<div class="button-group">
    {#each paramsList as params}
        <ActionButton {params} on:execute={execute} />
    {/each}
</div>