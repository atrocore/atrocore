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

        favoritesList: [],

        openMenu: function () {
            this.events = _.extend({}, this.events || {}, {
                'click .search-toggle': function () {
                    this.$el.find('.navbar-collapse ').toggleClass('open-search');
                },

                'click [data-action="configureFavorites"]': function (e) {
                    this.createView('favoritesEdit', 'views/layout-profile/modals/favorites', {
                        field: 'favoritesList',
                        model: this.getPreferences()
                    }, view => {
                        this.notify(false)
                        view.render();
                    });
                }
            });
        },

        data() {
            return _.extend({
                hasJM: this.getAcl().check('Job', 'read'),
                isMoreFields: this.isMoreFields,
                lastViewed: !this.getConfig().get('actionHistoryDisabled'),
                favoritesList: this.favoritesList || [],
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

            var scopes = this.getMetadata().get('scopes') || {};

            let checkScope = function (item) {
                if (typeof scopes[item] === 'undefined' && typeof item !== 'object') {
                    return false;
                }
                if ((scopes[item] || {}).disabled) {
                    return false;
                }
                if ((scopes[item] || {}).acl) {
                    return this.getAcl().check(item, 'read');
                }
                return true;
            };

            this.tabList = tabList.filter(function (scope) {
                if (typeof scope === 'object') {
                    scope.items = scope.items.filter(checkScope, this);
                    if (!scope.items.length) {
                        return false
                    }
                }
                return checkScope.call(this, scope);
            }, this);


            this.createView('notificationsBadge', 'views/notification/badge', {
                el: this.options.el + ' .notifications-badge-container',
                intervalConditions: [
                    () => {
                        return $(window).innerWidth() < 768;
                    }
                ]
            });

            this.createView('footer', 'views/site/navbar-footer', {
                el: `${this.options.el} footer`
            })

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

            this.setupFavoritesList();

            this.setupBookmark();

            this.once('remove', function () {
                $(window).off('resize.navbar');
                $(window).off('scroll.navbar');
            });

            this.events = _.extend({
                'click .more-group-name': function (e) {
                    let a = $(e.currentTarget);
                    let moreGroup = a.parent();
                    if (!moreGroup.hasClass('keep-open')) {
                        this.$el.find('ul.tabs .keep-open').removeClass('keep-open');
                        moreGroup.addClass('keep-open');
                    } else {
                        if(!moreGroup.hasClass('open')) {
                            moreGroup.addClass('open');
                        }
                        this.$el.find('ul.tabs .keep-open').removeClass('keep-open');
                    }
                }
            }, this.events);

            this.openMenu();
        },

        getFavoritesList: function () {
            return this.getConfig().get('favoritesList') || [];
        },

        setupFavoritesList: function () {
            this.favoritesList = this.getFavoritesList().filter(tab => this.getAcl().checkScope(tab, 'read')).map(tab => this.getTabDefs(tab));
        },

        adjust: function () {
            var $window = $(window);

            var navbarIsVertical = this.getThemeManager().getParam('navbarIsVertical');
            var navbarStaticItemsHeight = this.getThemeManager().getParam('navbarStaticItemsHeight') || 0;

            var smallScreenWidth = this.getThemeManager().getParam('screenWidthXs');

            if (!navbarIsVertical) {
                var $tabs = this.$el.find('ul.tabs');
                var $moreDropdown = $tabs.find('li.more').last();
                var $more = $tabs.find('li.more > ul').last();

                $window.on('resize.navbar', function() {
                    updateWidth();
                });

                var hideOneTab = function () {
                    var count = $tabs.children().size();
                    var $one = $tabs.children().eq(count - 2);
                    $one.prependTo($more);

                    if ($one.hasClass('more-group')) {
                        $one.children('a').hide();
                        $one.children('ul').addClass('nested-dropdown');
                    }
                };
                var unhideOneTab = function () {
                    var $one = $more.children().eq(0);
                    if ($one.size()) {
                        $one.insertBefore($moreDropdown);

                        if ($one.hasClass('more-group')) {
                            $one.children('a').show();
                            $one.children('ul').removeClass('nested-dropdown');
                        }
                    }
                };

                var tabCount = this.tabList.length;
                var $navbar = $('#navbar .navbar');
                var navbarNeededHeight = (this.getThemeManager().getParam('navbarHeight') || 44) + 1;

                $moreDd = $('#nav-more-tabs-dropdown');

                var navbarBaseWidth = this.getThemeManager().getParam('navbarBaseWidth') || 546;

                var updateWidth = function () {
                    var windowWidth = $(window.document).width();
                    var windowWidth = window.innerWidth;
                    var moreWidth = $moreDd.width();

                    $more.children('li.not-in-more').each(function (i, li) {
                        unhideOneTab();
                    });

                    if (windowWidth < smallScreenWidth) {
                        if ($more.children().size()) {
                            $moreDropdown.removeClass('hidden');
                        } else {
                            $moreDropdown.addClass('hidden');
                        }
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

                var processUpdateWidth = function () {
                    if ($navbar.height() > navbarNeededHeight) {
                        updateWidth();
                        setTimeout(function () {
                            processUpdateWidth();
                        }, 200);
                    } else {
                        setTimeout(function () {
                            processUpdateWidth();
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

                $more.each((index, elem) => {
                    if ($(elem).children().size() === 0) {
                        $(elem).parent().addClass('hidden');
                    }
                });

                $('body').css('minHeight', minHeight + 'px');

                var updateSizeForVertical = function () {
                    var windowHeight = window.innerHeight;
                    var windowWidth = window.innerWidth;

                    if (windowWidth < smallScreenWidth) {
                        $tabs.css('height', 'auto');
                        $more.css('max-height', '');
                    } else {
                        let tabsHeight = windowHeight - navbarStaticItemsHeight;
                        if (!$('body').hasClass('minimized')) {
                            tabsHeight += 28;
                        }

                        $tabs.css('height', tabsHeight - 28 + 'px');
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


