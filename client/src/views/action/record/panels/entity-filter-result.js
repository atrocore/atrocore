/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/record/panels/entity-filter-result', ['views/record/panels/relationship','views/search/search-filter-opener'],
    (Dep, SearchFilterOpener) => Dep.extend({

        rowActionsView: 'views/record/row-actions/relationship-view-only',

        readOnly: true,

        setup() {
            if (!this.panelVisible()) {
                return;
            }

            this.scope = this.model.get('targetEntity');
            this.url = this.model.get('targetEntity');

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
            }

            this.buttonList.unshift({
                title: this.translate('openSearchFilter'),
                action: 'openSearchFilter',
                html: this.getFilterButtonHtml()
            });

            this.listenTo(this.model, 'change:targetEntity', () => {
                this.reRender();
            });
        },

        setFilter(filter) {
            let data = this.model.get('data') || {};
            this.collection.where = data.where || [];
        },

        getFilterButtonHtml(){
           return SearchFilterOpener.prototype.getFilterButtonHtml.call(this, 'data');
        },

        actionOpenSearchFilter() {
            if(!this.model.get('targetEntity') || !this.getMetadata().get(['scopes', this.model.get('targetEntity')])) {
                this.notify(this.translate('The entity for the export is not valid'), 'error');
                return;
            }

            SearchFilterOpener.prototype.open.call(this, this.model.get('targetEntity'), this.model.get('data')?.where,  ({where, whereData}) => {
                this.model.set('data', _.extend({}, this.model.get('data'), {
                    where,
                    whereData,
                    whereScope: this.model.get('targetEntity')
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
            return this.model.get('targetEntity')
                && this.getAllowedActionTypes().includes(this.model.get('type'))
                && !this.model.get('applyToPreselectedRecords');
        },

        getAllowedActionTypes() {
            return ['update', 'delete', 'email'];
        }

    })
);