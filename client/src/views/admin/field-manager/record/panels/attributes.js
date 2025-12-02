/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/record/panels/attributes', 'views/record/panels/relationship',
    Dep => Dep.extend({

        rowActionsView: 'views/record/row-actions/relationship-no-unlink',

        setup() {
            if (!this.panelVisible()) {
                return;
            }

            this.scope = 'Attribute';
            this.url = 'Attribute';

            this.defs.select = false;
            this.defs.unlinkAll = false;

            this.model.defs.links.attributes = {
                entity: this.scope,
                type: "hasMany"
            }

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:hasAttribute', () => {
                this.reRender();
            })
        },

        actionCreateRelated(data) {
            let link = 'attributes';
            let scope = 'Attribute';

            let viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') || 'views/modals/edit';

            let attributes = {
                entityId: this.model.id,
                entityName: this.model.get('name')
            };

            this.notify('Loading...');
            this.createView('quickCreate', viewName, {
                scope: scope,
                fullFormDisabled: this.getMetadata().get('clientDefs.' + scope + '.modalFullFormDisabled') || false,
                attributes: attributes,
            }, view => {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    this.model.trigger('updateRelationshipPanel', link);
                    if (this.mode !== 'edit') {
                        this.model.trigger('after:relate', link);
                    }
                });
            });
        },

        setFilter(filter) {
            this.collection.where = [{
                type: "equals",
                attribute: "entityId",
                value: this.model.id
            }];
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.panelVisible()) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

        panelVisible() {
            if (!this.model.get('type')) {
                return this.getMetadata().get(['scopes', this.model.id, 'hasAttribute'])
                    && this.getMetadata().get(['scopes', this.model.id, 'type']) !== 'Derivative';
            }

            return this.model.get('hasAttribute') && this.model.get('type') !== 'Derivative';
        },
    })
);