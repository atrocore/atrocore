/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/record/detail/compare', ['views/record/compare'], function (Dep) {

    return Dep.extend({

        disableModelFetch: true,

        models: [],

        actionList: [],

        selectionModel: null,

        hidePanelNavigation: true,

        events: _.extend({
            'click div.inline-actions a.swap-entity': function (e) {
                let id = $(e.currentTarget).data('id');
                let selectionRecordId = $(e.currentTarget).data('selection-record-id');
                let entityType = $(e.currentTarget).data('entity-type');

                if (!id || !entityType || !selectionRecordId) {
                    return;
                }

                const viewName = this.getMetadata().get(['clientDefs', entityType, 'modalViews', 'select']) || 'views/modals/select-records';

                this.createView('select', viewName, {
                    scope: entityType,
                    createButton: false,
                    multiple: false
                }, (dialog) => {
                    dialog.render();
                    dialog.once('select', model => {
                        this.notify('Loading...');
                        this.ajaxPatchRequest(`SelectionRecord/${selectionRecordId}`, {
                            entityId: model.id
                        }).then(() => this.getParentView().afterChangedSelectedRecords([selectionRecordId]));
                    });
                });
            },
            'click div.inline-actions a.remove-entity': function (e) {
                let selectionRecordId = $(e.currentTarget).data('selection-record-id');
                if (!selectionRecordId) {
                    return;
                }
                this.notify('Removing...');
                $.ajax({
                    url: `SelectionRecord/${selectionRecordId}`,
                    type: 'DELETE',
                    contentType: 'application/json',
                    success: () => {
                        this.getParentView().afterRemoveSelectedRecords([selectionRecordId])
                    }
                });
            }
        }, Dep.prototype.events),

        setup() {
            this.models = [];
            this.selectionModel = this.options.model;
            this.selectionId = this.selectionModel.id;
            this.hidePanelNavigation = true;
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'selection-record:loaded', models => {
                this.selectionModel.trigger('selection-record:loaded', models);
            })

            this.selectedFilters['fieldFilter'] = this.getStorage().get('fieldFilter', 'Selection');

            this.listenTo(this.selectionModel, 'overview-filters-changed', () => {
                this.selectedFilters['fieldFilter'] = this.getStorage().get('fieldFilter', 'Selection');
                this.reRenderFieldsPanels();
            })

            this.listenToOnce(this, 'after:relationship-panels-render', () => {

                let panelList = this.getRelationshipPanels().map(m => {
                    m.title = m.label;
                    return m;
                });

                panelList = this.getPanelWithFields().concat(panelList);
                this.trigger('detailPanelsLoaded', {list: panelList});
            });


            this.listenTo(this, 'after:fields-panel-rendered', () => {
                if (!this.getView('fieldsOverviews')) {
                    return;
                }
                this.getView('fieldsOverviews').$el.find('th.inline-actions').each(function (e) {
                    $(this).on('mouseenter', function (e) {
                        e.stopPropagation();
                        $(this).find('div.inline-actions').removeClass('hidden')
                    }.bind(this)).on('mouseleave', function (e) {
                        e.stopPropagation();
                        $(this).find('div.inline-actions').addClass('hidden')
                    }.bind(this));
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
        }
    });


});
