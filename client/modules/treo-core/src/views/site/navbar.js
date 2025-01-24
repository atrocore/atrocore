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
                'click .navbar-toggle': function (e) {
                    if (window.innerWidth > 768) {
                        return;
                    }
                    if (this.$el.find('.menu').hasClass('open-menu')) {
                        $(document).off('mouseup.menu');
                        this.$el.find('.menu').removeClass('open-menu');
                    } else {
                        this.$el.find('.menu').addClass('open-menu');
                        $(document).on('mouseup.menu', function (e) {
                            if (!$(e.target).closest('.navbar .menu').length && !$(e.target).closest('.navbar-toggle').length) {
                                this.$el.find('.menu').removeClass('open-menu');
                            }
                        }.bind(this));
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

            this.setupBookmark();

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
                collection.sortBy = 'priority';
                collection.asc = false;
                collection.where = [{type: 'bool', value: ['jobManagerItems']}];
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
                        this.jmInterval = window.setInterval(() => {
                            collection.fetch();
                        }, 2000)
                    });
                });
                collection.fetch();
            });
        },

        initProgressBadge() {
            if (this.getAcl().check('Job', 'read')) {

                window.addEventListener('jobManagerPanelClosed', () => {
                    if (this.jmInterval) {
                        window.clearInterval(this.jmInterval);
                    }
                });

                new Svelte.JobManagerIcon({
                    target: this.$el.find('.navbar-header .queue-badge-container').get(0),
                    props: {
                        renderTable: () => {
                            this.renderQmPanelList();
                        }
                    }
                });

                new Svelte.JobManagerIcon({
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

        setupBookmark: function() {
            this.createView('bookmarkBadge', 'views/bookmark/badge', {
                el: this.options.el + ' .bookmark-badge-container'
            });
        }

    });

});


