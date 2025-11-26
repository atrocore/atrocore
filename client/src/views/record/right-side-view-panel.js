/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/right-side-view-panel', ['views/record/detail', 'view-record-helper'], function (Dep, ViewRecordHelper) {

    return Dep.extend({
        template: 'record/right-side-view',

        bottomView: null,

        layoutName: 'summary',

        setup: function () {
            if (typeof this.model === 'undefined') {
                throw new Error('Model has not been injected into record view.');
            }

            this.recordHelper = new ViewRecordHelper(this.defaultFieldStates, this.defaultFieldStates);

            this.once('remove', function () {
                if (this.isChanged) {
                    this.resetModelChanges();
                }
                this.setIsNotChanged();
                $(window).off('scroll.detail-' + this.numId);
            }, this);

            this.numId = Math.floor((Math.random() * 10000) + 1);
            this.id = Espo.Utils.toDom(this.entityType) + '-' + Espo.Utils.toDom(this.type) + '-' + this.numId;

            this.events = {};

            if (!this.editModeDisabled) {
                if ('editModeDisabled' in this.options) {
                    this.editModeDisabled = this.options.editModeDisabled;
                } else if (this.getMetadata().get(['scopes', this.model.name, 'disabled'])) {
                    this.editModeDisabled = true
                }
            }

            this.buttonsDisabled = this.options.buttonsDisabled || this.buttonsDisabled;

            // for backward compatibility
            // TODO remove in 5.6.0
            if ('buttonsPosition' in this.options && !this.options.buttonsPosition) {
                this.buttonsDisabled = true;
            }

            if ('isWide' in this.options) {
                this.isWide = this.options.isWide;
            }

            if ('bottomView' in this.options) {
                this.bottomView = this.options.bottomView;
            }

            this.sideDisabled = this.options.sideDisabled || this.sideDisabled;
            this.bottomDisabled = this.options.bottomDisabled || this.bottomDisabled;

            this.readOnlyLocked = this.readOnly;
            this.readOnly = this.options.readOnly || this.readOnly;

            this.inlineEditDisabled = this.inlineEditDisabled || this.getMetadata().get(['clientDefs', this.scope, 'inlineEditDisabled'])
                || this.getMetadata().get(['scopes', this.model.name, 'disabled']) || false;

            this.inlineEditDisabled = this.options.inlineEditDisabled || this.inlineEditDisabled;

            if (this.options.defs?.name === 'accessManagement') {
                this.detailLayout = this.getAccessManagementLayout()
            }

            this.listenTo(this.model, 'after:change-mode', (mode) => {
                if (mode === this.mode) {
                    return;
                }
                if (mode === 'edit') {
                    this.setEditMode();
                } else {
                    this.setDetailMode()
                }
            })

            this.setupBeforeFinal();
        },

        getAccessManagementLayout() {
            const rows = []
            const scopeDefs = this.getMetadata().get(['scopes', this.model.name]);

            if (scopeDefs['hasOwner']) {
                rows.push([{
                    "name": "ownerUser",
                    "fullWidth": true
                }])
            }

            if (scopeDefs['hasAssignedUser']) {
                rows.push([{
                    "name": "assignedUser",
                    "fullWidth": true
                }])
            }

            if (scopeDefs['hasTeam']) {
                rows.push([{
                    "name": "teams",
                    "fullWidth": true
                }])
            }

            rows.push([{
                "name": "created",
                "fullWidth": true
            }])

            rows.push([{
                "name": "modified",
                "fullWidth": true
            }])

            if (this.canLoadActivities()) {
                rows.push([{
                    "name": "followers",
                    "fullWidth": true
                }])
            }

            return [
                {
                    "rows": rows
                }
            ];
        },

        triggerModeChangedOnModel(mode) {
            // do nothing
        },

        setupBeforeFinal: function () {
            this.manageAccess();

            this.dependencyDefs = _.extend(this.getMetadata().get('clientDefs.' + this.model.name + '.formDependency') || {}, this.dependencyDefs);
            this.initDependancy();

            this.setupFieldLevelSecurity();
        },

        afterRender: function () {
            this.initListenToInlineMode();

            if (this.options.defs?.name === 'summary') {
                // hide access management panel if summary contains accessManagement panel
                if (this.layoutData.layout.find(item => item.label === 'accessManagement')) {
                    this.getParentView().hidePanel('accessManagement')
                }

                let emptyLayout = true
                this.layoutData.layout.forEach(panel => {
                    panel.rows.forEach(row => {
                        row.forEach(field => {
                            if (field) {
                                emptyLayout = false
                            }
                        })
                    })
                })

                if (this.getMetadata().get(['scopes', this.model.name, 'layouts']) && this.getUser().isAdmin() && this.mode === 'detail') {
                    // show configurator
                    const $container = this.$el.closest('.panel-summary').find('.panel-title')
                    $container.find('.layout-editor-container').remove()

                    $container.prepend('<span class="layout-editor-container"></span>')
                    this.createView('summaryLayoutConfigurator', "views/record/layout-configurator", {
                        scope: this.scope,
                        viewType: 'summary',
                        layoutData: this.layoutData,
                        el: $container.find('.layout-editor-container').get(0),
                    }, (v) => {
                        v.on("refresh", () => {
                            this.refreshLayout()
                        })
                        v.render()
                    })
                } else if (emptyLayout) {
                    this.getParentView().hidePanel('summary')
                }
            }
        }
    });
});
