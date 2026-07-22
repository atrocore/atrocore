/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/preview-template/record/detail', ['views/record/detail', 'views/search/search-filter-opener'],
    (Dep, SearchFilterOpener) => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'sync', () => {
                this.reRender();
            });

            this.listenTo(this.model, 'change:entityType', () => {
                let scope = this.model.get('entityType');

                let data = {};
                if (this.model.get('data')) {
                    data = this.model.get('data');
                }
                if (typeof data.whereScope === 'undefined' || data.whereScope !== scope) {
                    data = _.extend(data, {
                        where: null,
                        whereData: null,
                        whereScope: scope,
                    });
                    this.model.set('data', data);
                }
            });
        },

        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            if (this.model.get('entityType')) {
                this.additionalButtons.push({
                    tooltip: this.translate('openSearchFilter'),
                    action: 'openSearchFilter',
                    name: 'filterButton',
                    html: this.getFilterButtonHtml()
                });
            }
        },

        getFilterButtonHtml() {
            return SearchFilterOpener.prototype.getFilterButtonHtml.call(this, 'data');
        },

        actionOpenSearchFilter() {
            if(!this.model.get('entityType') || !this.getMetadata().get(['scopes', this.model.get('entityType')])) {
                this.notify(this.translate('The entity  is not valid'), 'error');
                return;
            }

            let whereData = this.model.get('data')?.where;

            if(this.model.get('data')?.whereData
                && (this.model.get('data')?.whereData['queryBuilder']
                    || this.model.get('data')?.whereData['bool']
                    || this.model.get('data')?.whereData['textFilter']
                    || this.model.get('data')?.whereData['savedSearch']
                )
            ){
                whereData = this.model.get('data')?.whereData;
            }

            SearchFilterOpener.prototype.open.call(this, this.model.get('entityType'), whereData,  ({where, whereData}) => {
                    this.model.set('data',  _.extend({}, this.model.get('data'), {
                        where,
                        whereData,
                        whereScope: this.model.get('entityType')
                    }));
                    this.notify(this.translate('saving', 'messages'));
                    this.model.save({_prev: null}).then(() =>  this.notify(this.translate('Done'), 'success'));
            });
        },

    })
);