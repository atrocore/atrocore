<script lang="ts">
    import {Language} from "../../../utils/Language";
    import BaseHeader from "./BaseHeader.svelte";
    import BreadcrumbsItem from "./interfaces/BreadcrumbsItem";

    export let tabs: string[] = [];
    export let selectedTabIndex: number = 0;
    export let readOnly: boolean = true;

    const breadcrumbs: BreadcrumbsItem[] = [{
        label: Language.translate('Dashboard', 'scopeNames'),
        url: '/'
    }];
</script>

<BaseHeader {breadcrumbs} scope="Dashboard">
    <div class="controls">
        {#if tabs.length > 1}
            <div class="button-group dashboard-tabs">
                {#each tabs as tab, index}
                    <button class="button" class:active={index === selectedTabIndex} data-action="selectTab" data-tab="{index}">{tab}</button>
                {/each}
            </div>

            <div class="dashboard-selectbox">
                <select class="form-control" data-action="selectTab">
                    {#each tabs as tab, index}
                        <button class="button" class:active={index === selectedTabIndex} data-action="selectTab" data-tab="{index}">{tab}</button>
                        <option value={index} selected={index === selectedTabIndex}>{tab}</option>
                    {/each}
                </select>
            </div>
        {/if}

        {#if !readOnly}
            <button data-action="reset">{Language.translate('Reset to Default')}</button>
            <div class="button-group dashboard-buttons">
                <button data-action="editTabs" title="{Language.translate('Edit Dashboard')}"><i class="ph ph-pencil-simple"></i></button>
                <button data-action="addDashlet" title="{Language.translate('Add Dashlet')}"><i class="ph ph-plus"></i></button>
            </div>
        {/if}
    </div>
</BaseHeader>

<style>
    .controls {
        margin: 0 0 0 auto;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .dashboard-buttons {
        flex-wrap: nowrap;
    }
</style>