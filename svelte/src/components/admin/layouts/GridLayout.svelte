<script lang="ts">
    import {createEventDispatcher, tick} from 'svelte';
    import Sortable from 'sortablejs';
    import {Params} from "./Interfaces";
    import {Notifier} from "../../../utils/Notifier";
    import BaseLayout from "./BaseLayout.svelte";
    import {Language} from "../../../utils/Language";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {ModelFactory} from "../../../utils/ModelFactory";
    import {Metadata} from "../../../utils/Metadata";

    export let params: Params;
    export let columnCount: number = 2;
    export let layoutDisabledParameter = 'layoutDetailDisabled';
    let dataAttributeList = ['id', 'name', 'fullWidth', 'customLabel', 'noLabel'];
    let panelDataAttributeList = ['id', 'panelName', 'style'];

    let dataAttributesDefs = {
        fullWidth: {type: 'bool'},
        name: {readOnly: true},
        customLabel: {type: 'varchar', readOnly: true},
        noLabel: {type: 'bool', readOnly: true}
    };

    let panelDataAttributesDefs = {
        panelName: {type: 'varchar'},
        style: {
            type: 'enum',
            options: ['default', 'success', 'danger', 'primary', 'info', 'warning'],
            translation: 'LayoutManager.options.style'
        },
        dynamicLogicVisible: {
            type: 'base',
            view: 'views/admin/field-manager/fields/dynamic-logic-conditions'
        }
    };

    let defaultPanelFieldList = ['modifiedAt', 'createdAt', 'modifiedBy', 'createdBy', 'assignedUser', 'ownerUser', 'teams'];

    let panels: Panel[] = [];
    let disabledFields: Cell[] = [];
    let lastPanelNumber = -1;
    let sidePanelsLayout: any;

    const dispatch = createEventDispatcher();

    function loadLayout(callback) {
        let layout;
        let model;

        const promiseList = [];

        promiseList.push(
            new Promise(resolve => {
                ModelFactory.create(params.scope, m => {
                    LayoutManager.get(params.scope, params.type, params.layoutProfileId, layoutLoaded => {
                        layout = layoutLoaded;
                        model = m;
                        resolve();
                    }, false);
                });
            })
        );

        if (['detail', 'detailSmall'].includes(params.type)) {
            promiseList.push(
                new Promise(resolve => {
                    LayoutManager.get(params.scope, 'sidePanels' + Espo.utils.upperCaseFirst(params.type), params.layoutProfileId, layoutLoaded => {
                        sidePanelsLayout = layoutLoaded;
                        resolve();
                    }, false);
                })
            );
        }

        Promise.all(promiseList).then(() => {
            readDataFromLayout(model, layout);
            setupPanels();
            tick().then(() => {
                initializeSortable();
            })
            if (callback) {
                callback();
            }
        });
    }

    function readDataFromLayout(model, layout) {
        let allFields = [];
        const labels = [];
        for (const field in model.defs.fields) {
            if (isFieldEnabled(model, field)) {
                labels.push(Language.translate(field, 'fields', params.scope));
                allFields.push(field);
            }
        }

        const duplicatedLabels = labels.filter((label, index) => labels.indexOf(label) !== index);
        let enabledFields = [];
        disabledFields = [];

        panels = layout;

        layout.forEach((panel, panelNum) => {
            panel.rows.forEach((row, rowNum) => {
                if (row) {
                    row.forEach((cell, i) => {
                        if (i == columnCount || !cell) {
                            return;
                        }
                        let label = Language.translate(cell.name, 'fields', params.scope);
                        if (duplicatedLabels.includes(label)) {
                            label += ` (${cell.name})`;
                        }
                        enabledFields.push({name: cell.name, label: label});
                        panels[panelNum].rows[rowNum][i].label = label;
                    });
                }
            });
        });

        allFields.sort((v1, v2) => Language.translate(v1, 'fields', params.scope).localeCompare(Language.translate(v2, 'fields', params.scope)));

        for (const i in allFields) {
            if (!hasField(allFields[i], enabledFields)) {
                const field = allFields[i];
                let label = Language.translate(field, 'fields', params.scope);
                if (duplicatedLabels.includes(label)) {
                    label += ` (${field})`;
                }
                disabledFields.push({name: field, label: label});
            }
        }
    }

    function hasField(name, list) {
        return list.some(field => field.name === name);
    }

    function isFieldEnabled(model, name) {
        if (hasDefaultPanel()) {
            if (defaultPanelFieldList.includes(name)) {
                return false;
            }
        }
        return !model.getFieldParam(name, 'disabled') && !model.getFieldParam(name, layoutDisabledParameter);
    }

    function hasDefaultPanel() {
        if (Metadata.get(['clientDefs', params.scope, 'defaultSidePanelDisabled'])) return false;

        if (sidePanelsLayout) {
            for (const name in sidePanelsLayout) {
                if (name === 'default' && sidePanelsLayout[name].disabled) {
                    return false;
                }
            }
        }
        return true;
    }


    function setupPanels() {
        lastPanelNumber = -1;
        panels = panels.map((panel, i) => {
            panel.number = i;
            lastPanelNumber = i;
            return panel;
        });
    }

    function initEmptyCell(el){
        Sortable.create(el, {
            group: 'cells',
            animation: 150,
            onAdd: function (evt) {
                console.log(`Item ${evt.item.textContent.trim()} dropped into droppable zone`);
            }
        });
    }
    function initializeSortable() {

        Sortable.create(document.querySelector('ul.panels'), {
            animation: 150,
            group: "panels",
            draggable: 'li.panel-layout'
        });
        document.querySelectorAll('ul.rows').forEach(el => {
            console.log(el)
            Sortable.create(el, {
                animation: 150,
                group: 'rows',
                draggable: 'li.row'
            });
        });
        document.querySelectorAll('ul.cells.disabled').forEach(el => {
            Sortable.create(el, {animation: 150, group: 'cells', draggable: 'li.cell'});
        });

        document.querySelectorAll('ul.cells .cell.empty').forEach(el => {
           initEmptyCell(el)
        })
    }



    function addPanel() {
        lastPanelNumber++;
        const newPanel: Panel = {
            customLabel: 'New panel',
            rows: [[false, false]],
            number: lastPanelNumber,
            name: `panel${lastPanelNumber}`,
            isCustomLabel: true
        };
        panels = [...panels, newPanel];
        tick().then(() => {
            const el = document.querySelector(`.panel-layout[data-number="${newPanel.number}"] > .rows`);
            Sortable.create(el, {
                animation: 150,
                group: 'rows',
                draggable: 'li.row'
            });
        })
    }

    function removePanel(number: number) {
        panels = panels.filter(panel => panel.number !== number);
    }

    function addRow(panelIndex: number) {
        panels = panels.map((panel, index) => {
            if (index === panelIndex) {
                panel.rows = [...panel.rows, Array(columnCount).fill(null)];
            }
            return panel;
        });
    }

    function removeRow(panelIndex: number, rowIndex: number) {
        panels = panels.map((panel, index) => {
            if (index === panelIndex) {
                panel.rows = panel.rows.filter((_, i) => i !== rowIndex);
            }
            return panel;
        });
    }

    function removeField(panelNumber: number, rowIndex: number, cellIndex: number) {
        panels = panels.map(panel => {
            if (panel.number === panelNumber) {
                panel.rows = panel.rows.map((row, rIndex) => {
                    if (rIndex === rowIndex) {
                        const removedCell = row[cellIndex];
                        if (removedCell) {
                            disabledFields = [...disabledFields, removedCell];
                        }
                        row[cellIndex] = null;
                    }
                    return row;
                });
            }
            return panel;
        });
    }

    function editPanelLabel(panelIndex: number) {
        // Implement edit panel label logic here
    }

    function minusCell(panelIndex: number, rowIndex: number, cellIndex: number) {
        if (columnCount < 2) return;

        panels = panels.map((panel, pIndex) => {
            if (pIndex === panelIndex) {
                panel.rows = panel.rows.map((row, rIndex) => {
                    if (rIndex === rowIndex) {
                        const newRow = row.filter((_, i) => i !== cellIndex);
                        if (newRow.length === 1) {
                            newRow[0].fullWidth = true;
                        }
                        return newRow;
                    }
                    return row;
                });
            }
            return panel;
        });
        columnCount--;
    }

    function validate(layout: Panel[]): boolean {
        let fieldCount = 0;
        layout.forEach(panel => {
            panel.rows.forEach(row => {
                row.forEach(cell => {
                    if (cell !== null) {
                        fieldCount++;
                    }
                });
            });
        });
        if (fieldCount === 0) {
            Notifier.notify('Layout cannot be empty.', 'error');
            return false;
        }
        return true;
    }

