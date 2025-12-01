/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

Espo.define('views/record/detail-bottom', ['view'], function (Dep) {

    return Dep.extend({

        template: 'record/bottom',

        panelHeadingTemplate: 'record/panel-heading',

        mode: 'detail',

        streamPanel: true,

        relationshipPanels: true,

        readOnly: false,

        canClose: true,

        layoutData: null,

        listInlineEditModeEnabled: false,

        data: function () {
            return {
                panelList: this.panelList,
                scope: this.scope,
                entityType: this.entityType
            };
        },

        afterRender: function () {
            this.panelList.forEach(panel => {
                const view = this.getView(panel.name)
                if (view && view.collection && view.collection.length > 0) {
                    view.collection.trigger('update-total')
                }
            })
        },

        events: {
            'click .action': function (e) {
                var $target = $(e.currentTarget);
                var action = $target.data('action');
                var panel = $target.data('panel');
                var data = $target.data();
                if (action) {
                    var method = 'action' + Espo.Utils.upperCaseFirst(action);
                    var d = _.clone(data);
                    delete d['action'];
                    delete d['panel'];
                    var view = this.getView(panel);
                    if (view && typeof view[method] == 'function') {
                        view[method].call(view, d, e);
                    }
                }
            },
            'click span.collapser[data-action="collapsePanel"]': function (e) {
                this.collapseBottomPanel($(e.currentTarget).data('panel'));
            },
            'show.bs.collapse div.panel-body.panel-collapse.collapse': function (e) {
                this.afterPanelCollapsed($(e.currentTarget));
            },
            'hide.bs.collapse div.panel-body.panel-collapse.collapse': function (e) {
                this.afterPanelCollapsed($(e.currentTarget), true);
            },
            'click [data-action="closePanel"]': function (e) {
                this.confirm({
                    message: this.translate('closePanelConfirmation', 'messages'),
                    confirmText: this.translate('Close')
                }, () => {
                    let name = $(e.currentTarget).data('panel');
                    this.$el.find(`.panel[data-name="${name}"]`).addClass('hidden');
                    this.addToClosedPanelPreferences([name]);
                });
            },
        },

        showPanel: function (name, callback) {
            this.recordHelper.setPanelStateParam(name, 'hidden', false);

            var isFound = false;
            this.panelList.forEach(function (d) {
                if (d.name == name) {
                    d.hidden = false;
                    isFound = true;
                }
            }, this);
            if (!isFound) return;

            if (this.isRendered()) {
                var view = this.getView(name);
                if (view) {
                    view.$el.closest('.panel').removeClass('hidden');
                    view.disabled = false;
                }
                if (callback) {
                    callback.call(this);
                }
            } else {
                if (callback) {
                    this.once('after:render', function () {
                        callback.call(this);
                    }, this);
                }
            }
        },

        hidePanel: function (name, callback) {
            this.recordHelper.setPanelStateParam(name, 'hidden', true);

            var isFound = false;
            this.panelList.forEach(function (d) {
                if (d.name == name) {
                    d.hidden = true;
                    isFound = true;
                }
            }, this);
            if (!isFound) return;

            if (this.isRendered()) {
                var view = this.getView(name);
                if (view) {
                    view.$el.closest('.panel').addClass('hidden');
                    view.disabled = true;
                }
                if (callback) {
                    callback.call(this);
                }
            } else {
                if (callback) {
                    this.once('after:render', function () {
                        callback.call(this);
                    }, this);
                }
            }
        },

        setupPanels: function () {
        },

        setupStreamPanel: function () {
            const panel = {
                name: "stream",
                label: "Activities",
                title: this.translate('Activities', 'labels'),
                view: "views/stream/panel",
                sticked: false,
                hidden: this.isPanelClosed('stream'),
                order: 5,
                expanded: this.getStorage().get('streamCollapseState', this.scope) === 'expanded',
                avoidLoadingOnCollapse: true
            }
            if (this.isInSmallView) {
                panel.expanded = false;
            }
            this.panelList.push(panel);
        },

        init: function () {
            this.recordHelper = this.options.recordHelper;
            this.scope = this.entityType = (this.options.scope ?? this.model.name);

            this.readOnlyLocked = this.options.readOnlyLocked || this.readOnly;
            this.readOnly = this.options.readOnly || this.readOnly;
            this.inlineEditDisabled = this.options.inlineEditDisabled || this.inlineEditDisabled;

            this.recordViewObject = this.options.recordViewObject;
        },

        setup: function () {
            this.type = this.mode;
            if ('type' in this.options) {
                this.type = this.options.type;
            }

            if ('canClose' in this.options) {
                this.canClose = this.options.canClose;
            }

            if ('isInSmallView' in this.options) {
                this.isInSmallView = this.options.isInSmallView;
            }

            if('listInlineEditModeEnabled' in this.options)  {
                this.listInlineEditModeEnabled =  this.options.listInlineEditModeEnabled;
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

                let streamAllowed = this.model
                    ? this.getAcl().checkModel(this.model, 'stream', true)
                    : this.getAcl().check(this.scope, 'stream');

                if (this.streamPanel && !this.getMetadata().get('scopes.' + this.scope + '.streamDisabled') && streamAllowed) {
                    this.setupStreamPanel();
                }

                this.setupPanelViews();
                this.wait(false);

            }.bind(this));

            this.listenTo(this, 'collapsePanel', (panel, type) => {
                this.collapseBottomPanel(panel, type);
            });

            this.listenTo(Backbone, 'create-bottom-panel', function (panel) {
                this.notify('Loading..');
                this.$el.find(`.panel[data-name="${panel.name}"]`).removeClass('hidden')
                panel.hidden = false;
                this.clearView(panel.name);
                this.createPanelView(panel, (view, pDefs) => {
                    this.rebuildPanelHeading(pDefs);
                    view.render();
                    this.removeFromClosedPanelPreferences([panel.name])
                    Backbone.trigger('after:create-bottom-panel', panel)
                    this.notify(false)

                });
            }.bind(this))
        },

        loadRelationshipsLayout: function (callback) {
            var layoutName = 'relationships';
            this._helper.layoutManager.get(this.model.name, layoutName, null, function (data) {
                this.layoutData = data
                this.relationshipsLayout = data.layout;
                callback.call(this);
            }.bind(this));
        },

        filterActions: function (actions) {
            var filtered = [];
            actions.forEach(function (item) {
                if (Espo.Utils.checkActionAccess(this.getAcl(), this.model, item) &&
                    filtered.findIndex(i => i.action === item.action && JSON.stringify(i.data) === JSON.stringify(item.data)) === -1) {
                    filtered.push(item);
                }
            }.bind(this));

            if (this.isInSmallView) {
                filtered = filtered.filter(function (item) {
                    return item.action === 'refresh';
                });
            }
            return filtered;
        },

        getFields: function () {
            return this.getFieldViews();
        },

        fetch: function () {
            var data = {};

            this.panelList.forEach(function (p) {
                var panelView = this.getView(p.name);
                if (panelView && !panelView.disabled && 'fetch' in panelView) {
                    data = _.extend(data, panelView.fetch());
                }
            }, this);
            return data;
        },

        isPreferenceScopeExists() {
            let preferences = this.getPreferences().get('closedPanels') ?? {};
            return preferences.hasOwnProperty(this.scope)
        },

        isPanelClosed(name) {
            let preferences = this.getPreferences().get('closedPanelOptions') ?? {};
            let scopePreferences = preferences[this.scope] ?? {}
            let panels = scopePreferences['closed'] ?? [];
            return panels.includes(name)
        },

        isPanelHiddenPerDefault(name) {
            let preferences = this.getPreferences().get('closedPanelOptions') ?? {};
            let scopePreferences = preferences[this.scope] ?? {}
            let panels = scopePreferences['hiddenPerDefault'] ?? []
            return panels.includes(name)
        },

        addToClosedPanelPreferences(names, isHiddenPerDefault = false) {
            if (names.length === 0) return;
            let preferences = this.getPreferences().get('closedPanelOptions') ?? {};
            let scopePreferences = preferences[this.scope] ?? {}
            let panels = scopePreferences['closed'] ?? []
            names.forEach(name => {
                if (!panels.includes(name)) {
                    panels.push(name)
                }
            })
            scopePreferences['closed'] = panels;

            if (isHiddenPerDefault) {
                panels = scopePreferences['hiddenPerDefault'] ?? []
                names.forEach(name => {
                    if (!panels.includes(name)) {
                        panels.push(name)
                    }
                })
                scopePreferences['hiddenPerDefault'] = panels;
            }

            preferences[this.scope] = scopePreferences;
            this.getPreferences().set('closedPanelOptions', preferences);
            this.getPreferences().save({ patch: true });
            this.getPreferences().trigger('update');
        },

        removeFromClosedPanelPreferences(names, fromHiddenPerDefault = false) {
            if (names.length === 0) return;
            let preferences = this.getPreferences().get('closedPanelOptions') ?? {};
            let scopePreferences = preferences[this.scope] ?? {};
            let panels = scopePreferences['closed'] ?? []

            panels = panels.filter(n => !names.includes(n));
            scopePreferences['closed'] = panels;

            if (fromHiddenPerDefault) {
                panels = scopePreferences['hiddenPerDefault'] ?? []
                panels = panels.filter(n => !names.includes(n));
                scopePreferences['hiddenPerDefault'] = panels;
            }

            preferences[this.scope] = scopePreferences;
            this.getPreferences().set('closedPanelOptions', preferences);
            this.getPreferences().save({ patch: true });
            this.getPreferences().trigger('update');
        },

        setupRelationshipPanels: function () {
            let scope = this.scope;

            let scopesDefs = this.getMetadata().get('scopes') || {};

            let panelList = this.relationshipsLayout;
            let toRemoveAsHiddenPerDefault = []
            panelList.forEach(function (item) {
                let p;
                if (typeof item === 'string' || item instanceof String) {
                    p = { name: item };
                } else {
                    p = Espo.Utils.clone(item || {});
                }
                if (!p.name) {
                    return;
                }

                if ((this.getAcl().getScopeForbiddenFieldList(this.model.name, 'read') || []).includes(p.name)) {
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

                p.canClose = p.canClose ?? this.canClose
                p.order = 5;
                if (p.hiddenPerDefault === true
                    && !this.isPanelHiddenPerDefault(p.name)) {
                    this.addToClosedPanelPreferences([p.name], true)
                } else if (p.hiddenPerDefault !== true && this.isPanelHiddenPerDefault(p.name)) {
                    toRemoveAsHiddenPerDefault.push(p.name)
                }

                if (this.isPanelClosed(p.name) && !toRemoveAsHiddenPerDefault.includes(p.name)) {
                    p.hidden = true
                    this.recordHelper.setPanelStateParam(p.name, true);
                }

                if (this.recordHelper.getPanelStateParam(p.name, 'hidden') !== null) {
                    p.hidden = this.recordHelper.getPanelStateParam(p.name, 'hidden');
                } else {
                    this.recordHelper.setPanelStateParam(p.name, p.hidden || false);
                }

                if(p.collapseByDefault) {
                    p.expanded = false;
                }else{
                    p.expanded = !(this.getStorage().get('collapsed-panels', this.scope) || []).includes(p.name);
                }

                if (this.isInSmallView) {
                    p.canClose = false;
                    p.expanded = false;
                    p.avoidLoadingOnCollapse = true
                }
                p.isInSmallView = this.isInSmallView;

                if(!('listInlineEditModeEnabled' in p))  {
                    p.listInlineEditModeEnabled =  this.options.listInlineEditModeEnabled;
                }

                this.setPanelTitle(p);

                this.panelList.push(p);
            }, this);

            this.removeFromClosedPanelPreferences(toRemoveAsHiddenPerDefault, true)
        },

        setupPanelViews() {
            if (this.options.staticAllowedPanelNames) {
                this.panelList = this.panelList.filter(p => {
                    return this.options.staticAllowedPanelNames.includes(p.name);
                });
            }

            this.setupOptionalPanels();
            this.sortPanelList();
            this.createPanelViews();
        },

        createPanelViews() {
            this.panelList.filter(p => {
                return !p.hidden && (p.expanded || !p.avoidLoadingOnCollapse);
            }).forEach(p => {
                this.createPanelView(p);
            });
        },

        createPanelView(p, callback) {
            let name = p.name;
            this.createView(name, p.view, {
                model: p.model ? p.model : this.model,
                panelName: name,
                el: this.options.el + ' .panel[data-name="' + name + '"] > .panel-body',
                defs: p,
                mode: this.mode,
                recordHelper: this.recordHelper,
                inlineEditDisabled: this.inlineEditDisabled,
                readOnly: this.readOnly,
                disabled: p.hidden || false,
                recordViewObject: this.recordViewObject,
                listInlineEditModeEnabled: !!p.listInlineEditModeEnabled
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

                this.setPanelTitle(p);

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
                    callback(view, p);
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

        collapseBottomPanel(name, type) {
            let panelBody = this.$el.find(`.panel-body[data-name="${name}"]`);
            let panelIndex = this.panelList.findIndex(panel => panel.name === name);
            let panel = this.panelList[panelIndex];

            if (!panel.alreadyLoaded && panel.avoidLoadingOnCollapse && !panelBody.hasClass('in')) {
                panelBody.html('<img class="preloader" style="height:12px;margin-top: 5px" src="client/img/atro-loader.svg">');
                panel.alreadyLoaded = true;
                panel.expanded = true;
                this.createPanelView(panel, (view, _) => {
                    if ('getActionList' in view) {
                        panel.actionList = this.filterActions(view.getActionList());
                    }
                    this.rebuildPanelHeading(panel);

                    view.render();
                });
                if (name === 'stream') {
                    this.getStorage().set('streamCollapseState', this.scope, 'expanded')
                }
            } else if (panelBody.hasClass('in') && name === 'stream') {
                this.getStorage().clear('streamCollapseState', this.scope)
            }
            panelBody.collapse(type ? type : 'toggle');
        },

        afterPanelCollapsed(target, hide) {
            const name = target.data('name');
            this.savePanelStateToStorage(name, hide);

            this.$el.find(`.panel[data-name="${name}"] > .panel-heading .collapser`).html(`<i class="ph ph-caret-${hide ? 'right' : 'down'}"></i>`);
        },

        savePanelStateToStorage(panelName, hide) {
            if (this.isInSmallView) {
                return;
            }

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
                if (panelView && (!panelView.disabled || withHidden) && 'getFieldViews' in panelView) {
                    fields = _.extend(fields, panelView.getFieldViews());
                }
            }, this);
            return fields;
        },

        setPanelTitle(panel) {
            if (panel.label) {
                let translated = this.translate(panel.name)
                panel.title = translated === panel.name ? this.translate(panel.label, 'labels', this.scope) : translated;
            } else {
                panel.title = this.translate(panel.name, 'fields', this.scope);
            }
            return panel;
        }

    });
});
