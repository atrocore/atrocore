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

Espo.define('views/dashboard', ['view', 'lib!gridstack'], function (Dep, Gridstack) {

    return Dep.extend({

        template: 'dashboard',

        dashboardLayout: null,

        currentTab: null,

        events: {
            'click button[data-action="selectTab"]': function (e) {
                var tab = parseInt($(e.currentTarget).data('tab'));
                this.selectTab(tab);
            },
            'change select[data-action="selectTab"]': function (e) {
                var tab = $(e.currentTarget).prop('selectedIndex');
                this.selectTab(tab);
            },
            'click button[data-action="addDashlet"]': function () {
                this.createView('addDashlet', 'views/modals/add-dashlet', {}, function (view) {
                    view.render();
                    this.listenToOnce(view, 'add', function (name) {
                        this.addDashlet(name);
                    }, this);
                }, this);
            },
            'click button[data-action="editTabs"]': function () {
                this.createView('editTabs', 'views/modals/edit-dashboard', {
                    dashboardLayout: this.dashboardLayout
                }, function (view) {
                    view.render();

                    this.listenToOnce(view, 'after:save', function (data) {
                        view.close();
                        var dashboardLayout = [];

                        dashboardLayout = dashboardLayout.filter(function (item, i) {
                            return dashboardLayout.indexOf(item) == i;
                        });

                        (data.dashboardTabList).forEach(function (name) {
                            var layout = [];
                            this.dashboardLayout.forEach(function (d) {
                                if (d.name == name) {
                                    layout = d.layout;
                                }
                            }, this);

                            if (name in data.renameMap) {
                                name = data.renameMap[name];
                            }
                            dashboardLayout.push({
                                name: name,
                                layout: layout
                            });
                        }, this);

                        this.dashletIdList.forEach(function (item) {
                            this.clearView('dashlet-' + item);
                        }, this);

                        this.dashboardLayout = dashboardLayout;
                        this.saveLayout();

                        this.storeCurrentTab(0);
                        this.currentTab = 0;
                        this.setupCurrentTabLayout();
                        this.reRender();

                    }, this);
                }.bind(this));
            },
        },

        data: function () {
            return {
                displayTitle: this.options.displayTitle,
                currentTab: this.currentTab,
                tabCount: this.dashboardLayout.length,
                dashboardLayout: this.dashboardLayout,
                layoutReadOnly: this.layoutReadOnly
            };
        },

        setupCurrentTabLayout: function () {
            if (!this.dashboardLayout) {
                var defaultLayout = [
                    {
                        "name": "My Espo",
                        "layout": []
                    }
                ];
                this.dashboardLayout = this.getConfig().get('lpDashboardLayout') || defaultLayout;

                if (this.dashboardLayout.length == 0 || Object.prototype.toString.call(this.dashboardLayout) !== '[object Array]') {
                    this.dashboardLayout = defaultLayout;
                }
            }

            var dashboardLayout = this.dashboardLayout || [];

            if (dashboardLayout.length <= this.currentTab) {
                this.currentTab = 0;
            }

            var tabLayout = dashboardLayout[this.currentTab].layout || [];

            tabLayout = GridStackUI.Utils.sort(tabLayout);

            this.currentTabLayout = tabLayout;
        },

        storeCurrentTab: function (tab) {
            this.getStorage().set('state', 'dashboardTab', tab);
        },

        selectTab: function (tab) {
            this.$el.find('.page-header button[data-action="selectTab"]').removeClass('active');
            this.$el.find('.page-header button[data-action="selectTab"][data-tab="'+tab+'"]').addClass('active');

            this.currentTab = tab;
            this.storeCurrentTab(tab);

            this.setupCurrentTabLayout();

            this.dashletIdList.forEach(function (id) {
                this.clearView('dashlet-'+id);
            }, this);
            this.dashletIdList = [];

            this.reRender();
        },

        setup: function () {
            this.currentTab = this.getStorage().get('state', 'dashboardTab') || 0;
            this.setupCurrentTabLayout();

            this.dashletIdList = [];

            var forbiddenPreferencesFieldList = this.getAcl().getScopeForbiddenFieldList('Preferences', 'edit');
            if (~forbiddenPreferencesFieldList.indexOf('dashboardLayout')) {
                this.layoutReadOnly = true;
            }
            if (~forbiddenPreferencesFieldList.indexOf('dashletsOptions')) {
                this.dashletsReadOnly = true;
            }

            this.once('remove', function () {
                if (this.$gridstack) {
                    var gridStack = this.$gridstack.data('gridstack');
                    if (gridStack) {
                        gridStack.destroy();
                    }
                }
            }, this);
        },

        afterRender: function () {
            this.initGridstack();
        },

        initGridstack: function () {
            var $gridstack = this.$gridstack = this.$el.find('> .dashlets');

            var draggable = false;
            var resizable = false;
            var disableDrag = false;
            var disableResize = false;

            $gridstack.gridstack({
                minWidth: 4,
                cellHeight: this.getThemeManager().getParam('dashboardCellHeight'),
                verticalMargin: 10,
                horizontalMargin: 10,
                width: 4,
                margin: 10,
                minWidth: this.getThemeManager().getParam('screenWidthXs'),
                handle: '.dashlet-container .panel-heading',
                disableDrag: disableDrag,
                disableResize: disableResize
            });

            var grid = $gridstack.data('gridstack');
            grid.removeAll();

            this.currentTabLayout.forEach(function (o) {
                var $item = this.prepareGridstackItem(o.id, o.name);
                grid.addWidget($item, o.x, o.y, o.width, o.height);
            }, this);

            $gridstack.find(' .grid-stack-item').css('position', 'absolute');

            this.currentTabLayout.forEach(function (o) {
                if (!o.id || !o.name) return;
                this.createDashletView(o.id, o.name);
            }, this);

            $gridstack.on('change', function (e, itemList) {
                this.fetchLayout();
                this.saveLayout();
            }.bind(this));

            $gridstack.on('resizestop', function (e, ui) {
                var id = $(e.target).data('id');
                var view = this.getView('dashlet-' + id);
                if (!view) return;
                view.trigger('resize');
            }.bind(this));
        },

        fetchLayout: function () {
            var layout = _.map(this.$gridstack.find('.grid-stack-item'), function (el) {
                var $el = $(el);
                var node = $el.data('_gridstack_node') || {};
                return {
                    id: $el.data('id'),
                    name: $el.data('name'),
                    x: node.x,
                    y: node.y,
                    width: node.width,
                    height: node.height
                };
            }.bind(this));

            this.dashboardLayout[this.currentTab].layout = layout;
        },

        prepareGridstackItem: function (id, name) {
            var $item = $('<div></div>');
            var $container = $('<div class="grid-stack-item-content dashlet-container"></div>');
            $container.attr('data-id', id);
            $container.attr('data-name', name);
            $item.attr('data-id', id);
            $item.attr('data-name', name);
            $item.append($container);

            return $item;
        },

        saveLayout: function () {
            if (this.layoutReadOnly) return;

            this.getPreferences().save({
                dashboardLayout: this.dashboardLayout
            }, {patch: true});
            this.getPreferences().trigger('update');
        },

        removeDashlet: function (id) {
            var grid = this.$gridstack.data('gridstack');
            var $item = this.$gridstack.find('.grid-stack-item[data-id="'+id+'"]');
            grid.removeWidget($item, true);

            var layout = this.dashboardLayout[this.currentTab].layout;
            layout.forEach(function (o, i) {
                if (o.id == id) {
                    layout.splice(i, 1);
                    return;
                }
            });

            var o = {};
            o.dashletsOptions = this.getPreferences().get('dashletsOptions') || {};
            delete o.dashletsOptions[id];

            o.dashboardLayout = this.dashboardLayout;

            if (this.layoutReadOnly) return;
            this.getPreferences().save(o, {patch: true});
            this.getPreferences().trigger('update');

            var index = this.dashletIdList.indexOf(id);
            if (~index) {
                this.dashletIdList.splice(index, index);
            }

            this.clearView('dashlet-' + id);
        },

        addDashlet: function (name) {
            var id = 'd' + (Math.floor(Math.random() * 1000001)).toString();

            var $item = this.prepareGridstackItem(id, name);

            var grid = this.$gridstack.data('gridstack');
            grid.addWidget($item, 0, 0, 2, 2);

            this.createDashletView(id, name, name, function () {
                this.fetchLayout();
                this.saveLayout();
            }, this);
        },

        createDashletView: function (id, name, label, callback, context) {
            var context = context || this;

            var o = {
                id: id,
                name: name
            }
            if (label) {
                o.label = label;
            }
            this.createView('dashlet-' + id, 'views/dashlet', {
                label: name,
                name: name,
                id: id,
                el: this.options.el + ' > .dashlets .dashlet-container[data-id="'+id+'"]',
                readOnly: this.dashletsReadOnly
            }, function (view) {
                this.dashletIdList.push(id);

                view.render();

                this.listenToOnce(view, 'change', function () {
                    this.clearView(id);
                    this.createDashletView(id, name, label, function (view) {
                    }, this);
                }, this);

                this.listenToOnce(view, 'remove-dashlet', function () {
                    this.removeDashlet(id);
                }, this);

                if (callback) {
                    callback.call(this, view);
                }
            }, this);
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Dashboard', 'labels'));
        }
    });
});

