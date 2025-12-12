/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/selection/record/detail-bottom-comparison', 'view', function (Dep) {
    return Dep.extend({
        template: 'selection/record/detail-bottom-comparison',

        panelList: [],

        setup() {
            this.scope = this.options.scope;
            this.model = this.options.model;
            this.panelList = [];
            this.wait(true);
            this.loadRelationshipsLayout(() => {
                this.setupPanelViews();
                this.wait(false);
            });

        },

        data() {
            return {
                panelList: this.panelList,
                scope: this.scope
            }
        },

        loadRelationshipsLayout: function (callback) {
            var layoutName = 'selectionRelations';
            this._helper.layoutManager.get(this.model.name, layoutName, null, function (data) {
                this.layoutData = data;
                data.layout.forEach((item) => {
                    let p = Espo.Utils.clone(item);
                    if (!p.name) {
                        return;
                    }

                    let relationName = this.getMetadata().get(['entityDefs', this.scope, 'links', p.name, 'relationName']);

                    if (!relationName) {
                        return;
                    }

                    if ((this.getAcl().getScopeForbiddenFieldList(this.model.name, 'read') || []).includes(p.name)) {
                        return;
                    }

                    let links = (this.model.defs || {}).links || {};

                    let foreignScope = (links[p.name] || {}).entity;

                    p.scope = foreignScope;

                    if (foreignScope && !this.getAcl().check(foreignScope, 'read')) {
                        return;
                    }

                    if (this.getMetadata().get(['scopes', foreignScope], {}).disabled) {
                        return;
                    }

                    let defs = this.getMetadata().get(['clientDefs', this.scope, 'relationshipPanels', name]) || {};
                    for (let i in defs) {
                        if (i in p) continue;
                        p[i] = defs[i];
                    }

                    if (!p.view) {
                        p.view = 'views/selection/record/panels/relationship';
                    }

                    p.label = this.translate(p.name, 'fields', this.scope)

                    p.layoutConfigurator = p.name + 'LayoutConfigurator'
                    this.panelList.push(p);

                });
                callback.call(this);
            }.bind(this));
        },

        setupPanelViews() {
            this.panelList.forEach(p => {
                this.createView(p.layoutConfigurator, "views/record/layout-configurator", {
                    scope: this.scope,
                    viewType: 'selectionRelations',
                    layoutData: this.layoutData,
                    el: this.options.el + ' .panel[data-name="' + p.name + '"]' + ' .panel-heading .layout-editor-container',
                    alignRight: true
                }, (view) => {
                    view.on("refresh", () => {
                        this.getParentView()
                            .getParentView()
                            .getParentView()
                            .refreshContent()
                    });
                });
                this.createView(p.name, p.view, {
                    scope: p.scope,
                    model: this.model,
                    defs: p,
                    el: this.options.el + ' .panel[data-name="' + p.name + '"] > .panel-body'
                })
            })
        }

    });
});