</script>

<BaseLayout {params} {validate} {fetch} {loadLayout}>
    <div id="layout" class="row">
        <div class="col-md-8">
            <div class="well">
                <header>{Language.translate('Layout', 'LayoutManager')}</header>
                <a href="#" on:click|preventDefault={addPanel}>{Language.translate('Add Panel', 'Admin')}</a>
                <ul class="panels">
                    {#each panels as panel (panel.number)}
                        <li data-number={panel.number} class="panel-layout">
                            <header data-name={panel.name}>
                                <label data-is-custom={panel.isCustomLabel ? 'true' : undefined}>{panel.customLabel || panel.label}</label>&nbsp;
                                <a href="#" data-action="edit-panel-label" class="edit-panel-label"
                                   on:click|preventDefault={() => editPanelLabel(panel.number)}>
                                    <i class="fas fa-pencil-alt fa-sm"></i>
                                </a>
                                <a href="#" style="float: right;" data-action="removePanel" class="remove-panel"
                                   data-number={panel.number} on:click|preventDefault={() => removePanel(panel.number)}>
                                    <i class="fas fa-times"></i>
                                </a>
                            </header>
                            <ul class="rows" on:mousedown|stopPropagation={()=>{}}>
                                {#each panel.rows as row, rowIndex}
                                    <li class="row">
                                        <div>
                                            <a href="#" data-action="removeRow" class="remove-row pull-right"
                                               on:click|preventDefault={() => removeRow(panel.number, rowIndex)}>
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </div>
                                        <ul class="cells" on:mousedown|stopPropagation={()=>{}}>
                                            {#each row as cell, cellIndex}
                                                {#if cell}
                                                    <li class="cell" draggable="true"
                                                        data-id={cell.id}
                                                        data-name={cell.name}
                                                        data-full-width={cell.fullWidth ? 'true' : undefined}
                                                        data-custom-label={cell.customLabel ? cell.customLabel : undefined}
                                                        data-no-label={cell.noLabel}>
                                                        {cell.label}
                                                        <a href="#" data-action="removeField" class="remove-field"
                                                           on:click|preventDefault={() => removeField(panel.number, rowIndex, cellIndex)}>
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    </li>
                                                {:else}
                                                    <li class="empty cell">
                                                        <a href="#" data-action="minusCell" class="remove-field"
                                                           on:click|preventDefault={() => minusCell(panel.number, rowIndex, cellIndex)}>
                                                            <i class="fas fa-minus"></i>
                                                        </a>
                                                    </li>
                                                {/if}
                                            {/each}
                                        </ul>
                                    </li>
                                {/each}
                            </ul>
                            <div>
                                <a href="#" data-action="addRow" on:click|preventDefault={() => addRow(panel.number)}>
                                    <i class="fas fa-plus"></i>
                                </a>
                            </div>
                        </li>
                    {/each}
                </ul>
            </div>
        </div>
        <div class="col-md-4">
            <div class="well">
                <header>{Language.translate('Available Fields', 'Admin')}</header>
                <ul class="disabled cells clearfix">
                    {#each disabledFields as field}
                        <li class="cell" data-name={field.name}>
                            {field.label}
                            <a href="#" data-action="removeField" class="remove-field"
                               on:click|preventDefault={() => removeField(-1, -1, disabledFields.indexOf(field))}>
                                <i class="fas fa-times"></i>
                            </a>
                        </li>
                    {/each}
                </ul>
            </div>
        </div>
    </div>
</BaseLayout>

<style>
    #layout ul {
        list-style-type: none;
        padding: 0;
        margin: 0;
    }

    #layout ul > li {
        background-color: #FFF;
    }

    #layout ul.panels > li {
        padding: 5px;
        margin: 5px;
        min-height: 80px;
        border: 1px solid #CCC;
        list-style: none;
    }

    #layout ul.rows {
        min-height: 80px;
    }

    #layout ul.rows > li {
        list-style: none;
        border: 1px solid #CCC;
        margin: 5px;
        padding: 5px;
        height: 72px;
    }

    #layout ul.cells {
        min-height: 30px;
        margin-top: 12px;
    }

    #layout ul.panels ul.cells > li {
        width: 46%;
        float: left;
    }

    #layout ul.panels ul.cells > li[data-full-width="true"] {
        width: 94%;
    }

    #layout ul.cells > li {
        list-style: none;
        border: 1px solid #CCC;
        margin: 5px;
        padding: 5px;
        height: 32px;
    }

    #layout ul.rows > li > div {
        width: auto;
    }

    #layout ul.cells > li a {
        float: right;
        margin-left: 5px;
    }

    #layout ul.disabled {
        min-height: 200px;
        width: 100%;
    }

    #layout ul.disabled > li a {
        display: none;
    }

    #layout header {
        font-weight: bold;
    }

    #layout ul.panels > li label {
        display: inline;
    }

    #layout ul.panels > li header a {
        font-weight: normal;
    }

    #layout ul.panels > li > div {
        width: auto;
        text-align: left;
        margin-left: 5px;
    }

    ul.cells li.cell {
        position: relative;
        cursor: pointer;
    }

    ul.cells li.cell a.remove-field {
        display: none;
    }

    ul.cells li.cell:hover a.remove-field {
        display: block;
    }

    ul.panels > li a.remove-panel {
        display: none;
    }

    ul.panels > li:hover a.remove-panel {
        display: block;
    }

    ul.rows > li a.remove-row {
        display: none;
    }

    ul.rows > li:hover a.remove-row {
        display: block;
    }

    ul.panels > li a.edit-panel-label {
        display: none;
    }

    ul.panels > li:hover a.edit-panel-label {
        display: inline-block;
    }

    .col-md-8 {
        width: 66.66667%;
    }

    .col-md-4 {
        width: 33.33333%;
    }

    .well {
        min-height: 20px;
        padding: 19px;
        margin-bottom: 20px;
        background-color: #f5f5f5;
        border: 1px solid #e3e3e3;
        border-radius: 4px;
        box-shadow: inset 0 1px 1px rgba(0, 0, 0, .05);
    }
</style>