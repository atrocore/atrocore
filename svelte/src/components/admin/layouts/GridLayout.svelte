<script lang="ts">
    import {createEventDispatcher, tick} from 'svelte';
    import Sortable from 'sortablejs';
    import Params from "./interfaces/Params"
    import {Notifier} from "../../../utils/Notifier";
    import BaseLayout from "./BaseLayout.svelte";
    import {Language} from "../../../utils/Language";
    import {LayoutManager} from "../../../utils/LayoutManager";
    import {Metadata} from "../../../utils/Metadata";
    import Group from "./interfaces/Group";
    import {Utils} from "../../../utils/Utils.js";
    import Field from "./interfaces/Field";
    import {Acl} from "../../../utils/Acl";
    import {UserData} from "../../../utils/UserData";

    export let params: Params;
    export let columnCount: number = 2;

    let panelDataAttributeList = ['id', 'label', 'style'];


    let panelDataAttributesDefs = {
        label: {type: 'varchar'},
        style: {
            type: 'enum',
            options: ['default', 'success', 'danger', 'primary', 'info', 'warning'],
            translation: 'LayoutManager.options.style'
        }
    };

    let defaultPanelFieldList = [];

    let panels: [] = [];
    let availableGroups: Group[] = [];
    let lastPanelNumber = -1;
    let lastRowNumber = -1;
    let sidePanelsLayout: any;

    const dispatch = createEventDispatcher();

    function loadLayout(callback) {
        let layoutData;

        const promiseList = [];

        promiseList.push(
            new Promise(resolve => {
                LayoutManager.get(params.scope, params.type, params.relatedScope, params.layoutProfileId, layoutLoaded => {
                    layoutData = layoutLoaded;
                    resolve();
                }, false, true);
            })
        );

        if (params.type === "detail") {
            promiseList.push(
                new Promise(resolve => {
                    LayoutManager.get(params.scope, 'sidePanelsDetail', null, params.layoutProfileId, layoutLoaded => {
                        sidePanelsLayout = layoutLoaded.layout;
                        resolve();
                    }, false, true);
                })
            );
        }

        Promise.all(promiseList).then(() => {
            if (callback) {
                readDataFromLayout(layoutData.layout);
                setupPanels();
                tick().then(() => {
                    initializeSortable();
                })
                callback(layoutData);
            }
        });
    }

    function getRelationScope(leftScope: string, rightScope: string) {
        const parts = rightScope.split('.')
        const link = Metadata.get(['entityDefs', parts[0], 'links', parts[1]]) ?? {}
        if (link.entity === leftScope && !!link.relationName) {
            return Espo.utils.upperCaseFirst(link.relationName)
        }
        return ''
    }

    function getTranslation(scope: string, field: string) {
        return Language.translate(field, 'fields', scope)
    }

    function getFieldType(scope: string, field: string) {
        return Metadata.get(['entityDefs', scope, 'fields', field, 'type']) ?? ''
    }

    function checkFieldType(type: string): boolean {
        if (params.fieldTypes) {
            return params.fieldTypes.includes(type)
        }
        return true;
    }

    function readDataFromLayout(layout) {
        const groups = []
        let relationScope = ''

        if (params.relatedScope) {
            relationScope = getRelationScope(params.scope, params.relatedScope)
            if (relationScope) {
                // load related scope field
                const group = {
                    name: relationScope,
                    scope: relationScope,
                    prefix: relationScope + '__'
                }
                let allFields = Object.keys(Metadata.get(['entityDefs', relationScope, 'fields']) || {}).filter(field =>
                    checkFieldType(getFieldType(relationScope, field)) && isFieldEnabled(relationScope, field)
                );

                // remove links
                allFields = allFields.filter(field => !(Metadata.get(['entityDefs', relationScope, 'fields', field, 'relationField']) ?? false))

                allFields = allFields.sort((v1, v2) =>
                    getTranslation(relationScope, v1).localeCompare(getTranslation(relationScope, v2))
                ).map(f => group.prefix + f)

                group.fields = allFields
                groups.push(group)
            }
        }

        let allFields = [];
        const labels = [];
        for (const field in Metadata.get(['entityDefs', params.scope, 'fields']) || {}) {
            if (isFieldEnabled(params.scope, field)) {
                labels.push(Language.translate(field, 'fields', params.scope));
                allFields.push(field);
            }
        }

        groups.unshift({
            name: params.scope,
            scope: params.scope,
            fields: allFields
        })

        const labelList: string[] = [];
        const duplicateLabelList: string[] = [];

        for (const group of groups) {
            group.fields = group.fields
                .map(field => {
                    const label = getTranslation(group.scope, group.prefix ? field.replace(group.prefix, '') : field);
                    if (!group.prefix) {
                        if (labelList.includes(label)) {
                            duplicateLabelList.push(label);
                        }
                        labelList.push(label);
                    }

                    return {name: field, label};
                });
        }

        panels = layout;

        let enabledFields = [];

        layout.forEach((panel, panelNum) => {
            panel.rows.forEach((row, rowNum) => {
                if (row) {
                    row.forEach((cell, i) => {
                        if (i == columnCount || !cell) {
                            return;
                        }
                        enabledFields.push(cell.name);
                    });
                }
            });
        });

        groups[0].fields.forEach(item => {
            if (duplicateLabelList.includes(item.label)) {
                item.label += ` (${item.name})`;
            }
            const selectedItem = getCell(item.name)
            if (selectedItem) {
                selectedItem.label = item.label
            }
        });

        if (groups[1]) {
            groups[1].fields.forEach(item => {
                item.label += ` (Relation)`
                const selectedItem = getCell(item.name)
                if (selectedItem) {
                    selectedItem.label = item.label
                }
            })
        }

        for (const group of groups) {
            group.fields = group.fields.filter(item => !enabledFields.find(name => name === item.name))
        }

        availableGroups = groups.reverse()
    }

    function getCell(name) {
        for (const panel of panels) {
            for (const row of panel.rows) {
                for (const cell of row) {
                    if (cell && cell.name === name) return cell
                }
            }
        }
        return null
    }

    function isFieldEnabled(scope: string, name: string) {
        if (hasDefaultPanel()) {
            if (defaultPanelFieldList.includes(name)) {
                return false;
            }
        }

        // remove multilang fields
        if (Metadata.get(['entityDefs', scope, 'fields', name, 'multilangField'])) {
            return false
        }

        const disabledParameters = ['disabled', `layout${Utils.upperCaseFirst(params.type)}Disabled`];
        if (params.reelType) {
            disabledParameters.push(`layout${Utils.upperCaseFirst(params.reelType)}Disabled`)
        }
        for (let param of disabledParameters) {
            if (Metadata.get(['entityDefs', scope, 'fields', name, param])) {
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
            let panelRemovedLayoutDisabled = false;
            panel.rows = panel.rows.map(row => {
                let rowRemovedLayoutDisabled = false
                row = row.map(cell => {
                    if(!cell) {
                        return cell;
                    }
                    cell.layoutRemoveDisabled = !!Metadata.get(['entityDefs', params.scope, 'fields', cell.name, 'layoutRemoveDisabled']);
                    rowRemovedLayoutDisabled = rowRemovedLayoutDisabled || cell.layoutRemoveDisabled;
                    return cell;
                });
                panelRemovedLayoutDisabled = panelRemovedLayoutDisabled || rowRemovedLayoutDisabled;
                return {
                    layoutRemoveDisabled: rowRemovedLayoutDisabled,
                    number: lastRowNumber++,
                    cells: row
                }
            });
            panel.layoutRemoveDisabled = panelRemovedLayoutDisabled;
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
        document.querySelectorAll('ul.cells.disabled').forEach(ul => {
            Sortable.create(ul, {
                animation: 150,
                group: 'cells',
                draggable: 'li.cell',
                onEnd: function (evt) {
                    const toUl = evt.to.closest('.cells.disabled')
                    let movedItem = null
                    if (toUl) {
                        if (toUl !== ul) {
                            // cancel drop
                            if (evt.oldIndex >= evt.from.children.length) {
                                evt.from.appendChild(evt.item);
                            } else {
                                evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                            }
                            return
                        }
                    }
                }
            });
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
            initPanel(el);
            el.scrollIntoView();
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

        addToGroups(fields)
    }

    function checkGroup(field: string, group: Group): boolean {
        if (group.prefix) {
            return field.startsWith(group.prefix)
        }
        return !field.includes('__')
    }

    function addToGroups(fields: []): void {
        availableGroups = availableGroups.map(group => {
            group.fields = [
                ...fields.filter(field => !!field && checkGroup(field.name, group)),
                ...group.fields
            ]
            return group
        })
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

                addToGroups(row.cells.filter(r => !!r))
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
                            addToGroups([removedCell])
                        }
                        row.cells[cellIndex] = false;
                    }
                    return row;
                });
            }
            return panel;
        });
    }

    function openLabelDialog(field: string, rowNumber: number, cellIndex: number) {
        params.openEditLabelDialog(params.scope, field, (label) => {
            panels = panels.map(panel => {
                panel.rows = panel.rows.map((row, rIndex) => {
                    if (row.number === rowNumber) {
                        row.cells[cellIndex].label = label;
                    }
                    return row;
                });
                return panel;
            })
        });
    }

    function openPanelLabelDialog(panel: Record<string, string>) {
        params.openEditLabelDialog(params.scope, panel.label, null, `${params.scope}.labels.${panel.label}`);
    }

    function isAdmin() {
        let data = UserData.get();

        return !!(data && data.user && data.user.isAdmin);
    }

    function handleDrop(event, panelNumber, rowNumber, cellIndex) {
        // get properties of dragged object
        const name = event.dataTransfer.getData('name')
        let field = null
        for (const group of availableGroups) {
            field = group.fields.find(f => f.name === name)
            if (field) break
        }

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
            availableGroups = availableGroups.map(group => {
                group.fields = group.fields.filter(f => f !== field)
                return group
            })
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
                <header><h5>{Language.translate('Current Layout', 'LayoutManager')}</h5> <a href="#" on:click|preventDefault={addPanel}>{Language.translate('Add Panel', 'Admin')}</a></header>
                <div class="rows-wrapper">
                    <ul class="panels">
                        {#each panels as panel (panel.number)}
                            <li data-number={panel.number} class="panel-layout">
                                <header data-name={panel.name}>
                                    <label data-is-custom={panel.customLabel ? 'true' : undefined}>{panel.customLabel || panel.label || ''}</label>&nbsp;
                                    <div>
                                        <a href="#" data-action="edit-panel-label" class="edit-panel-label"
                                           on:click|preventDefault={() => editPanelLabel(panel)}>
                                            <i class="ph ph-pencil-simple"></i>
                                        </a>
                                        {#if isAdmin() && panel.label}
                                            <a href="#" data-action="change-label" class="edit-panel-label"
                                               on:click|preventDefault={() => openPanelLabelDialog(panel)}>
                                                <i class="ph ph-globe-simple"></i>
                                            </a>
                                        {/if}
                                        {#if !panel.layoutRemoveDisabled}
                                            <a href="#" style="float: right;" data-action="removePanel" class="remove-panel"
                                               data-number={panel.number}
                                               on:click|preventDefault={() => removePanel(panel.number)}>
                                                <i class="ph ph-x"></i>
                                            </a>
                                        {/if}
                                    </div>
                                </header>
                                <ul class="rows" on:mousedown|stopPropagation={()=>{}}>
                                    {#each panel.rows as row (row.number)}
                                        <li class="row" data-number={row.number}>
                                            <div>
                                                {#if !row.layoutRemoveDisabled }
                                                    <a href="#" data-action="removeRow" class="remove-row pull-right"
                                                       on:click|preventDefault={() => removeRow(panel.number, row.number)}>
                                                        <i class="ph ph-x"></i>
                                                    </a>
                                                {/if}
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
                                                            data-no-label={cell.noLabel} title="{cell.label}">
                                                            <span class="left">{cell.label}</span>
                                                            <span class="right">
                                                                {#if !cell.layoutRemoveDisabled}
                                                                    <a href="#" data-action="removeField" class="remove-field"
                                                                       on:click|preventDefault={() => removeField(panel.number, row.number, cellIndex)}>
                                                                        <i class="ph ph-x"></i>
                                                                    </a>
                                                                {/if}
                                                                {#if isAdmin() && !cell.attributeId}
                                                                    <a href="#" data-action="change-label" class="change-label"
                                                                       on:click|preventDefault={() => openLabelDialog(cell.name, row.number, cellIndex)}>
                                                                        <i class="ph ph-globe-simple"></i>
                                                                    </a>
                                                                {/if}
                                                            </span>
                                                        </li>
                                                    {:else}
                                                        <li class="empty cell"
                                                            on:dragover|preventDefault={event => event.dataTransfer.dropEffect = 'move'}
                                                            on:drop={e => handleDrop(e,panel.number,row.number,cellIndex)}>
                                                            <span class="right">
                                                                <a href="#" data-action="minusCell" class="remove-field"
                                                                   on:click|preventDefault={() => minusCell(panel.number, row.number, cellIndex)}>
                                                                    <i class="ph ph-minus"></i>
                                                                </a>
                                                            </span>
                                                        </li>
                                                    {/if}
                                                {/each}
                                            </ul>
                                        </li>
                                    {/each}
                                </ul>
                                <div>
                                    <a href="#" data-action="addRow"
                                       on:click|preventDefault={() => addRow(panel.number)}>
                                        <i class="ph ph-plus"></i>
                                    </a>
                                </div>
                            </li>
                        {/each}
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="well available-fields">
                <header><h5>{Language.translate('Available Fields', 'Admin')}</h5></header>
                <div class="rows-wrapper">
                    {#each availableGroups as group (group.name)}
                        <div class:group={availableGroups.length>1}>
                            {#if availableGroups.length > 1 }
                                <span class="title">{Language.translate(group.name, 'scopeNames')}</span>
                            {/if}
                            <ul class="disabled cells clearfix" data-name="{group.name}">
                                {#each group.fields.sort((a, b) => a.label.localeCompare(b.label)) as field (field.name)}
                                    <li class="cell" data-name={field.name} title="{field.label}"
                                        on:dragstart={event => {event.dataTransfer.setData('name', field.name)}}>
                                        <span class="left">{field.label}</span>
                                    </li>
                                {/each}
                            </ul>
                        </div>
                    {/each}
                </div>
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
        padding: 5px 10px;
        margin: 0 0 5px 0;
        min-height: 80px;
        border: 1px solid #CCC;
        list-style: none;
    }

    #layout ul.panels > li:last-child {
        margin-bottom: 0;
    }

    #layout ul.rows {
        min-height: 80px;
    }

    #layout ul.rows > li {
        list-style: none;
        border: 1px solid #CCC;
        margin: 5px 0;
        padding: 5px;
        height: 72px;
    }

    #layout ul.cells {
        min-height: 30px;
        margin-top: 20px;
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

    #layout .available-fields ul.cells {
        margin-left: -5px;
        margin-right: -5px;
        width: auto;
        margin-top: 0;
    }

    #layout .available-fields ul.cells > li:first-child {
        margin-top: 0;
    }

    #layout ul.rows > li > div {
        width: auto;
    }

    #layout ul.cells > li a {
        float: right;
        margin-inline-start: 5px;
    }

    #layout ul.disabled {
        min-height: 200px;
        width: 100%;
    }

    #layout .group ul.disabled {
        min-height: 40px;
    }

    #layout ul.disabled > li a {
        display: none;
    }

    #layout header {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    #layout header h5 {
        margin-top: 0;
    }

    #layout .panel-layout header label {
        display: inline-block !important;
        margin-bottom: 0;
        min-height: 20px;
        vertical-align: top;
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
    }

    ul.cells li.cell {
        position: relative;
        cursor: pointer;
    }

    ul.cells li.cell a.remove-field,
    ul.cells li.cell a.change-label {
        display: none;
    }

    ul.cells li.cell:hover a.remove-field,
    ul.cells li.cell:hover a.change-label {
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
        margin-right: 5px;
    }

    ul.panels > li:hover a.edit-panel-label {
        display: inline-block;
    }

    ul.cells > li {
        display: flex;
    }

    ul.cells > li .left {
        flex: 1;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    ul.cells > li .right {
        flex-shrink: 0;
        margin-left: auto;
        margin-right: 0;
    }

    .col-md-8 {
        width: 66.66667%;
    }

    .col-md-4 {
        width: 33.33333%;
    }

    .well {
        height: 100%;
        border: 1px solid #ededed;
        border-radius: 3px;
    }

    .well .rows-wrapper {
        overflow-x: clip;
        overflow-y: auto;
        padding-right: 5px;
        margin-right: -5px;
        max-height: 70vh;
    }


    .group {
        border: 1px solid #ededed;
        border-radius: 2px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .group .title {
        font-weight: bold;
    }

    #layout {
        height: 100%;
    }

    #layout > * {
        height: 100%;
    }
</style>