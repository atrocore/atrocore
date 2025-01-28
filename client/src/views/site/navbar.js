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

Espo.define('views/site/navbar', 'view', function (Dep) {

    return Dep.extend({

        template: 'site/navbar',

        currentTab: null,

        data: function () {
            return {
                tabDefsList: this.tabDefsList,
                title: this.options.title,
                menuDataList: this.getMenuDataList(),
                quickCreateList: this.quickCreateList,
                enableQuickCreate: this.quickCreateList.length > 0,
                userName: this.getUser().get('name'),
                userId: this.getUser().id,
                logoSrc: this.getLogoSrc(),
                hideFeedbackIcon: !!this.getPreferences().get('hideFeedbackIcon'),
                showBookmarked: true
            };
        },

        events: {
            'mouseover .menu:not(.menu-open)': function(e) {
                e.preventDefault();
                this.menuShouldBeOpen = true;
                if(!this.$el.find('.menu').hasClass('open-menu') && !this.$el.find('.menu').hasClass('not-collapsed')){
                    return;
                }
                this.handleMenuVisibility(e);
            },
            'mouseover .navbar-toggle': function(e){
                e.preventDefault();
                this.menuShouldBeOpen = true;
                this.handleMenuVisibility(e);
            },
            'mouseleave .menu': function(e) {
                this.handleMouseLeave(e);
            },
            'mouseleave .navbar-toggle': function(e) {
                this.handleMouseLeave(e);
            },
            'click .menu.open-menu a.nav-link': function (e) {
                var $a = $(e.currentTarget);
                var href = $a.attr('href');
                if (href && href != '#') {
                    this.$el.find('.menu').removeClass('open-menu');
                }
            },
            'click .navbar-collapse.in a.nav-link': function (e) {
                var $a = $(e.currentTarget);
                var href = $a.attr('href');
                if (href && href != '#') {
                    this.$el.find('.navbar-collapse.in').collapse('hide');
                }
            },
            'click a[data-action="quick-create"]': function (e) {
                e.preventDefault();
                var scope = $(e.currentTarget).data('name');
                this.quickCreate(scope);
            },
            'click a.action': function (e) {
                var $el = $(e.currentTarget);

                var action = $el.data('action');
                var method = 'action' + Espo.Utils.upperCaseFirst(action);
                if (typeof this[method] == 'function') {
                    var data = $el.data();
                    this[method](data, e);
                    e.preventDefault();
                }
            },
            'transitionend .menu' : function(e) {
                if(e.originalEvent.propertyName === 'transform' && !this.$el.find('.menu').hasClass('open-menu')) {
                    this.$el.find('.menu').addClass('not-collapsed');
                    this.$el.find('.menu').off('transitionend');
                }
            }
        },

        handleMenuVisibility(e) {
            if (window.innerWidth <= 768) {
                return;
            }
            if(!this.$el.find('.menu').hasClass('open-menu')) {
                this.menuShouldBeOpen = true;
               setTimeout(() => {
                   if(this.menuShouldBeOpen) {
                       this.$el.find('.menu').addClass('open-menu');
                       this.$el.find('.menu').removeClass('not-collapsed');
                   }
               }, 500)
            }
        },

        handleMouseLeave (e) {
            e.preventDefault();
            this.menuShouldBeOpen = false;
            setTimeout(() => {
                if(!this.menuShouldBeOpen) {
                    this.$el.find('.menu').removeClass('open-menu');
                }
            },500)
        },

        getLogoSrc: function () {
            var companyLogoId = this.getConfig().get('companyLogoId');
            if (!companyLogoId) {
                return this.getBasePath() + (this.getThemeManager().getParam('logo') || 'client/img/logo.png');
            }
            return this.getBasePath() + '?entryPoint=LogoImage&t=' + companyLogoId;
        },

        getTabList: function () {
            var tabList = this.getPreferences().get('useCustomTabList') ? this.getPreferences().get('tabList') : this.getConfig().get('tabList');
            return tabList || [];
        },

        getQuickCreateList: function () {
            return this.getConfig().get('quickCreateList') || [];
        },

        setup: function () {
            this.getRouter().on('routed', function (e) {
                if (e.controller) {
                    this.selectTab(e.controller);
                } else {
                    this.selectTab(false);
                }
            }.bind(this));

            var tabList = this.getTabList();

            var scopes = this.getMetadata().get('scopes') || {};

            this.tabList = tabList.filter(function (scope) {
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
                el: this.options.el + ' .notifications-badge-container'
            });


            this.setupGlobalSearch();


            this.setupTabDefsList();

            this.once('remove', function () {
                $(window).off('resize.navbar');
                $(window).off('scroll.navbar');
            });
        },

        setupGlobalSearch: function () {
            this.globalSearchAvailable = false;
            (this.getConfig().get('globalSearchEntityList') || []).forEach(function (scope) {
                if (this.globalSearchAvailable) return;
                if (this.getAcl().checkScope(scope)) {
                    this.globalSearchAvailable = true;
                }
            }, this);

            if (this.globalSearchAvailable) {
                this.createView('globalSearch', 'views/global-search/global-search', {
                    el: this.options.el + ' .global-search-container'
                });
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

                $window.on('scroll.navbar', function () {
                    $tabs.scrollTop($window.scrollTop());
                    $more.scrollTop($window.scrollTop());
                }.bind(this));

                var updateSizeForVertical = function () {
                    var windowHeight = window.innerHeight;
                    var windowWidth = window.innerWidth;

                    if (windowWidth < smallScreenWidth) {
                        $tabs.css('height', 'auto');
                        $more.css('max-height', '');
                    } else {
                        $tabs.css('height', (windowHeight - navbarStaticItemsHeight) + 'px');
                        $more.css('max-height', windowHeight + 'px');
                    }

                }.bind(this);

                $(window).on('resize.navbar', function() {
                    updateSizeForVertical();
                });
                updateSizeForVertical();
            }
        },

        afterRender: function () {
            this.selectTab(this.getRouter().getLast().controller);

            var layoutState = this.getStorage().get('state', 'siteLayoutState');
            var layoutMinimized = false;
            if (layoutState === 'collapsed') {
                layoutMinimized = true;
            }

            if (layoutMinimized) {
                var $body = $('body');
                $body.addClass('minimized');
            }
            this.$navbar = this.$el.find('> .navbar');

            if (this.getThemeManager().getParam('navbarIsVertical')) {
                var process = function () {
                    if (this.$navbar.height() < $(window).height() / 2) {
                        setTimeout(function () {
                            process();
                        }.bind(this), 50);
                        return;
                    }
                    if (this.getThemeManager().isUserTheme()) {
                        setTimeout(function () {
                            this.adjust();
                        }.bind(this), 10);
                        return;
                    }
                    this.adjust();
                }.bind(this);
                process();
            } else {
                var process = function () {
                    if (this.$el.width() < $(window).width() / 2) {
                        setTimeout(function () {
                            process();
                        }.bind(this), 50);
                        return;
                    }
                    if (this.getThemeManager().isUserTheme()) {
                        setTimeout(function () {
                            this.adjust();
                        }.bind(this), 10);
                        return;
                    }
                    this.adjust();
                }.bind(this);
                process();
            }
            $('a[data-action="composerUpdate"]').parent().addClass('disabled');
        },

        selectTab: function (name) {
            if (this.currentTab != name) {
                this.$el.find('ul.tabs li.active').removeClass('active');
                if (name) {
                    this.$el.find('ul.tabs  li[data-name="' + name + '"]').addClass('active');
                }
                this.currentTab = name;
            }
        },

        setupTabDefsList: function () {
            var tabDefsList = [];
            var moreIsMet = false;
            var colorsDisabled = false;
            var tabIconsDisabled = this.getConfig().get('tabIconsDisabled');

            this.tabList.forEach(function (tab, i) {
                if (tab === '_delimiter_') {
                    moreIsMet = true;
                    return;
                }
                var label = this.getLanguage().translate(tab, 'scopeNamesPlural');
                var color = null;
                if (!colorsDisabled) {
                    var color = this.getMetadata().get(['clientDefs', tab, 'color']);
                }

                var shortLabel = label.substr(0, 2);

                var iconClass = null;
                if (!tabIconsDisabled) {
                    iconClass = this.getMetadata().get(['clientDefs', tab, 'iconClass'])
                }

                var o = {
                    link: '#' + tab,
                    label: label,
                    shortLabel: shortLabel,
                    name: tab,
                    isInMore: moreIsMet,
                    color: color,
                    iconClass: iconClass
                };
                if (color && !iconClass) {
                    o.colorIconClass = 'color-icon fas fa-square-full';
                }
                tabDefsList.push(o);
            }, this);
            this.tabDefsList = tabDefsList;
        },

        getMenuDataList: function () {
            var avatarHtml = this.getHelper().getAvatarHtml(this.getUser().id, 'small', 16, 'avatar-link');
            if (avatarHtml) avatarHtml += ' ';

            var list = [
                {
                    link: '#User/view/' + this.getUser().id,
                    html: avatarHtml + this.getUser().get('name')
                },
                {divider: true}
            ];

            if (this.getUser().isAdmin()) {
                list.push({
                    link: '#Admin',
                    label: this.getLanguage().translate('Administration')
                });
            }

            list.push({
                link: '#Preferences',
                label: this.getLanguage().translate('Preferences')
            });

            if (this.getUser().isAdmin()) {
                list.push({
                    divider: true
                });

                list.push({
                    action: 'composerUpdate',
                    html: this.getLanguage().translate('Run Update', 'labels', 'Composer') + ' <span id="composer-update" class="fas fa-arrow-alt-circle-down"></span>'
                });
            }

            list.push({
                divider: true
            });

            list.push({
                link: '#clearCache',
                label: this.getLanguage().translate('Clear Local Cache')
            });

            list.push({
                divider: true
            });

            list.push({
                action: 'openFeedbackModal',
                label: this.getLanguage().translate('Provide Feedback')
            });

            list.push({
                link: 'https://community.atrocore.com/c/issues/8',
                label: this.getLanguage().translate('Report a bug'),
                targetBlank: true
            });

            list.push({
                link: 'https://community.atrocore.com',
                label: this.getLanguage().translate('Visit Community'),
                targetBlank: true
            });

            list.push({
                divider: true
            });

            list.push({
                link: '#logout',
                label: this.getLanguage().translate('Log Out')
            });

            setInterval(() => {
                let $composerLi = $('a[data-action="composerUpdate"]').parent();
                let isNeedToUpdate = localStorage.getItem('pd_isNeedToUpdate') || false;
                if (isNeedToUpdate === 'true') {
                    $composerLi.removeClass('disabled');
                } else {
                    if (!$composerLi.hasClass('disabled')) {
                        $composerLi.addClass('disabled');
                    }
                }
            }, 1000);

            return list;
        },

        quickCreate: function (scope) {
            Espo.Ui.notify(this.translate('Loading...'));
            var type = this.getMetadata().get(['clientDefs', scope, 'quickCreateModalType']) || 'edit';
            var viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', type]) || 'views/modals/edit';
            this.createView('quickCreate', viewName, {scope: scope}, function (view) {
                view.once('after:render', function () {
                    Espo.Ui.notify(false);
                });
                view.render();
            });
        },

        actionShowHistory: function () {
            this.createView('dialog', 'views/modals/action-history', {}, function (view) {
                view.render();
                this.listenTo(view, 'close', function () {
                    this.clearView('dialog');
                }, this);
            }, this);
        },

        actionComposerUpdate(data, el) {
            if ($(el.currentTarget).parent().hasClass('disabled')) {
                return false;
            }

            this.confirm({
                message: this.translate('confirmRun', 'labels', 'Composer'),
                confirmText: this.translate('Run Update', 'labels', 'Composer')
            }, () => {
                this.notify(this.translate('updating', 'labels', 'Composer'));
                this.ajaxPostRequest('Composer/runUpdate', {}, {timeout: 180000}).then(response => {
                    this.notify(this.translate('updateStarted', 'labels', 'Composer'), 'success');
                    location.reload();
                }, error => {
                    location.reload();
                });
            });
        },
        actionOpenFeedbackModal(data, el){
            this.notify('Loading...')
            this.createView('feedbackDialog','views/modals/feedback',{}, view => {
                view.render()
                setTimeout(() => this.notify(false),2000)
            })
        }

    });

});


