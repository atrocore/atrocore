/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/dashlets/records', 'views/dashlets/abstract/base', Dep => {

    return Dep.extend({

        name: 'Records',

        scope: null,

        _template: '<div class="list-container">{{{list}}}</div>',

        init() {
            Dep.prototype.init.call(this);

            this.scope = this.getOption('entityType');
        },

        checkAccess() {
            return this.getAcl().check(this.scope, 'read');
        },

        getSearchWhere() {
            return this.getOption('entityFilter');
        },

        afterRender() {
            this.getCollectionFactory().create(this.scope, function (collection) {
                if (!this.scope) {
                    this.$el.find('.list-container').html(this.translate('selectEntityType', 'messages', 'DashletOptions'));
                    return;
                }

                if (!this.checkAccess()) {
                    this.$el.find('.list-container').html(this.translate('No Access'));
                    return;
                }

                if (this.collectionUrl) {
                    collection.url = this.collectionUrl;
                }

                this.collection = collection;
                collection.sortBy = this.getOption('sortBy') || this.collection.sortBy;
                collection.asc = this.getOption('asc') || this.collection.asc;

                if (this.getOption('sortDirection') === 'asc') {
                    collection.asc = true;
                } else if (this.getOption('sortDirection') === 'desc') {
                    collection.asc = false;
                }

                collection.maxSize = this.getOption('displayRecords');

                let searchWhere = this.getSearchWhere();
                if (searchWhere && searchWhere.where) {
                    collection.where = searchWhere.where;
                }

                let view = this.getMetadata().get(`clientDefs.${this.scope}.recordViews.list`) || 'views/record/list';

                this.createView('list', view, {
                    collection: collection,
                    el: this.getSelector() + ' .list-container',
                    pagination: this.getOption('pagination') ? 'bottom' : false,
                    checkboxes: false,
                    showMore: true,
                    layoutName: 'list',
                    skipBuildRows: true
                }, view => {
                    view.getSelectAttributeList(selectAttributeList => {
                        if (selectAttributeList) {
                            collection.data.select = selectAttributeList.join(',');
                        }
                        collection.fetch();
                    });
                });

            }, this);
        },

        actionRefresh() {
            if (this.collection) {
                this.collection.fetch();
            }
        },

    });
});

