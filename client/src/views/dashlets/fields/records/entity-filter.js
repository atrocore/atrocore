/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/dashlets/fields/records/entity-filter', ['views/record/panels/bottom', 'search-manager'],
    (Dep, SearchManager) => Dep.extend({

        template: 'export:export-feed/simple-type-components/record/panels/simple-type-entity-filter',

        entitySearchView: 'export:views/export-feed/simple-type-components/record/entity-search',

        productSearchView: 'export:views/export-feed/simple-type-components/record/product-search',

        searchManager: null,

        collection: null,

        setup() {
            Dep.prototype.setup.call(this);

            this.scope = this.model.get('entity');

            console.log('1')

            let data = _.extend({}, this.model.get('data'));
            if (typeof data.whereScope === 'undefined' || data.whereScope !== this.scope) {
                data = _.extend(data, {
                    where: [],
                    whereData: {},
                    whereScope: this.scope,
                });
                this.model.set({data: data});
            }
        },

        setupSearchPanel() {
            this.getCollectionFactory().create(this.scope, collection => {
                this.collection = collection;
                this.searchManager = new SearchManager(this.collection, `exportSimpleType`, null, this.getDateTime(), (this.model.get('data') || {}).whereData || [], true);

                let searchView = this.entitySearchView;
                if (this.scope === 'Product' && this.getMetadata().get('scopes.Product.module') === 'Pim') {
                    searchView = this.productSearchView;
                }

                this.createView('search', searchView, {
                    collection: this.collection,
                    el: `${this.options.el} .search-container`,
                    searchManager: this.searchManager,
                    scope: this.scope,
                    entityType: this.model.name,
                    viewMode: 'list',
                    hiddenBoolFilterList: this.getMetadata().get(`clientDefs.${this.scope}.hiddenBoolFilterList`) || [],
                    feedModel: this.model,
                }, view => {
                    view.render();
                });
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            console.log('q111')

            // this.$el.parent().show();
            // if (!this.model.get('entity') || this.model.get('hasMultipleSheets')) {
            //     this.$el.parent().hide();
            // }
            //
            // this.setupSearchPanel()
        },

    })
);
