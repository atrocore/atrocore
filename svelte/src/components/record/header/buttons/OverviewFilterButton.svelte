<script lang="ts">
    import ActionParams from "../interfaces/ActionParams";
    import ActionButton from "./ActionButton.svelte";
    import {Language} from "../../../../utils/Language";

    export let filterApplied: boolean = false;
    export let onExecute: (e: CustomEvent) => void;

    let style: string;
    let params: ActionParams;

    $: {
        style = filterApplied ? 'danger' : 'default';
        params = {
            action: 'openOverviewFilter',
            html: '<i class="ph ph-funnel"></i>',
            style: style,
            tooltip: Language.translate('actionOpenFilter')
        } as ActionParams;
    }

</script>

<ActionButton {params} on:execute={onExecute}/>