/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/modals/dashboard-layout', ['views/layout-profile/modals/navigation', 'lib!gridstack'],
    (Dep) => Dep.extend({
        template: 'layout-profile/modals/dashboard-layout',

        className: 'full-page-modal',

        fullHeight: true,

        mode: 'edit',

        events: {
            'click button[data-action="selectTab"]': function (e) {
                var tab = parseInt($(e.currentTarget).data('tab'));
                this.selectTab(tab);
            },
            'click a[data-action="removeDashlet"]': function (e) {
                var id = $(e.currentTarget).data('id');
                this.removeDashlet(id);
            },
            'click a[data-action="editDashlet"]': function (e) {
                var id = $(e.currentTarget).data('id');
                var name = $(e.currentTarget).data('name');
                this.editDashlet(id, name);
            },
            'click button[data-action="editTabs"]': function () {
                this.editTabs();
            },
            'click button[data-action="addDashlet"]': function () {
                this.createView('addDashlet', 'views/modals/add-dashlet', {}, function (view) {
                    view.render();
                    this.listenToOnce(view, 'add', function (name) {
                        this.addDashlet(name);
                    }, this);
                }, this);
            },
        },

        data: function () {
            return {
                dashboardLayout: this.dashboardLayout,
                currentTab: this.currentTab
            };
        },

        setup() {
            Dep.prototype.setup.call(this);
            this.dashboardLayout = Espo.Utils.cloneDeep(this.model.get(this.field) || []);
            this.dashletsOptions = Espo.Utils.cloneDeep(this.model.get('dashletsOptions') || {});

            this.listenTo(this.model, 'change', function () {
                if (this.model.hasChanged(this.name)) {
                    this.dashboardLayout = Espo.Utils.cloneDeep(this.model.get(this.name) || []);
                }
                if (this.model.hasChanged('dashletsOptions')) {
                    this.dashletsOptions = Espo.Utils.cloneDeep(this.model.get('dashletsOptions') || {});
                }
                if (this.model.hasChanged(this.name)) {
                    if (this.dashboardLayout.length) {
                        this.selectTab(0);
                    }
                }
            }, this);

            this.currentTab = -1;
            this.currentTabLayout = null;

            if (this.dashboardLayout.length) {
                this.selectTab(0);
            }
            this.buttonList = [
                {
                    name: "Save",
                    label: "Save",
                    style: "primary"
                },
                {
                    name: "Cancel",
                    label: "Cancel",
                }
            ]
        },

        selectTab: function (tab) {
            this.currentTab = tab;
            this.setupCurrentTabLayout();
            if (this.isRendered()) {
                this.reRender();
            }
        },

        setupCurrentTabLayout: function () {
            if (!~this.currentTab) {
                this.currentTabLayout = null;
            }

            var tabLayout = this.dashboardLayout[this.currentTab].layout || [];
            tabLayout = GridStackUI.Utils.sort(tabLayout);
            this.currentTabLayout = tabLayout;
        },

        addDashetHtml: function (id, name) {
            var $item = this.prepareGridstackItem(id, name);

            var grid = this.$gridstack.data('gridstack');
            grid.addWidget($item, 0, 0, 2, 2);
        },

        addDashlet: function (name) {
            var id = 'd' + (Math.floor(Math.random() * 1000001)).toString();

            if (!~this.currentTab) {
                this.dashboardLayout.push({
                    name: 'My Espo',
                    layout: []
                });
                this.currentTab = 0;
                this.setupCurrentTabLayout();
                this.once('after:render', function () {
                    setTimeout(function() {
                        this.addDashetHtml(id, name);
                        this.fetchLayout();
                    }.bind(this), 50);
                }, this);
                this.reRender();
            } else {
                this.addDashetHtml(id, name);
                this.fetchLayout();
            }
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

            delete this.dashletsOptions[id];

            this.setupCurrentTabLayout();
        },

        editTabs: function () {
            this.createView('editTabs', 'views/modals/edit-dashboard', {
                dashboardLayout: this.dashboardLayout,
                tabListIsNotRequired: true
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

                    this.dashboardLayout = dashboardLayout;

                    this.selectTab(0);

                    this.deleteNotExistingDashletsOptions();
                }, this);
            }.bind(this));
        },

        deleteNotExistingDashletsOptions: function () {
            var idListMet = [];
            (this.dashboardLayout || []).forEach(function (itemTab) {
                (itemTab.layout || []).forEach(function (item) {
                    idListMet.push(item.id);
                }, this);
            }, this);

            Object.keys(this.dashletsOptions).forEach(function (id) {
                if (!~idListMet.indexOf(id)) {
                    delete this.dashletsOptions[id];
                }
            }, this);
        },

        editDashlet: function (id, name) {
            var options = this.dashletsOptions[id] || {};
            options = Espo.Utils.cloneDeep(options);

            var defaultOptions = this.getMetadata().get(['dashlets', name , 'options', 'defaults']) || {};

            Object.keys(defaultOptions).forEach(function (item) {
                if (item in options) return;
                options[item] = Espo.Utils.cloneDeep(defaultOptions[item]);
            }, this);

            if (!('title' in options)) {
                options.title = this.translate(name, 'dashlets');
            }

            var optionsView = this.getMetadata().get(['dashlets', name, 'options', 'view']) || 'views/dashlets/options/base';
            this.createView('options', optionsView, {
                name: name,
                optionsData: options,
                fields: this.getMetadata().get(['dashlets', name, 'options', 'fields']) || {}
            }, function (view) {
                view.render();
                this.listenToOnce(view, 'save', function (attributes) {
                    this.dashletsOptions[id] = attributes;
                    view.close();
                    if ('title' in attributes) {
                        this.$el.find('[data-id="'+id+'"] .panel-title').text(attributes.title);
                    }
                }, this);
            }, this);
        },

        fetchLayout: function () {
            if (!~this.currentTab) return;

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

            this.setupCurrentTabLayout();
        },

        afterRender: function () {
            if (this.currentTabLayout) {
                var $gridstack = this.$gridstack = this.$el.find('.modal-body .grid-stack');
                $gridstack.gridstack({
                    minWidth: 4,
                    cellHeight: 60,
                    verticalMargin: 10,
                    width: 4,
                    minWidth: this.getThemeManager().getParam('screenWidthXs'),
                    resizable: {
                        handles: 'se',
                        helper: false
                    },
                    staticGrid: this.mode !== 'edit',
                    disableResize: this.mode !== 'edit',
                    disableDrag: this.mode !== 'edit'
                });


                var grid = $gridstack.data('gridstack');
                grid.removeAll();

                this.currentTabLayout.forEach(function (o) {
                    var $item = this.prepareGridstackItem(o.id, o.name);
                    grid.addWidget($item, o.x, o.y, o.width, o.height);
                }, this);

                $gridstack.find(' .grid-stack-item').css('position', 'absolute');

                $gridstack.on('change', function (e, itemList) {
                    this.fetchLayout();
                    this.trigger('change');
                }.bind(this));
            }
        },

        prepareGridstackItem: function (id, name) {
            var $item = $('<div></div>');
            var actionsHtml = '';
            var actions2Html = '';
            if (this.mode == 'edit') {
                actionsHtml +=
                    '<a href="javascript:" class="pull-right" data-action="removeDashlet" data-id="'+id+'">'+
                    '<span class="fas fa-times"></span>'+
                    '</a>';
                actions2Html +=
                    '<a href="javascript:" class="pull-right" data-action="editDashlet" data-id="'+id+'" data-name="'+name+'">'+
                    this.translate('Edit') +
                    '</a>';
            }
            var headerHtml =
                '<div class="panel-heading">' +
                actionsHtml + '<h4 class="panel-title">' + (this.getOption(id, 'title') || this.translate(name, 'dashlets')) + '</h4>' +
                '</div>';
            var $container = $('<div class="grid-stack-item-content panel panel-default">' + headerHtml + '<div class="panel-body">'+actions2Html+'</div></div>');
            $container.attr('data-id', id);
            $container.attr('data-name', name);
            $item.attr('data-id', id);
            $item.attr('data-name', name);
            $item.append($container);

            return $item;
        },

        getOption: function (id, optionName) {
            var options = (this.model.get('dashletsOptions') || {})[id] || {};
            return options[optionName];
        },

        fetch: function () {
            var data = {};
            if (!this.dashboardLayout || !this.dashboardLayout.length) {
                data[this.name] = null;
            } else {
                data[this.name] = Espo.Utils.cloneDeep(this.dashboardLayout);
            }

            data['dashletsOptions'] = Espo.Utils.cloneDeep(this.dashletsOptions);

            return data;
        }
    })
);
