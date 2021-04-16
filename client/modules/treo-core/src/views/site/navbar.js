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

                var minHeight = $tabs.height() + navbarStaticItemsHeight;

                var $more = $tabs.find('li.more > ul');

                if ($more.children().size() === 0) {
                    $more.parent().addClass('hidden');
                }

                $('body').css('minHeight', minHeight + 'px');

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

        initProgressBadge() {
            if (this.getAcl().check('QueueItem', 'read')) {
                this.$el.find('.navbar-header').find('.notifications-badge-container').before('<li class="dropdown queue-badge-container"></li>');
                this.createView('queueBadgeHeader', 'treo-core:views/queue-manager/badge', {
                    el: `${this.options.el} .navbar-header .queue-badge-container`,
                    intervalConditions: [
                        () => {
                            return $(window).innerWidth() < 768;
                        }
                    ]
                }, view => {
                    view.render();
                });

                this.$el.find('.navbar-right').find('.notifications-badge-container').before('<li class="dropdown queue-badge-container hidden-xs"></li>');
                this.createView('queueBadgeRight', 'treo-core:views/queue-manager/badge', {
                    el: `${this.options.el} .navbar-right .queue-badge-container`,
                    intervalConditions: [
                        () => {
                            return $(window).innerWidth() >= 768;
                        }
                    ]
                }, view => {
                    view.render();
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


