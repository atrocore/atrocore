/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/list-in-groups', 'views/record/list',
    Dep => Dep.extend({

        hiddenInEditColumns: ['isRequired'],

        template: 'record/list-in-groups',

        events: _.extend({
            'click [data-action="unlinkGroup"]': function (e) {
                e.preventDefault();
                this.trigger('remove-group', $(e.currentTarget).data())
            },
            'click [data-action="unlinkGroupHierarchy"]': function (e) {
                e.preventDefault();
                this.trigger('remove-group-hierarchically', $(e.currentTarget).data());
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);
            this.groupScope = this.groupScope || this.options.groupScope;
            this.scope = this.scope || this.options.scope;
            this.pipelines = {
                actionShowRevisionAttribute: ['clientDefs', this.scope, 'actionShowRevisionAttribute']
            }

            this.listenTo(this, 'after:save', model => {
                let panelView = this.getParentView();

                if (panelView && panelView.model) {
                    panelView.model.trigger('after:attributesSave');
                    panelView.actionRefresh();
                }
            });

            this.runPipeline('actionShowRevisionAttribute');
        },

        afterSave: function () {
            // do nothing
        },

        data() {
            let result = Dep.prototype.data.call(this);

            result.groupScope = this.groupScope;
            result.groupId = this.options.groupId;
            result.headerDefs = this.updateHeaderDefs(result.headerDefs);
            result.rowActionsColumnWidth = this.rowActionsColumnWidth;
            result.editable = !!this.options.groupId && this.getAcl().check(this.scope, 'delete');
            result.hierarchyEnabled = this.options.hierarchyEnabled
            return result;
        },

        getStatusIcons: function (model) {
            const htmlIcons = Dep.prototype.getStatusIcons.call(this, model) || [];

            if (model.get('isRequired')) {
                htmlIcons.push(`<i class="ph ph-asterisk required-sign pressable-icon" title="${this.translate('Required')}"></i>`);
            }

            return htmlIcons;
        },

        afterRenderStatusIcons(icons, model) {
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.setEditMode();
            }
        },

        updateHeaderDefs(defs) {
            if (defs[0]) {
                defs[0].name = this.options.groupName;

                if (this.options.groupId) {
                    defs[0].id = this.options.groupId;
                }
            }

            if (this.rowActionsView && !this.rowActionsDisabled && this.options.groupId) {
                defs.pop();
            }

            return defs;
        },

        prepareInternalLayout(internalLayout, model) {
            Dep.prototype.prepareInternalLayout.call(this, internalLayout, model);

            internalLayout.forEach(item => item.options.mode = this.options.mode || item.options.mode);
        },

        setListMode() {
            this.mode = 'list';
            this.updateModeInFields(this.mode);
        },

        setEditMode() {
            this.mode = 'edit';
            this.updateModeInFields(this.mode);
        },

        updateModeInFields(mode) {
            Object.keys(this.nestedViews).forEach(row => {
                let rowView = this.nestedViews[row];
                if (rowView) {
                    this.getEditableFields().forEach(field => {
                        let fieldView = rowView.getView(field + 'Field');
                        if (
                            fieldView
                            && fieldView.model
                            && !fieldView.model.getFieldParam(fieldView.name, 'readOnly')
                            && typeof fieldView.setMode === 'function'
                        ) {
                            fieldView.setMode(mode);
                            fieldView.reRender();
                        }
                    })
                }
            });
        },

        getEditableFields(){
            let fields = (this.listLayout ?? []).filter(p => p.editable).map(p => p.name);
            if(!fields.includes('value')){
                fields.push('value')
            }
            return fields;
        },
    })
);
