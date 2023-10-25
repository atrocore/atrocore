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

Espo.define('views/main', 'view', function (Dep) {

    return Dep.extend({

        scope: null,

        name: null,

        menu: null,

        events: {
            'click .action': function (e) {
                var $target = $(e.currentTarget);
                var action = $target.data('action');
                var data = $target.data();
                if (action) {
                    var method = 'action' + Espo.Utils.upperCaseFirst(action);
                    if (typeof this[method] == 'function') {
                        e.preventDefault();
                        this[method].call(this, data, e);
                    }
                }
            },
        },

        init: function () {
            this.scope = this.options.scope || this.scope;
            this.menu = {};

            this.options.params = this.options.params || {};

            if (this.name && this.scope) {
                this.menu = this.getMetadata().get('clientDefs.' + this.scope + '.menu.' + this.name.charAt(0).toLowerCase() + this.name.slice(1)) || {};
            }

            this.menu = Espo.Utils.cloneDeep(this.menu);

            ['buttons', 'actions', 'dropdown'].forEach(function (type) {
                this.menu[type] = this.menu[type] || [];
            }, this);

            this.updateLastUrl();
            this.setupMassDeletingNotification();
            this.setupMassRestoringNotification();
            this.setupMassUpdatingNotification();
        },

        setupMassDeletingNotification: function () {
            this.listenTo(Backbone.Events, 'publicData', data => {
                let locationHash = window.location.hash;

                let hashScope = null;
                if (locationHash === '#Admin/jobs') {
                    hashScope = 'Job';
                } else {
                    hashScope = locationHash.split('/').shift().replace('#', '');
                }

                if (data.massDelete && hashScope === this.scope) {
                    if (data.massDelete[this.scope]) {
                        let scopeData = data.massDelete[this.scope];
                        if (scopeData.done) {
                            if (this.getStorage().get('massDeleteDoneHash', this.scope) !== scopeData.done) {
                                this.getStorage().set('massDeleteDoneHash', this.scope, scopeData.done);
                                Espo.Ui.notify(this.translate('Done'), 'success', 2000);
                                if (this.collection) {
                                    this.collection.fetch();
                                }
                            }
                        } else {
                            Espo.Ui.notify(this.translate('massDeleting', 'messages', 'Global').replace('{{deleted}}', scopeData.deleted).replace('{{total}}', scopeData.total), null, 2000);
                        }
                    }
                }
            });
        },
        setupMassRestoringNotification: function () {
            this.listenTo(Backbone.Events, 'publicData', data => {
                let locationHash = window.location.hash;

                let hashScope = null;
                if (locationHash === '#Admin/jobs') {
                    hashScope = 'Job';
                } else {
                    hashScope = locationHash.split('/').shift().replace('#', '');
                }

                if (data.massRestore && hashScope === this.scope) {
                    if (data.massRestore[this.scope]) {
                        let scopeData = data.massRestore[this.scope];
                        if (scopeData.done) {
                            if (this.getStorage().get('massRestoreDoneHash', this.scope) !== scopeData.done) {
                                this.getStorage().set('massRestoreDoneHash', this.scope, scopeData.done);
                                Espo.Ui.notify(this.translate('Done'), 'success', 2000);
                                if (this.collection) {
                                    this.collection.fetch();
                                }
                            }
                        } else {
                            Espo.Ui.notify(this.translate('massRestoring', 'messages', 'Global').replace('{{restored}}', scopeData.restored).replace('{{total}}', scopeData.total), null, 2000);
                        }
                    }
                }
            });
        },

        setupMassUpdatingNotification: function () {
            this.listenTo(Backbone.Events, 'publicData', data => {
                let locationHash = window.location.hash;

                let hashScope = null;
                if (locationHash === '#Admin/jobs') {
                    hashScope = 'Job';
                } else {
                    hashScope = locationHash.split('/').shift().replace('#', '');
                }

                if (data.massUpdate && hashScope === this.scope) {
                    if (data.massUpdate[this.scope]) {
                        let scopeData = data.massUpdate[this.scope];
                        if (scopeData.done) {
                            if (this.getStorage().get('massUpdateDoneHash', this.scope) !== scopeData.done) {
                                this.getStorage().set('massUpdateDoneHash', this.scope, scopeData.done);
                                Espo.Ui.notify(this.translate('Done'), 'success', 2000);
                                if (this.collection) {
                                    this.collection.fetch();
                                }
                            }
                        } else {
                            Espo.Ui.notify(this.translate('massUpdating', 'messages', 'Global').replace('{{deleted}}', scopeData.updated).replace('{{total}}', scopeData.total), null, 2000);
                        }
                    }
                }
            });
        },

        updateLastUrl: function () {
            this.lastUrl = this.getRouter().getCurrentUrl();
        },

        getMenu: function () {
            var menu = {};

            if (this.menu) {
                ['buttons', 'actions', 'dropdown'].forEach(function (type) {
                    (this.menu[type] || []).forEach(function (item) {
                        menu[type] = menu[type] || [];
                        if (Espo.Utils.checkActionAccess(this.getAcl(), this.model || this.scope, item)) {
                            menu[type].push(item);
                        }
                        item.name = item.name || item.action;
                        item.action = item.action || this.name;
                        item.cssStyle = item.cssStyle || '';
                    }, this);
                }, this);
            }

            return menu;
        },

        getHeader: function () {},

        buildHeaderHtml: function (arr, isAdmin) {
            let a = [];

            if (!isAdmin && this.entityType) {
                const tab = this.getMetadata().get(`scopes.${this.entityType}.tab`);
                if (tab === false) {
                    isAdmin = true;
                }
            }

            if (isAdmin) {
                a.unshift(`<a href='#Admin' class="action">${this.getLanguage().translate('Administration', 'labels')}</a>`);
            } else {
                a.unshift(`<a href='#' class="action">${this.getLanguage().translate('Dashboard', 'labels')}</a>`);
            }

            arr.forEach(function (item, index) {
                if (index !== arr.length - 1) {
                    a.push('<span class="subsection">' + item + '</span>');
                } else {
                    a.push('<span>' + item + '</span>');
                }
            }, this);

            return '<div class="header-breadcrumbs fixed-header-breadcrumbs"><div class="breadcrumbs-wrapper">' + a.join('') + '</div></div><div class="header-title">' + arr.pop() + '</div>';
        },

        getHeaderIconHtml: function () {
            return this.getHelper().getScopeColorIconHtml(this.scope);
        },

        actionShowModal: function (data) {
            var view = data.view;
            if (!view) {
                return;
            };
            this.createView('modal', view, {
                model: this.model,
                collection: this.collection
            }, function (view) {
                view.render();
                this.listenTo(view, 'after:save', function () {
                    if (this.model) {
                        this.model.fetch();
                    }
                    if (this.collection) {
                        this.collection.fetch();
                    }
                }, this);
            }.bind(this));
        },

        addMenuItem: function (type, item, toBeginnig, doNotReRender) {
            item.name = item.name || item.action;
            var name = item.name;

            var index = -1;
            this.menu[type].forEach(function (data, i) {
                if (data.name === name) {
                    index = i;
                    return;
                }
            }, this);
            if (~index) {
                this.menu[type].splice(index, 1);
            }

            var method = 'push';
            if (toBeginnig) {
                method  = 'unshift';
            }
            this.menu[type][method](item);

            if (!doNotReRender && this.isRendered()) {
                this.getView('header').reRender();
            }
        },

        disableMenuItem: function (name) {
            this.$el.find('.header .header-buttons [data-name="'+name+'"]').addClass('disabled').attr('disabled');
        },

        enableMenuItem: function (name) {
            this.$el.find('.header .header-buttons [data-name="'+name+'"]').removeClass('disabled').removeAttr('disabled');
        },

        removeMenuItem: function (name, doNotReRender) {
            var index = -1;
            var type = false;

            ['actions', 'dropdown', 'buttons'].forEach(function (t) {
                this.menu[t].forEach(function (item, i) {
                    if (item.name == name) {
                        index = i;
                        type = t;
                    }
                }, this);
            }, this);

            if (~index && type) {
                this.menu[type].splice(index, 1);
            }

            if (!doNotReRender && this.isRendered()) {
                this.getView('header').reRender();
            }
        },

        actionNavigateToRoot: function (data, e) {
            e.stopPropagation();

            this.getRouter().checkConfirmLeaveOut(function () {
                var options = {
                    isReturn: true
                };
                var rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                this.getRouter().navigate(rootUrl, {trigger: false});
                this.getRouter().dispatch(this.scope, null, options);
            }, this);
        },

        hideHeaderActionItem: function (name) {
            ['actions', 'dropdown', 'buttons'].forEach(function (t) {
                this.menu[t].forEach(function (item, i) {
                    if (item.name == name) {
                        item.hidden = true;
                    }
                }, this);
            }, this);
            if (!this.isRendered()) return;
            this.$el.find('.page-header li > .action[data-action="'+name+'"]').parent().addClass('hidden');
            this.$el.find('.page-header a.action[data-action="'+name+'"]').addClass('hidden');
        },

        showHeaderActionItem: function (name) {
            ['actions', 'dropdown', 'buttons'].forEach(function (t) {
                this.menu[t].forEach(function (item, i) {
                    if (item.name == name) {
                        item.hidden = false;
                    }
                }, this);
            }, this);
            if (!this.isRendered()) return;
            this.$el.find('.page-header li > .action[data-action="'+name+'"]').parent().removeClass('hidden');
            this.$el.find('.page-header a.action[data-action="'+name+'"]').removeClass('hidden');
        }

    });
});


