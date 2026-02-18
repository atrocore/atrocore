/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/entity-filter-result', ['views/record/panels/relationship','views/search/search-filter-opener'],
    (Dep, SearchFilterOpener) => Dep.extend({

        readOnly: true,

        entityField: 'searchEntity',

        setup() {
            this.wait(true);

            this.onModelReady(() => {
                this.entityField = this?.options?.defs?.entityField || 'searchEntity';

                this.scope = this.model.get(this.entityField);
                this.url = this.model.get(this.entityField);
                this.model.defs.links.entityFilterResult = {
                    entity: this.scope,
                    type: "hasMany"
                }

                this.defs.create = false;
                this.defs.select = false;
                this.defs.unlinkAll = false;

                Dep.prototype.setup.call(this);

                if(!this.defs.hideShowFullList && !this.getPreferences().get('hideShowFullList')) {
                    this.actionList.push({
                        label: 'showFullList',
                        action: 'showFullList'
                    });
                    if(this.getMetadata().get(['clientDefs', this.scope, 'kanbanViewMode'])){
                        this.actionList.push({
                            label: 'showKanban',
                            action: 'showKanban'
                        });
                    }
                }

                this.buttonList.unshift({
                    title: this.translate('openSearchFilter'),
                    action: 'openSearchFilter',
                    html: this.getFilterButtonHtml()
                });

                this.listenTo(this.model, `change:${this.entityField}`, () => {
                    let scope = this.model.get(this.entityField);

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

                    this.reRender();
                });

                this.wait(false)
            })

        },

        setFilter(filter) {
            let data = this.model.get('data') || {};
            this.collection.where = data.where || [];
        },

        getFilterButtonHtml(){
           return SearchFilterOpener.prototype.getFilterButtonHtml.call(this, 'data');
        },

        actionOpenSearchFilter() {
            if(!this.model.get(this.entityField) || !this.getMetadata().get(['scopes', this.model.get(this.entityField)])) {
                this.notify(this.translate('The search entity is not valid'), 'error');
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

            SearchFilterOpener.prototype.open.call(this, this.model.get(this.entityField), whereData,  ({where, whereData}) => {
                this.model.set('data', _.extend({}, this.model.get('data'), {
                    where,
                    whereData,
                    whereScope: this.model.get(this.entityField)
                }));
                this.notify(this.translate('saving', 'messages'));
                this.model.save({_prev: null}).then(() =>  {
                    this.notify(this.translate('Done'), 'success')
                    this.setFilter(null);
                    this.actionRefresh();
                });
            });
        },

        actionShowFullList(data) {
            this.getStorage().set('listQueryBuilder', this.scope, this.model.get('data').whereData || {});
            window.open(`#${this.scope}`, '_blank');
        },

        actionShowKanban(data) {
            this.getStorage().set('listQueryBuilder', this.scope, this.model.get('data').whereData || {});
            window.open(`#${this.scope}/kanban`, '_blank');
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.panelVisible()) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }

            $('.panel-entityFilterResult button[data-action="openSearchFilter"]').html(this.getFilterButtonHtml());
        },

        panelVisible() {
            return !!(this.model.get(this.entityField));
        },

    })
);