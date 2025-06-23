/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/search/modals/select-filter-search', 'views/modals/select-records', function (Dep) {
    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            this.multiple = false;
            this.createButton = false;
            this.header = this.translate(this.scope, 'scopeNames');
            this.header += ' / ' + this.translate('searchResults')
            this.buttonList =  [
                {
                    name: 'applySearch',
                    style: 'primary',
                    label: 'applySearch',
                    disabled: true,
                    onClick: (dialog) => {
                        var where = this.collection.where;
                        this.trigger('select', where);
                        dialog.close();
                    }
                },
                {
                    name: 'unsetSearch',
                    style: 'primary',
                    label: 'unsetSearch',
                    disabled: !this.options.disabledUnsetSearch,
                    onClick: (dialog) => {
                        this.trigger('select', {
                            where: null
                        });
                        dialog.close();
                    }
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            // listening directly on the collection is not working everytime, so I used backbone instead as a workaround
            this.listenTo(window.Backbone, 'filter-state:changed', (collection) => {
                if(collection === this.collection) {
                    if(this.searchManager.isFilterSet() || this.searchManager.geTextFilter().trim().length > 0) {
                        this.$el.find('button[data-name="applySearch"]').removeClass('disabled');
                        this.$el.find('button[data-name="applySearch"]').attr('disabled', false);
                    }else{
                        this.$el.find('button[data-name="applySearch"]').addClass('disabled');
                        this.$el.find('button[data-name="applySearch"]').attr('disabled', 'disabled');
                    }
                }
            });
        },

        loadList: function () {
            let viewName = this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.listSelect') ||
                this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') ||
                'views/record/list';

            const options = {
                collection: this.collection,
                el: this.containerSelector + ' .list-container',
                selectable: true,
                checkboxes: false,
                massActionsDisabled: true,
                rowActionsView: false,
                layoutName: this.layoutName,
                searchManager: this.searchManager,
                buttonsDisabled: true,
                skipBuildRows: true
            }

            if (typeof this.options.allowSelectAllResult === 'boolean') {
                options.allowSelectAllResult = this.options.allowSelectAllResult;
            }

            this.createView('list', viewName, options, function (view) {
                this.listenTo(view, 'select', function (model) {
                    window.open(`#${this.scope}/view/${model.id}`, '_blank');
                }.bind(this));
                    view.getSelectAttributeList(function (selectAttributeList) {
                        if (!~selectAttributeList.indexOf('name')) {
                            selectAttributeList.push('name');
                        }
                        var mandatorySelectAttributeList = this.options.mandatorySelectAttributeList || this.mandatorySelectAttributeList || [];
                        mandatorySelectAttributeList.forEach(function (attribute) {
                            if (!~selectAttributeList.indexOf(attribute)) {
                                selectAttributeList.push(attribute);
                            }
                        }, this);

                        if (selectAttributeList) {
                            this.collection.data.select = selectAttributeList.join(',');
                        }
                        this.listenToOnce(view, 'after:build-rows', function () {
                            this.wait(false);
                        }, this);
                        this.collection.fetch();
                    }.bind(this));
            });
        },

        isHierarchical() {
            return false;
        }

    })
})