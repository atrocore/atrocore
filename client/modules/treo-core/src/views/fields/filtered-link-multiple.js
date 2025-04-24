/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/fields/filtered-link-multiple', 'views/fields/link-multiple',
    Dep => Dep.extend({

        selectBoolFilterList: [],

        boolFilterData: {},

        getBoolFilterData() {
            let data = {};
            this.selectBoolFilterList.forEach(item => {
                if (typeof this.boolFilterData[item] === 'function') {
                    data[item] = this.boolFilterData[item].call(this);
                }
            });
            return data;
        },

        setup() {
            Dep.prototype.setup.call(this);

            if (this.mode != 'list') {
                this.addActionHandler('selectLink', function () {
                    this.notify('Loading...');

                    let viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') || this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode != 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        boolFilterData: this.getBoolFilterData(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                        multiple: true
                    }, view => {
                        view.render();
                        this.notify(false);
                        this.listenToOnce(view, 'select', models => {
                            this.clearView('dialog');
                            if (Object.prototype.toString.call(models) !== '[object Array]') {
                                models = [models];
                            }
                            models.forEach(model => {
                                if (model.where) {
                                    this.notify('Loading...');
                                    this.ajaxGetRequest(this.foreignScope, {select: "id,name", where: model.where}, {async: false}).then(res => {
                                        if (res.list) {
                                            res.list.forEach(item => {
                                                this.addLink(item.id, item.name);
                                            });
                                        }
                                        this.notify(false);
                                    });
                                } else {
                                    this.addLink(model.id, model.get('name'));
                                }
                            });
                        });
                    });
                });
            }
        },

        getAutocompleteAdditionalWhereConditions() {
            let boolData = this.getBoolFilterData();
            // add boolFilter data
            if (boolData) {
                return [
                    {
                        'type': 'bool',
                        'data': boolData
                    }
                ];
            }

            return [];
        }

    })
);

