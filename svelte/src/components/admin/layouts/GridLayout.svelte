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

    let dataAttributeList = ['id', 'name', 'fullWidth', 'customLabel', 'noLabel'];
    let panelDataAttributeList = ['id', 'label', 'style'];

    let dataAttributesDefs = {
        fullWidth: {type: 'bool'},
        name: {readOnly: true},
        customLabel: {type: 'varchar', readOnly: true},
        noLabel: {type: 'bool', readOnly: true}
    };

    let panelDataAttributesDefs = {
        label: {type: 'varchar'},
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

    let panels: [] = [];
    let disabledFields: Cell[] = [];
    let lastPanelNumber = -1;
    let lastRowNumber = -1;
    let sidePanelsLayout: any;

    const dispatch = createEventDispatcher();

    function loadLayout(callback) {
        let layoutData;
        let model;

        const promiseList = [];

        promiseList.push(
            new Promise(resolve => {
                ModelFactory.create(params.scope, m => {
                    LayoutManager.get(params.scope, params.type,params.relatedScope, params.layoutProfileId, layoutLoaded => {
                        layoutData = layoutLoaded;
                        model = m;
                        resolve();
                    }, false);
                });
            })
        );

        if (params.type === "detail") {
            promiseList.push(
                new Promise(resolve => {
                    LayoutManager.get(params.scope, 'sidePanelsDetail',null, params.layoutProfileId, layoutLoaded => {
                        sidePanelsLayout = layoutLoaded.layout;
                        resolve();
                    }, false);
                })
            );
        }

        Promise.all(promiseList).then(() => {
            if (callback) {
                readDataFromLayout(model, layoutData.layout);
                setupPanels();
                tick().then(() => {
                    initializeSortable();
                })
                callback(layoutData);
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
        const disabledParameters = ['disabled', `layout${Espo.utils.upperCaseFirst(params.type)}Disabled`];
        if (params.reelType) {
            disabledParameters.push(`layout${Espo.utils.upperCaseFirst(params.reelType)}Disabled`)
        }
        for (let param of disabledParameters) {
            if (model.getFieldParam(name, param)) {
                return false
            }
        }
        return true
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

    function fetch(): [] {
        return panels.map(panel => {
            return {
                ...panel,
                rows: panel.rows.map(row => row.cells)
            }
        })
    }

    function setupPanels() {
        lastPanelNumber = -1;
        lastRowNumber = -1
        panels = panels.map((panel, i) => {
            panel.number = i;
            lastPanelNumber = i;
            panel.rows = panel.rows.map(row => {
                return {
                    number: lastRowNumber++,
                    cells: row
                }
            })
            return panel;
        });
    }

    function initPanel(el) {
        Sortable.create(el, {
            animation: 150,
            group: 'rows',
            draggable: 'li.row',
            onEnd: function (evt) {
                const panelToNumber = parseInt(evt.to.closest('.panel-layout').getAttribute('data-number'));
                const panelFromNumber = parseInt(evt.from.closest('.panel-layout').getAttribute('data-number'));
                if (panelFromNumber === panelToNumber) {
                    panels = panels.map(panel => {
                        if (panel.number === panelToNumber) {
                            const [movedItem] = panel.rows.splice(evt.oldIndex, 1);
                            // Reinsert the moved item at the new position
                            panel.rows.splice(evt.newIndex, 0, movedItem);
                        }
                        return panel;
                    });
                } else {
                    const panelFrom = panels.find(p => p.number === panelFromNumber)
                    const movedItem = panelFrom.rows[evt.oldIndex]
                    panels = panels.map(panel => {
                        if (panel.number === panelFromNumber) {
                            panel.rows.splice(evt.oldIndex, 1)
                        }
                        if (panel.number === panelToNumber) {
                            panel.rows.splice(evt.newIndex, 0, movedItem)
                        }
                        return panel
                    })
                }
            }
        });
    }

    function initializeSortable() {
        const panelsList = document.querySelector('ul.panels');
        if (panelsList) {
            Sortable.create(panelsList, {
                animation: 150,
                group: "panels",
                draggable: 'li.panel-layout',
                onEnd: function (evt) {
                    const panelElements = Array.from(evt.to.children);
                    panels = panelElements.map(panelElement => {
                        const panelNumber = parseInt(panelElement.getAttribute('data-number'));
                        return panels.find(panel => panel.number === panelNumber);
                    });
                }
            });
        }
        document.querySelectorAll('ul.rows').forEach(el => {
            initPanel(el)
        });
        document.querySelectorAll('ul.cells.disabled').forEach(el => {
            Sortable.create(el, {animation: 150, group: 'cells', draggable: 'li.cell'});
        });
    }


    function addPanel() {
        lastPanelNumber++;
        const newPanel: Panel = {
            label: 'New panel',
            rows: [{
                number: lastRowNumber++,
                cells: [false, false]
            }],
            number: lastPanelNumber,
            name: `panel${lastPanelNumber}`,
            isCustomLabel: true
        };
        panels = [...panels, newPanel];
        tick().then(() => {
            const el = document.querySelector(`.panel-layout[data-number="${newPanel.number}"] > .rows`);
            initPanel(el)
        })
    }

    function removePanel(number: number) {
        const panel = panels.find(p => p.number === number)
        panels = panels.filter(p => p !== panel);
        let fields = []
        panel.rows.forEach(row => {
            row.cells.forEach(cell => {
                if (cell) {
                    fields.push(cell)
                }
            })
        })
        disabledFields = [...disabledFields, ...fields]
    }

    function addRow(panelNumber: number) {
        panels = panels.map((panel) => {
            if (panel.number === panelNumber) {
                panel.rows = [...panel.rows, {number: lastRowNumber++, cells: Array(columnCount).fill(false)}];
            }
            return panel;
        });
    }

    function removeRow(panelNumber: number, rowNumber: number) {
        panels = panels.map((panel) => {
            if (panel.number === panelNumber) {
                const row = panel.rows.find(r => r.number === rowNumber)
                panel.rows = panel.rows.filter(r => r != row);
                disabledFields = [...disabledFields, ...row.cells.filter(r => !!r)]
            }
            return panel;
        });
    }

    function removeField(panelNumber: number, rowNumber: number, cellIndex: number) {
        panels = panels.map(panel => {
            if (panel.number === panelNumber) {
                panel.rows = panel.rows.map((row, rIndex) => {
                    if (row.number === rowNumber) {
                        const removedCell = row.cells[cellIndex];
                        if (removedCell) {
                            disabledFields = [...disabledFields, removedCell];
                        }
                        row.cells[cellIndex] = false;
                    }
                    return row;
                });
            }
            return panel;
        });
    }

    function handleDrop(event, panelNumber, rowNumber, cellIndex) {
        // get properties of dragged object
        const name = event.dataTransfer.getData('name')
        let field = disabledFields.find(f => f.name === name)
        let oldRowNumber = null
        let oldPanelNumber = null
        if (!field) {
            // search in layout
            panels.forEach(panel => {
                panel.rows.forEach((row, rowIndex) => {
                    row.cells.forEach(cell => {
                        if (cell && cell.name === name) {
                            field = cell
                            oldRowNumber = row.number
                            oldPanelNumber = panel.number
                        }
                    })
                })
            })
            if (!field) return
            // remove from panel
            panels = panels.map((panel, pIndex) => {
                if (oldPanelNumber === panel.number) {
                    panel.rows = panel.rows.map((row, rIndex) => {
                        if (row.number === oldRowNumber) {
                            row.cells = row.cells.map((cell, cIndex) => {
                                if (cell && cell.name === name) {
                                    return false
                                }
                                return cell
                            })
                        }
                        return row;
                    });
                }
                return panel;
            });
        } else {
            disabledFields = disabledFields.filter(f => f !== field)
        }


        panels = panels.map(panel => {
            if (panelNumber === panel.number) {
                panel.rows = panel.rows.map(row => {
                    if (rowNumber === row.number) {
                        row.cells = row.cells.map((cell, cIndex) => {
                            if (cIndex === cellIndex) {
                                if (field.fullWidth && row.cells.length > 1) {
                                    delete field.fullWidth
                                }
                                return field
                            }
                            return cell
                        })
                    }
                    return row;
                });
            }
            return panel;
        });
    }

    function editPanelLabel(panel: any) {
        params.onEditPanel(panel, panelDataAttributeList, panelDataAttributesDefs, (attributes) => {
            panels = panels.map(p => {
                if (p.number === panel.number) {
                    panelDataAttributeList.forEach(item => {
                        panel[item] = attributes[item]
                    })
                }
                return p;
            })
        })
    }

    function minusCell(panelNumber: number, rowNumber: number, cellIndex: number) {
        panels = panels.map(panel => {
            if (panelNumber === panel.number) {
                panel.rows = panel.rows.map(row => {
                    if (rowNumber === row.number) {
                        const cells = row.cells.filter((_, i) => i !== cellIndex);
                        if (cells.length === 1 && cells[0]) {
                            cells[0].fullWidth = true;
                        }
                        row.cells = cells
                    }
                    return row;
                });
            }
            return panel;
        });
    }

    function validate(layout: Panel[]): boolean {
        let fieldCount = 0;
        layout.forEach(panel => {
            panel.rows.forEach(row => {
                row.forEach(cell => {
                    if (cell) {
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
                                <label data-is-custom={panel.customLabel ? 'true' : undefined}>{panel.customLabel || panel.label || ''}</label>&nbsp;
                                <a href="#" data-action="edit-panel-label" class="edit-panel-label"
                                   on:click|preventDefault={() => editPanelLabel(panel)}>
                                    <i class="fas fa-pencil-alt fa-sm"></i>
                                </a>
                                <a href="#" style="float: right;" data-action="removePanel" class="remove-panel"
                                   data-number={panel.number} on:click|preventDefault={() => removePanel(panel.number)}>
                                    <i class="fas fa-times"></i>
                                </a>
                            </header>
                            <ul class="rows" on:mousedown|stopPropagation={()=>{}}>
                                {#each panel.rows as row (row.number)}
                                    <li class="row" data-number={row.number}>
                                        <div>
                                            <a href="#" data-action="removeRow" class="remove-row pull-right"
                                               on:click|preventDefault={() => removeRow(panel.number, row.number)}>
                                                <i class="fas fa-times"></i>
                                            </a>
                                        </div>
                                        <ul class="cells" on:mousedown|stopPropagation={()=>{}}>
                                            {#each row.cells as cell, cellIndex}
                                                {#if cell}
                                                    <li class="cell" draggable="true"
                                                        on:dragstart|stopPropagation={event => {event.dataTransfer.setData('name', cell.name)}}
                                                        data-id={cell.id}
                                                        data-name={cell.name}
                                                        data-full-width={cell.fullWidth ? 'true' : undefined}
                                                        data-custom-label={cell.customLabel ? cell.customLabel : undefined}
                                                        data-no-label={cell.noLabel}>
                                                        {cell.label}
                                                        <a href="#" data-action="removeField" class="remove-field"
                                                           on:click|preventDefault={() => removeField(panel.number, row.number, cellIndex)}>
                                                            <i class="fas fa-times"></i>
                                                        </a>
                                                    </li>
                                                {:else}
                                                    <li class="empty cell"
                                                        on:dragover|preventDefault={event => event.dataTransfer.dropEffect = 'move'}
                                                        on:drop={e => handleDrop(e,panel.number,row.number,cellIndex)}>
                                                        <a href="#" data-action="minusCell" class="remove-field"
                                                           on:click|preventDefault={() => minusCell(panel.number, row.number, cellIndex)}>
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
                        <li class="cell" data-name={field.name}
                            on:dragstart={event => {event.dataTransfer.setData('name', field.name)}}>
                            {field.label}
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