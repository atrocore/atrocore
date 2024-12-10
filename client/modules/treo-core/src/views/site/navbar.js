/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/site/navbar', 'class-replace!treo-core:views/site/navbar', function (Dep) {

    return Dep.extend({

        template: 'treo-core:site/navbar',

        isMoreFields: false,

        openMenu: function () {
            this.events = _.extend({}, this.events || {}, {
                'click .navbar-toggle': function () {
                    this.$el.find('.menu').toggleClass('open-menu');
                    let headerBreadcrumbs = $('.header-breadcrumbs');
                    if ($(window).scrollTop() > $('.page-header').outerHeight() && !$('#header .navbar .menu').hasClass('open-menu')) {
                        headerBreadcrumbs.addClass('fixed-header-breadcrumbs');
                    } else {
                        headerBreadcrumbs.removeClass('fixed-header-breadcrumbs');
                    }
                },

                'click .menu.open-menu a.nav-link': function (e) {
                    var $a = $(e.currentTarget);
                    var href = $a.attr('href');
                    if (href && href != '#') {
                        this.$el.find('.menu').removeClass('open-menu');
                    }
                },

                'click .search-toggle': function () {
                    this.$el.find('.navbar-collapse ').toggleClass('open-search');
                },
            });
        },

        data() {
            return _.extend({
                hasJM: this.getAcl().check('Job', 'read'),
                isMoreFields: this.isMoreFields,
                lastViewed: !this.getConfig().get('actionHistoryDisabled')
            }, Dep.prototype.data.call(this));
        },

        setup() {
            this.getRouter().on('routed', function (e) {
                if (e.controller) {
                    this.selectTab(e.controller);
                } else {
                    this.selectTab(false);
                }
            }.bind(this));

            var tabList = this.getTabList();
            this.isMoreFields = tabList.some(tab => tab === '_delimiter_');

            var scopes = this.getMetadata().get('scopes') || {};

            this.tabList = tabList.filter(function (scope) {
                if (typeof scopes[scope] === 'undefined' && scope !== '_delimiter_') return;
                if ((scopes[scope] || {}).disabled) return;
                if ((scopes[scope] || {}).acl) {
                    return this.getAcl().check(scope);
                }
                return true;
            }, this);

            this.quickCreateList = this.getQuickCreateList().filter(function (scope) {
                if ((scopes[scope] || {}).disabled) return;
                if ((scopes[scope] || {}).acl) {
                    return this.getAcl().check(scope, 'create');
                }
                return true;
            }, this);

            this.createView('notificationsBadge', 'views/notification/badge', {
                el: this.options.el + ' .notifications-badge-container',
                intervalConditions: [
                    () => {
                        return $(window).innerWidth() < 768;
                    }
                ]
            });

            this.createView('notificationsBadgeRight', 'views/notification/badge', {
                el: `${this.options.el} .navbar-right .notifications-badge-container`,
                intervalConditions: [
                    () => {
                        return $(window).innerWidth() >= 768;
                    }
                ]
            });

            this.createView('footer', 'views/site/navbar-footer', {
                el: `${this.options.el} footer`
            })

            if (!this.getConfig().get('actionHistoryDisabled')) {
                this.createView('lastViewedBadge', 'views/last-viewed/badge', {
                    el: this.options.el + ' .last-viewed-badge-container'
                });

                this.createView('lastViewedBadgeRight', 'views/last-viewed/badge', {
                    el: `${this.options.el} .navbar-right .last-viewed-badge-container`
                });
            }

            this.setupGlobalSearch();

            this.setupTabDefsList();

            this.once('remove', function () {
                $(window).off('resize.navbar');
                $(window).off('scroll.navbar');
            });

            this.openMenu();

            this.listenTo(Backbone, 'tree-panel-expanded', () => {
                this.switchMinimizer(true);
            });
        },

        switchMinimizer(afterTrigger) {
            let $body = $('body');
            if (!afterTrigger && $body.hasClass('minimized')) {
                $body.removeClass('minimized');
                this.getStorage().set('state', 'siteLayoutState', 'expanded');
                Backbone.trigger('menu-expanded');
            } else {
                $body.addClass('minimized');
                if (!afterTrigger) {
                    this.getStorage().set('state', 'siteLayoutState', 'collapsed');
                }
            }
            if (window.Event) {
                try {
                    window.dispatchEvent(new Event('resize'));
                } catch (e) {}
            }
        },

        adjust: function () {
            var $window = $(window);

            var navbarIsVertical = this.getThemeManager().getParam('navbarIsVertical');
            var navbarStaticItemsHeight = this.getThemeManager().getParam('navbarStaticItemsHeight') || 0;

            var smallScreenWidth = this.getThemeManager().getParam('screenWidthXs');

            if (!navbarIsVertical) {
                var $tabs = this.$el.find('ul.tabs');
                var $moreDropdown = $tabs.find('li.more');
                var $more = $tabs.find('li.more > ul');

                $window.on('resize.navbar', function() {
                    updateWidth();
                });

                var hideOneTab = function () {
                    var count = $tabs.children().size();
                    if (count <= 1) return;
                    var $one = $tabs.children().eq(count - 2);
                    $one.prependTo($more);
                };
                var unhideOneTab = function () {
                    var $one = $more.children().eq(0);
                    if ($one.size()) {
                        $one.insertBefore($moreDropdown);
                    }
                };

                var tabCount = this.tabList.length;
                var $navbar = $('#navbar .navbar');
                var navbarNeededHeight = (this.getThemeManager().getParam('navbarHeight') || 43) + 1;

                $moreDd = $('#nav-more-tabs-dropdown');
                $moreLi = $moreDd.closest('li');

                var navbarBaseWidth = this.getThemeManager().getParam('navbarBaseWidth') || 516;

                var updateWidth = function () {
                    var windowWidth = $(window.document).width();
                    var windowWidth = window.innerWidth;
                    var moreWidth = $moreLi.width();

                    $more.children('li.not-in-more').each(function (i, li) {
                        unhideOneTab();
                    });

                    if (windowWidth < smallScreenWidth) {
                        return;
                    }

                    $more.parent().addClass('hidden');

                    var headerWidth = this.$el.width();

                    var maxWidth = headerWidth - navbarBaseWidth - moreWidth;
                    var width = $tabs.width();

                    var i = 0;
                    while (width > maxWidth) {
                        hideOneTab();
                        width = $tabs.width();
                        i++;
                        if (i >= tabCount) {
                            setTimeout(function () {
                                updateWidth();
                            }, 100);
                            break;
                        }
                    }

                    if ($more.children().size() > 0) {
                        $moreDropdown.removeClass('hidden');
                    }
                }.bind(this);

                var processUpdateWidth = function (isRecursive) {
                    if ($navbar.height() > navbarNeededHeight) {
                        updateWidth();
                        setTimeout(function () {
                            processUpdateWidth(true);
                        }, 200);
                    } else {
                        if (!isRecursive) {
                            setTimeout(function () {
                                processUpdateWidth(true);
                            }, 10);
                        }
                        setTimeout(function () {
                            processUpdateWidth(true);
                        }, 1000);
                    }
                };

                if ($navbar.height() <= navbarNeededHeight && $more.children().size() === 0) {
                    $more.parent().addClass('hidden');
                }

                processUpdateWidth();

            } else {
                var $tabs = this.$el.find('ul.tabs');

                var $more = $tabs.find('li.more > ul');

                if ($more.children().size() === 0) {
                    $more.parent().addClass('hidden');
                }

                var updateSizeForVertical = function () {
                    var windowHeight = window.innerHeight;
                    var windowWidth = window.innerWidth;

                    if (windowWidth < smallScreenWidth) {
                        $tabs.css('height', 'auto');
                        $more.css('max-height', '');
                    } else {
                        let tabsHeight = windowHeight - navbarStaticItemsHeight;
                        $tabs.css('height', tabsHeight + 'px');
                        $more.css('max-height', windowHeight + 'px');
                    }

                    var minHeight = $tabs.height() + navbarStaticItemsHeight;
                    $('body').css('minHeight', minHeight + 'px');
                }.bind(this);

                $(window).on('resize.navbar', function() {
                    updateSizeForVertical();
                });
                updateSizeForVertical();
            }
        },

        init() {
            Dep.prototype.init.call(this);

            this.listenToOnce(this, 'after:render', () => {
                this.initProgressBadge();
            });
        },

        renderQmPanelList() {
            this.getCollectionFactory().create('Job', collection => {
                collection.maxSize = 20;
                collection.url = 'Job';
                collection.sortBy = 'startedAt';
                collection.asc = true;
                collection.where = [
                    {
                        attribute: 'status',
                        type: 'in',
                        value: ['Running', 'Pending']
                    },
                    {
                        attribute: 'executeTime',
                        type: 'past',
                        dateTime: true,
                        timeZone: 'UTC'
                    }
                ];
                this.listenToOnce(collection, 'sync', () => {
                    this.createView('list', 'views/record/list', {
                        el: this.options.el + ' .list-container',
                        collection: collection,
                        rowActionsDisabled: true,
                        checkboxes: false,
                        headerDisabled: true,
                        layoutName: 'listInQueueManager'
                    }, view => {
                        view.render();
                        this.qmInterval = window.setInterval(() => {
                            collection.fetch();
                        }, 2000)
                    });
                });
                collection.fetch();
            });
        },

        initProgressBadge() {
            if (this.getAcl().check('Job', 'read')) {

                window.addEventListener('qmPanelClosed', () => {
                    if (this.qmInterval) {
                        window.clearInterval(this.qmInterval);
                    }
                });

                new Svelte.QueueManagerIcon({
                    target: this.$el.find('.navbar-header .queue-badge-container').get(0),
                    props: {
                        renderTable: () => {
                            this.renderQmPanelList();
                        }
                    }
                });

                new Svelte.QueueManagerIcon({
                    target: this.$el.find('.navbar-right .queue-badge-container.hidden-xs').get(0),
                    props: {
                        renderTable: () => {
                            this.renderQmPanelList();
                        }
                    }
                });
            }
        },

        getMenuDataList: function () {
            let menuDefs = Dep.prototype.getMenuDataList.call(this) || [];

            return menuDefs.filter(item => item.link !== '#About');
        },

        setupTabDefsList: function () {
            Dep.prototype.setupTabDefsList.call(this);

            this.tabDefsList.forEach(tab => {
                if (!tab.iconClass) {
                    tab.colorIconClass = 'color-icon fas fa-stop';
                }
            });
        },

    });

});


