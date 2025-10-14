/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-profile/fields/style', ['views/fields/link', 'treo-core:views/site/master'], (Dep, Master) => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, `change:${this.name + 'Id'}`, () => {
               this.updateStylesAfterChange();
            });

            this.listenTo(this.model, 'after:save after:inlineEditSave', () => {
               this.updateStylesAfterSave();
            });
        },

        reloadStyle(style) {
            let master = new Master();
            if (style) {
                master.initStyleVariables(style);

                this.navFilter = null;
                this.toolbarFilter = null;

                if (style?.navigationMenuFontColor) {
                    this.navFilter = master.getIconFilter(style?.navigationMenuFontColor);
                }

                if (style?.toolbarFontColor) {
                    this.toolbarFilter = master.getIconFilter(style?.toolbarFontColor);
                }

                if (this.navFilter) {
                    $(".short-label img[src^=\"client/img/icons\"]").css('filter', this.navFilter.replace("filter:", '').replace(';', ''));
                } else {
                    $(".short-label img[src^=\"client/img/icons\"]").css('filter', '');
                }

                if (this.toolbarFilter) {
                    $(".label-wrapper img[src^=\"client/img/icons\"]").css('filter', this.toolbarFilter.replace("filter:", '').replace(';', ''));
                } else {
                    $(".label-wrapper img[src^=\"client/img/icons\"]").css('filter', '');
                }

                if (!this.getConfig().get('companyLogoId')) {
                    if (style.logo) {
                        $('.navbar-brand img').attr('src', style.logo);
                    } else {
                        $('.navbar-brand img').attr('src', 'client/modules/treo-core/img/core_logo_dark.svg');
                    }
                }
            } else {
                master.removeStyleVariables();

                if (!this.getConfig().get('companyLogoId')) {
                    $('.navbar-brand img').attr('src', 'client/modules/treo-core/img/core_logo_dark.svg');
                }

                $(".label-wrapper img[src^=\"client/img/icons\"], .short-label img[src^=\"client/img/icons\"]").css('filter', '');

                this.getSessionStorage().clear('navigationIconColor');
                this.getSessionStorage().clear('toolbarIconColor');
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.shouldHide()) {
                this.hide();
            }
        },

        getStyle(id = null) {
            if(!id) {
                return this.getThemeManager().getStyle();
            }

            let styles = this.getConfig().get('referenceData')?.Style ||  {};

            for (const key in styles) {
                if(styles[key].id === id) {
                    return styles[key];
                }
            }

            return this.getThemeManager().getStyle();
        },

        updateStylesAfterSave(){
            this.updatePreferences('styleId', this.model.get(this.name + 'Id'))
            const style = this.getStyle(this.model.get(this.name + 'Id') || this.getConfig().get('defaultStyleId'));

            let styleUrl = this.getThemeManager().getCustomStylesheet()

            if (styleUrl) {
                let customLink = $('#custom-stylesheet');
                if (customLink.length > 0) {
                    customLink.attr('href', styleUrl + `?r=${Date.now()}`);
                } else {
                    $('head').append('<link href="' + styleUrl + '" rel="stylesheet" id="custom-stylesheet">');
                }
            } else {
                $('#custom-stylesheet').remove();
            }

            if (this.navFilter && style?.id) {
                this.getSessionStorage().set('navigationIconColor', this.navFilter);
            } else {
                this.getSessionStorage().clear('navigationIconColor')
            }

            if (this.toolbarFilter && style?.id) {
                this.getSessionStorage().set('toolbarIconColor', this.toolbarFilter);
            } else {
                this.getSessionStorage().clear('toolbarIconColor')
            }
        },

        updateStylesAfterChange(){
            const style = this.getStyle(this.model.get(this.name + 'Id') || this.getConfig().get('defaultStyleId'));
            this.reloadStyle(style);
        },

        shouldHide(){
            return this.getAcl().get('styleControlPermission') === 'no'
        },

        updatePreferences(key, value) {
            this.getPreferences().set(key, value);
        },

        remove() {
            const style = this.getStyle();
            this.reloadStyle(style);
            Dep.prototype.remove.call(this);
        }

    });
});
