/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/site/navbar', ['view', 'color-converter'], function (Dep, ColorConverter) {

    return Dep.extend({

        template: 'site/navbar',

        currentTab: null,

        data: function () {
            return {
                tabDefsList: this.tabDefsList,
                title: this.options.title,
                menuDataList: this.getMenuDataList(),
                userName: this.getUser().get('name'),
                userId: this.getUser().id,
                logoSrc: this.getLogoSrc(),
                navbarIsVertical: this.getThemeManager().getParam('navbarIsVertical'),
                showBookmarked: true,
                canConfigureMenu: this.getUser().isAdmin()  && this.getPreferences().get('layoutProfileId'),
                showIcon: !this.getConfig().get('tabIconsDisabled')
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
            'click [data-action="quickCreate"]': function (e) {
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
            },
            'click [data-action="configureMenu"]': function(e) {
                if(!this.getUser().isAdmin()) {
                    return;
                }
                this.notify('Loading');
                this.getModelFactory().create('LayoutProfile', (model) => {
                    model.id = this.getPreferences().get('layoutProfileId');
                    model.fetch().success(() => {
                        this.notify(false);
                        this.createView('edit', 'views/layout-profile/modals/navigation', {
                            field: 'navigation',
                            model: model
                        }, view => {
                            view.render();
                            this.listenTo(model, 'sync', () => {
                                this.getPreferences().set('ldNavigation', this.tabList =  model.get('navigation'));
                                this.setup();
                                this.reRender();
                            });
                        });
                    });
                })
            },
            'click .navbar-toggle': function (e) {
                if (this.$el.find('.menu').hasClass('open-menu')) {
                    if(this.menuShouldBeOpen) {
                        return;
                    }
                    this.$el.find('.menu').removeClass('open-menu');
                }

                if(this.$el.find('.menu').hasClass('not-collapsed')){
                    this.$el.find('.menu').addClass('open-menu');
                    this.$el.find('.menu').removeClass('not-collapsed');
                    this.menuShouldBeOpen = false;
                }
            },
            'mouseenter .favorites-items .nav-link': function (e) {
                const createIcon = $(e.currentTarget).find('.label-wrapper .plus-icon');

                if (createIcon.length) {
                    $(e.currentTarget).find('.label-wrapper img').addClass('hidden');
                    createIcon.removeClass('hidden');
                }
            },
            'mouseleave .favorites-items .nav-link ': function (e) {
                const createIcon = $(e.currentTarget).find('.label-wrapper .plus-icon');
                if (createIcon.length) {
                    $(e.currentTarget).find('.label-wrapper img').removeClass('hidden');
                    createIcon.addClass('hidden');
                }
            },
            'click .favorites-items [data-action="quickFavCreate"]': function(e){
                e.preventDefault();
                let data = $(e.currentTarget).data();
                if(data.name === 'File') {
                    this.quickCreateFile();
                    return;
                }
                this.getRouter().navigate(`#${data.name}/create/new`, {trigger: true});
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
                       this.menuShouldBeOpen = false;
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
            if(this.tabList) {
                return this.tabList;
            }
            return this.tabList = this.getPreferences().get('lpNavigation') || [];
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
        },

        selectTab: function (name) {
            if (this.currentTab !== name) {
                this.$el.find('ul.tabs li.active').removeClass('active');
                this.$el.find('ul.favorites-items li .favorite.active').removeClass('active');
                if (name) {
                    this.$el.find(`ul.tabs  li[data-name="${name}"]`).addClass('active');
                    this.$el.find(`ul.favorites-items li[data-name="${name}"] .favorite`).addClass('active');
                }
                this.currentTab = name;
            }

            this.$el.find('ul.tabs .keep-open').removeClass('keep-open');
            this.$el.find('ul.tabs .highlighted').removeClass('highlighted');
            if (this.getThemeManager().getParam('navbarIsVertical')) {
                this.$el.find(`ul.tabs li.in-more[data-name="${name}"]`).parents('.more-group').addClass('open keep-open highlighted');
            }
        },

        setupTabDefsList: function () {
            this.tabDefsList = this.tabList.map(tab => this.getTabDefs(tab)).filter(item => item != null);
        },

        getTabDefs(tab) {
            let result ={};
            let id;
            let group;
            let name;
            let translateCategory;
            let items;
            let link;
            let color;
            let iconClass;
            let createDisabled;

            let colorsDisabled =
                this.getPreferences().get('scopeColorsDisabled') ||
                this.getConfig().get('scopeColorsDisabled')
            if (Espo.Utils.isObject(tab)) {
                if (!colorsDisabled) {
                    color = tab.color || null;
                }
                iconClass = tab.iconClass;
                id = tab.id;
                group = true;
                name = tab.name;
                translateCategory = 'navMenuDelimiters';
                items = tab.items.map(item => this.getTabDefs(item)).filter(item => item != null);
                link = 'javascript:';
            } else {
                if (!this.getAcl().check(tab, 'read')){
                    return null;
                }
                iconClass = this.getMetadata().get(['clientDefs', tab, 'iconClass']);
                id = tab;
                group = false;
                name = tab;
                translateCategory = 'scopeNamesPlural';
                items = [];
                link = `#${tab}`;

                if (!this.getAcl().check(tab, 'create') || this.getMetadata().get(['clientDefs', tab, 'createDisabled'])) {
                    createDisabled = true;
                }
            }
            let label = this.getLanguage().translate(name, translateCategory);
            result = {
                id: id,
                link: link,
                label: label,
                shortLabel: label.substr(0, 2),
                name: name,
                items: items,
                group: group,
                createDisabled: createDisabled,
            };

            if (iconClass) {
                const systemIcons = this.getMetadata().get(['app', 'systemIcons']) || [];

                if (systemIcons && iconClass in systemIcons) {
                    const path = systemIcons[iconClass].path || null;

                    if (path) {
                        result.iconSrc = systemIcons[iconClass].path;
                    }
                }
            }

            if (!result.iconSrc && result.name) {
                result.defaultIconSrc = this.getDefaultTabIcon(result.name);
            }

            let filter = null;
            if (this.getSessionStorage().get('navigationIconColor')) {
                filter = this.getSessionStorage().get('navigationIconColor');
            } else {
                let style = this.getThemeManager().getStyle();

                if (style && style['navigationMenuFontColor']) {
                    let colorConverter = new ColorConverter(style['navigationMenuFontColor']);

                    filter = colorConverter.solve().filter;
                    this.getSessionStorage().set('navigationIconColor', filter)
                }
            }

            result.colorFilter = filter;

            if (tab === 'Dashboard') {
                result.link = '#';
                result.name = 'Home';
                result.createDisabled = true;
            }

            return result;
        },

        getMenuDataList: function () {
            let avatarHtml = this.getHelper().getAvatarHtml(this.getUser().id, 'small', 20, 'avatar-link');

            const list = [
                {
                    link: '#UserProfile',
                    html: avatarHtml + this.getUser().get('name')
                },
                {
                    link: '#clearCache',
                    label: this.getLanguage().translate('Clear Local Cache'),
                    icon: '<i class="ph ph-devices"></i>'
                },
                {
                    link: '#logout',
                    label: this.getLanguage().translate('Log Out'),
                    icon: '<i class="ph ph-sign-out"></i>'
                }
            ];

            if (this.getUser().isAdmin()) {
                list.push({
                    divider: true
                });

                list.push({
                    link: '#Admin',
                    label: this.getLanguage().translate('Administration'),
                    icon: '<i class="ph ph-gear"></i>'
                });

                const systemCacheEntry = {
                    action: 'clearSystemCache',
                    label: this.getLanguage().translate('Clear Cache', 'labels', 'Admin'),
                    icon: '<i class="ph ph-database"></i>',
                };

                if (Espo?.loader?.cacheTimestamp) {
                    const date = moment.unix(Espo.loader.cacheTimestamp).tz(this.getDateTime().getTimeZone()).format(this.getDateTime().getDateTimeFormat());
                    systemCacheEntry.title = this.getLanguage().translate('clearCacheTooltip', 'labels', 'Admin') + ' ' + date;
                }

                list.push(systemCacheEntry);

                list.push({
                    action: 'rebuildDatabase',
                    label: this.getLanguage().translate('rebuildDb', 'labels', 'Admin'),
                    icon: '<i class="ph ph-wrench"></i>'
                });

                list.push({
                    action: 'composerUpdate',
                    label: this.getLanguage().translate('Run Update', 'labels', 'Composer'),
                    icon: '<i class="ph ph-cloud-arrow-down"></i>'
                });
            }

            list.push({
                divider: true
            });

            list.push({
                action: 'openFeedbackModal',
                label: this.getLanguage().translate('Provide Feedback'),
                icon: '<i class="ph ph-chat-text"></i>'
            });

            list.push({
                link: 'https://community.atrocore.com/c/issues/8',
                label: this.getLanguage().translate('Report a bug'),
                targetBlank: true,
                icon: '<i class="ph ph-bug-beetle"></i>'
            });

            list.push({
                link: 'https://community.atrocore.com',
                label: this.getLanguage().translate('Visit Community'),
                targetBlank: true,
                icon: '<i class="ph ph-users-three"></i>'
            });

            list.push({
                link: 'https://help.atrocore.com',
                label: this.getLanguage().translate('Help Center'),
                targetBlank: true,
                icon: '<i class="ph ph-lifebuoy"></i>'
            });

            setInterval(() => {
                const $composerLi = $('a[data-action="composerUpdate"]').parent();
                const $rebuildLi = $('a[data-action="rebuildDatabase"]').parent();
                const isNeedToUpdate = localStorage.getItem('pd_isNeedToUpdate') || false;
                const isNeedToRebuildDb = localStorage.getItem('pd_isNeedToRebuildDatabase') || false;
                if (isNeedToUpdate === 'true') {
                    $composerLi.removeClass('hidden');
                } else {
                    $composerLi.addClass('hidden');
                }

                if (isNeedToRebuildDb === 'true') {
                    $rebuildLi.removeClass('hidden');
                } else {
                    $rebuildLi.addClass('hidden');
                }
            }, 1000);

            return list;
        },

        quickCreate: function (scope) {
            Espo.Ui.notify(this.translate('Loading...'));
            if(scope === 'File') {
                this.quickCreateFile();
                return;
            }
            var type = this.getMetadata().get(['clientDefs', scope, 'quickCreateModalType']) || 'edit';
            var viewName = this.getMetadata().get(['clientDefs', scope, 'modalViews', type]) || 'views/modals/edit';
            this.createView('quickCreate', viewName, {scope: scope}, function (view) {
                view.once('after:render', function () {
                    Espo.Ui.notify(false);
                });
                view.render();
            });
        },

        quickCreateFile: function() {
            this.notify('Loading...');
            this.createView('upload', 'views/file/modals/upload', {
                scope: 'File',
                fullFormDisabled: true,
                layoutName: 'upload',
                multiUpload: true,
                attributes: {
                    scope: 'File'
                },
            }, view => {
                view.once('after:render', () => {
                    this.notify(false);
                });
                view.render();
            });
        },

        actionClearSystemCache: function () {
            Espo.Ui.notify(this.getLanguage().translate('Please wait...'));

            this.ajaxPostRequest('Admin/clearCache').success(response => {
                Espo.Ui.success(this.getLanguage().translate('Cache has been cleared', 'labels', 'Admin'));
            });
        },

        actionRebuildDatabase: function () {
            this.createView('rebuild-db', 'views/modals/rebuild-database', {}, view => view.render());
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


