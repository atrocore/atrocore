/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
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
                userName: this.getUser().get('name'),
                userId: this.getUser().id,
                logoSrc: this.getLogoSrc(),
                navbarIsVertical: this.getThemeManager().getParam('navbarIsVertical'),
                showBookmarked: true,
                canConfigureMenu: this.getUser().isAdmin()  && this.getPreferences().get('layoutProfileId')
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
            $('a[data-action="composerUpdate"]').parent().addClass('disabled');
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
            this.tabDefsList = this.tabList.map(tab => this.getTabDefs(tab));
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

            let colorsDisabled =
                this.getPreferences().get('scopeColorsDisabled') ||
                this.getPreferences().get('tabColorsDisabled') ||
                this.getConfig().get('scopeColorsDisabled') ||
                this.getConfig().get('tabColorsDisabled');
            let tabIconsDisabled = this.getConfig().get('tabIconsDisabled');
            if (Espo.Utils.isObject(tab)) {
                if (!colorsDisabled) {
                    color = tab.color || 'inherit';
                }
                if (!tabIconsDisabled) {
                    iconClass = tab.iconClass;
                }
                id = tab.id;
                group = true;
                name = tab.name;
                translateCategory = 'navMenuDelimiters';
                items = tab.items.map(item => this.getTabDefs(item));
                link = 'javascript:';
            } else {
                if (!colorsDisabled) {
                    color = this.getMetadata().get(['clientDefs', tab, 'color']) || 'inherit';
                }
                if (!tabIconsDisabled) {
                    iconClass = this.getMetadata().get(['clientDefs', tab, 'iconClass']);
                }
                id = tab;
                group = false;
                name = tab;
                translateCategory = 'scopeNamesPlural';
                items = [];
                link = `#${tab}`;
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
                color: color,
                iconClass: iconClass
            };
            if (!iconClass && result.name) {
                result.iconSrc = this.getDefaultTabIcon(result.name);
            }
            return result;
        },

        getDefaultTabIcon(name) {
            let firstLetter = name.match(/\p{L}/u)?.[0] || "A",
                referenceData = this.getConfig().get('referenceData');

            if (referenceData['SystemIcon']) {
                let key = 'letter_' + firstLetter.toLowerCase();

                if (key in referenceData['SystemIcon']) {
                    let item = referenceData['SystemIcon'][key];

                    if ('imagePathsData' in item && 'thumbnails' in item['imagePathsData']) {
                        for (let size in item['imagePathsData']['thumbnails']) {
                            return item['imagePathsData']['thumbnails'][size];
                        }
                    }
                }
            }

            return null;
        },

        getMenuDataList: function () {
            var avatarHtml = this.getHelper().getAvatarHtml(this.getUser().id, 'small', 16, 'avatar-link');
            if (avatarHtml) avatarHtml += ' ';

            var list = [
                {
                    link: '#UserProfile',
                    html: avatarHtml + this.getUser().get('name')
                },
                {
                    divider: true
                }
            ];

            if (this.getUser().isAdmin()) {
                list.push({
                    link: '#Admin',
                    label: this.getLanguage().translate('Administration')
                });
            }

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
            if(scope === 'File') {
                this.notify('Loading...');
                this.createView('upload', 'views/file/modals/upload', {
                    scope: 'File',
                    fullFormDisabled: true,
                    layoutName: 'upload',
                    multiUpload: true,
                    attributes: {
                        scope: scope
                    },
                }, view => {
                    view.once('after:render', () => {
                        this.notify(false);
                    });
                    view.render();
                });
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


