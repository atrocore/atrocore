

/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('treo-core:views/record/detail-bottom', 'class-replace!treo-core:views/record/detail-bottom', function (Dep) {

    return Dep.extend({

        template: 'treo-core:record/bottom',

        panelHeadingTemplate: 'treo-core:record/panel-heading',

        events: _.extend({
            'click span.collapser[data-action="collapsePanel"]': function (e) {
                this.collapseBottomPanel($(e.currentTarget).data('panel'));
            },
            'show.bs.collapse div.panel-body.panel-collapse.collapse': function (e) {
                this.afterPanelCollapsed($(e.currentTarget));
            },
            'hide.bs.collapse div.panel-body.panel-collapse.collapse': function (e) {
                this.afterPanelCollapsed($(e.currentTarget), true);
            },
        }, Dep.prototype.events),

        setupPanels: function () {},

        setupStreamPanel: function () {
            var streamAllowed = this.getAcl().checkModel(this.model, 'stream', true);
            if (streamAllowed === null) {
                this.listenToOnce(this.model, 'sync', function () {
                    streamAllowed = this.getAcl().checkModel(this.model, 'stream', true);
                    if (streamAllowed) {
                        this.showPanel('stream', function () {
                            this.getView('stream').collection.fetch();
                        });
                    }
                }, this);
            }
            if (streamAllowed !== false) {
                this.panelList.push({
                    "name":"stream",
                    "label":"Stream",
                    "view":"views/stream/panel",
                    "sticked": false,
                    "hidden": !streamAllowed,
                    "order": this.getConfig().get('isStreamPanelFirst') ? 2 : 5,
                    "expanded": !(this.getStorage().get('collapsed-panels', this.scope) || []).includes('stream')
                });
            }
        },

        setup: function () {
            this.type = this.mode;
            if ('type' in this.options) {
                this.type = this.options.type;
            }

            this.panelList = [];

            this.setupPanels();

            this.wait(true);

            Promise.all([
                new Promise(function (resolve) {
                    if (this.relationshipPanels) {
                        this.loadRelationshipsLayout(function () {
                            resolve();
                        });
                    } else {
                        resolve();
                    }
                }.bind(this))
            ]).then(function () {
                this.panelList = this.panelList.filter(function (p) {
                    if (p.aclScope) {
                        if (!this.getAcl().checkScope(p.aclScope)) {
                            return;
                        }
                    }
                    return true;
                }, this);

                if (this.relationshipPanels) {
                    this.setupRelationshipPanels();
                }

                this.panelList = this.panelList.map(function (p) {
                    var item = Espo.Utils.clone(p);
                    if (this.recordHelper.getPanelStateParam(p.name, 'hidden') !== null) {
                        item.hidden = this.recordHelper.getPanelStateParam(p.name, 'hidden');
                    } else {
                        this.recordHelper.setPanelStateParam(p.name, item.hidden || false);
                    }
                    return item;
                }, this);

                if (this.streamPanel && this.getMetadata().get('scopes.' + this.scope + '.stream') && !this.getConfig().get('isStreamSide')) {
                    this.setupStreamPanel();
                }

                this.setupPanelViews();
                this.wait(false);

            }.bind(this));

            this.listenTo(this, 'collapsePanel', (panel, type) => {
                this.collapseBottomPanel(panel, type);
            });
        },

        setupRelationshipPanels: function () {
            let scope = this.scope;

            let scopesDefs = this.getMetadata().get('scopes') || {};

            let panelList = this.relationshipsLayout;

            panelList.forEach(function (item) {
                let p;
                if (typeof item === 'string' || item instanceof String) {
                    p = {name: item};
                } else {
                    p = Espo.Utils.clone(item || {});
                }
                if (!p.name) {
                    return;
                }

                let name = p.name;

                let links = (this.model.defs || {}).links || {};
                let bottomPanels = this.getMetadata().get(['clientDefs', this.scope, 'bottomPanels', 'detail']) || [];
                let bottomPanelOptions = bottomPanels.find(panel => panel.name === name);
                if (!(name in links) && !bottomPanelOptions) {
                    return;
                }

                let defs = this.getMetadata().get('clientDefs.' + scope + '.relationshipPanels.' + name) || {};
                if (bottomPanelOptions) {
                    defs = bottomPanelOptions;
                }
                defs = Espo.Utils.clone(defs);

                if (defs.aclScopesList) {
                    if (!defs.aclScopesList.every(item => this.getAcl().checkScope(item, 'read'))) {
                        return;
                    }
                } else {
                    let foreignScope = (links[name] || {}).entity;
                    if ((scopesDefs[foreignScope] || {}).disabled) return;
                    if (foreignScope && !this.getAcl().check(foreignScope, 'read')) {
                        return;
                    }
                }

                for (let i in defs) {
                    if (i in p) continue;
                    p[i] = defs[i];
                }

                if (!p.view) {
                    p.view = bottomPanelOptions ? 'views/record/panels/bottom' : 'views/record/panels/relationship';
                }

                p.order = 5;

                if (this.recordHelper.getPanelStateParam(p.name, 'hidden') !== null) {
                    p.hidden = this.recordHelper.getPanelStateParam(p.name, 'hidden');
                } else {
                    this.recordHelper.setPanelStateParam(p.name, p.hidden || false);
                }

                p.expanded = !(this.getStorage().get('collapsed-panels', this.scope) || []).includes(p.name);

                this.panelList.push(p);
            }, this);
        },

        setupPanelViews() {
            this.setupOptionalPanels();
            this.sortPanelList();

            this.panelList.forEach(function (p) {
                this.createPanelView(p);
            }, this);
        },

        createPanelView(p, callback) {
            let name = p.name;
            this.createView(name, p.view, {
                model: this.model,
                panelName: name,
                el: this.options.el + ' .panel[data-name="' + name + '"] > .panel-body',
                defs: p,
                mode: this.mode,
                recordHelper: this.recordHelper,
                inlineEditDisabled: this.inlineEditDisabled,
                readOnly: this.readOnly,
                disabled: p.hidden || false,
                recordViewObject: this.recordViewObject
            }, function (view) {
                if ('getActionList' in view) {
                    p.actionList = this.filterActions(view.getActionList());
                }
                if ('getButtonList' in view) {
                    p.buttonList = this.filterActions(view.getButtonList());
                }

                if (view.titleHtml) {
                    p.titleHtml = view.titleHtml;
                }

                if (p.label) {
                    p.title = this.translate(p.label, 'labels', this.scope);
                } else {
                    p.title = view.title;
                }

                this.listenTo(view, 'panel:rebuild', defs => {
                    this.clearView(defs.name);
                    this.createPanelView(defs, (view, pDefs) => {
                        this.rebuildPanelHeading(pDefs);
                        view.render();
                    });

                    // refresh sync panels
                    let syncPanels = this.getMetadata().get(['clientDefs', this.scope, 'syncPanels', defs.name]) || [];
                    if (syncPanels !== []) {
                        let syncPanelDefs = null;
                        syncPanels.forEach(function (panelName) {
                            // prepare syncPanelDefs
                            syncPanelDefs = this.panelList.filter(panel => {
                                return panel.name === panelName;
                            }).shift();

                            if (typeof syncPanelDefs !== 'undefined') {
                                syncPanelDefs.select = defs.select;
                                syncPanelDefs.create = defs.create;
                                syncPanelDefs.readOnly = defs.readOnly;
                                syncPanelDefs.actionList = [];
                                syncPanelDefs.buttonList = [];

                                this.clearView(panelName);
                                this.createPanelView(syncPanelDefs, (view, pDefs) => {
                                    this.rebuildPanelHeading(pDefs);
                                    view.render();
                                });
                            }
                        }, this);
                    }
                });

                this.listenTo(view, 'collapsePanel', type => this.collapseBottomPanel(p.name, type));

                if (callback) {
                    callback(view ,p);
                }
            }, this);
        },

        rebuildPanelHeading(defs) {
            let panelHeading = this.$el.find(`.panel[data-name="${defs.name}"] > .panel-heading`);
            this._templator.getTemplate(this.panelHeadingTemplate, {}, false, template => {
                panelHeading.html(this._renderer.render(template, defs));
            });
        },

        setupOptionalPanels() {
            let optionalPanels = this.getMetadata().get(`clientDefs.${this.scope}.optionalBottomPanels`) || {};

            this.panelList = this.panelList.filter(panel => {
                if (panel.name in optionalPanels) {
                    return optionalPanels[panel.name].every(condition => this.model.get(condition.field) === condition.value);
                }
                return true;
            });
        },

        sortPanelList() {
            this.panelList.forEach((item, index) => item.index = index);
            this.panelList.sort((a, b) => (((a.order || 0) - (b.order || 0)) || (a.index - b.index)));
        },

        collapseBottomPanel(panel, type) {
            let panelBody = this.$el.find(`.panel-body[data-name="${panel}"]`);
            panelBody.collapse(type ? type : 'toggle');
        },

        afterPanelCollapsed(target, hide) {
            let collapser = target.prev().find(`span.collapser[data-panel="${target.data('name')}"]`);
            if (hide) {
                collapser.removeClass('fa-chevron-up');
                collapser.addClass('fa-chevron-down');
            } else {
                collapser.removeClass('fa-chevron-down');
                collapser.addClass('fa-chevron-up');
            }
            this.savePanelStateToStorage(target.data('name'), hide);
        },

        savePanelStateToStorage(panelName, hide) {
            let states = this.getStorage().get('collapsed-panels', this.scope) || [];
            if (!hide && states.includes(panelName)) {
                states.splice(states.indexOf(panelName), 1);
            } else if (hide && !states.includes(panelName)) {
                states.push(panelName);
            } else {
                return;
            }
            this.getStorage().set('collapsed-panels', this.scope, states);

        },

        getFieldViews(withHidden) {
            var fields = {};
            this.panelList.forEach(function (p) {
                var panelView = this.getView(p.name);
                if (panelView && (!panelView.disabled || withHidden)  && 'getFieldViews' in panelView) {
                    fields = _.extend(fields, panelView.getFieldViews());
                }
            }, this);
            return fields;
        }

    });
});


