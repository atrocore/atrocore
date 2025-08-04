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
        setupActionItems() {
            Dep.prototype.setupActionItems.call(this);

            let filterButton = {
                tooltip: this.translate('openSearchFilter'),
                action: 'openSearchFilter',
                name: 'filterButton',
                html: this.getFilterButtonHtml()
            };

            if(this.model.get('entityType')) {
                this.additionalButtons.push(filterButton);
            }

            this.listenTo(this.model, 'sync', () => {
                filterButton.html = this.getFilterButtonHtml();
                this.additionalButtons = this.additionalButtons.filter(b => b.name !== filterButton.name);
                if(this.model.get('entityType')) {
                    this.additionalButtons.push(filterButton);
                }
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

        getFilterButtonHtml() {
            return SearchFilterOpener.prototype.getFilterButtonHtml.call(this, 'data');
        },

        actionOpenSearchFilter() {
            if(!this.model.get('entityType') || !this.getMetadata().get(['scopes', this.model.get('entityType')])) {
                this.notify(this.translate('The entity for the export is not valid'), 'error');
                return;
            }

            SearchFilterOpener.prototype.open.call(this, this.model.get('entityType'), this.model.get('data')?.where,  ({where, whereData}) => {
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