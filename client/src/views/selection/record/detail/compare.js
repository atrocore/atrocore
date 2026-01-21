/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail/compare', ['views/record/compare', 'views/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        disableModelFetch: true,

        models: [],

        actionList: [],

        selectionModel: null,

        hidePanelNavigation: true,

        itemScope: 'SelectionItem',

        hasReplaceRecord: true,

        hasRemoveRecord: true,

        events: _.extend({
            'click div.inline-actions a.swap-entity': function (e) {
                this.afterSwapButtonClick(e)
            },
            'click div.inline-actions a.remove-entity': function (e) {
                this.afterRemoveButtonClicked(e);
            }
        }, Dep.prototype.events),

        afterSwapButtonClick(e) {
            let id = $(e.currentTarget).data('id');
            let selectionItemId = $(e.currentTarget).data('selection-item-id');
            let entityType = $(e.currentTarget).data('entity-type');

            if (!id || !entityType || !selectionItemId) {
                return;
            }

            const viewName = this.getMetadata().get(['clientDefs', entityType, 'modalViews', 'select']) || 'views/modals/select-records';
            this.notify('Loading...');
            this.createView('select', viewName, {
                scope: entityType,
                createButton: false,
                multiple: false
            }, (dialog) => {
                dialog.render(() => {
                    this.notify(false);
                });
                dialog.once('select', model => {
                    if(model.id === id) {
                        this.notify(this.translate('notModified', 'messages'));
                        return;
                    }
                    this.notify('Loading...');
                    this.ajaxPatchRequest(`${this.itemScope}/${selectionItemId}`, {
                        entityId: model.id
                    }).then(() => this.getParentView().afterChangedSelectedRecords([model.id]));
                });
            });
        },

        afterRemoveButtonClicked(e) {
            let selectionItemId = $(e.currentTarget).data('selection-item-id');
            if (!selectionItemId) {
                return;
            }

            if(this.getModels().length <= 2) {
                this.notify(this.translate('youNeedAtLeastTwoItem', 'messages', 'Selection'), 'error');
                return;
            }

            this.notify(this.translate('Removing...'));

            $.ajax({
                url: `${this.itemScope}/${selectionItemId}`,
                type: 'DELETE',
                contentType: 'application/json',
                success: () => {
                    this.getParentView().afterRemoveSelectedRecords([selectionItemId])
                }
            });
        },

        setup() {
            this.models = [];
            this.selectionModel = this.options.model;
            this.selectionId = this.selectionModel.id;
            this.hidePanelNavigation = true;
            if (!this.selectedFilters) {
                this.selectedFilters = {}
            }
            this.selectedFilters['fieldFilter'] = this.getStorage().get('fieldFilter', 'Selection');

            Dep.prototype.setup.call(this);

            this.listenTo(this, 'selection-item:loaded', models => {
                this.selectionModel.trigger('selection-item:loaded', models);
            })

            this.listenTo(this.selectionModel, 'overview-filters-changed', () => {
                this.selectedFilters['fieldFilter'] = this.getStorage().get('fieldFilter', 'Selection');
                this.reRenderFieldsPanels();
            })

            this.listenToOnce(this, 'all-panels-rendered', () => {
                this.prepareRelationshipPanels((panelList) => {
                    panelList = this.getPanelWithFields().concat(panelList);
                    this.trigger('detailPanelsLoaded', {list: panelList});
                    this.getParentView().setupLayoutEditorButton();
                });
            });
        },

        executeAction: function (action, data = null, e = null) {
            var method = 'action' + Espo.Utils.upperCaseFirst(action);
            if (typeof this[method] == 'function') {
                this[method].call(this, data, e);
            }
        },

        getModels() {
            return this.models;
        },

        getRecordButtons() {
            return this.getParentView().getCompareButtons();
        },

        isComparisonAcrossScopes() {
            return  this.selectionModel.get('type') !== 'single' && this.selectionModel.get('entityTypes').length > 1;
        },

        canLoadActivities() {
            return true;
        },

        getSvelteSideViewProps(parentView) {
            let thisClone = Espo.utils.clone(this);

            thisClone.scope = 'Selection';
            thisClone.model = this.selectionModel;
            thisClone.mode = 'detail';

            let option = Detail.prototype.getSvelteSideViewProps.call(thisClone, parentView);

            option.showInsights = true;
            option.isCollapsed = false;

            return option;
        }
    });
});
