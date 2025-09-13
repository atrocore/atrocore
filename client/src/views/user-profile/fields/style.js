/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-profile/fields/style', ['views/fields/link', 'treo-core:views/site/master', 'color-converter'], (Dep, Master, ColorConverter) => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, `change:${this.name + 'Id'}`, () => {
                const style = this.getStyle(this.model.get(this.name + 'Id'));
                this.reloadStyle(style);
            });

            this.listenTo(this.model, 'after:save after:inlineEditSave', () => {
                this.getPreferences().set('styleId', this.model.get(this.name + 'Id'))
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

                if(this.filter) {
                    this.getStorage().set('icons', 'navigationIconColor', this.filter);
                }else{
                    this.getStorage().clear('icons', 'navigationIconColor')
                }
            });
        },

        reloadStyle(style){
            if(style){
                let master = new Master();
                master.initStyleVariables(style);
                if (style?.navigationIconColor) {
                    let colorConverter = new ColorConverter(style?.navigationIconColor);
                    this.filter = colorConverter.solve().filter;
                    $(".label-wrapper img[src^=\"client/img/icons\"], .short-label img[src^=\"client/img/icons\"]").css('filter', this.filter.replace("filter:", '').replace(';',''));
                }else{
                    $(".label-wrapper img[src^=\"client/img/icons\"], .short-label img[src^=\"client/img/icons\"]").css('filter', '');
                }

                if(!this.getConfig().get('companyLogo')) {
                    if(style.logo) {
                        $('.navbar-brand img').attr('src', style.logo);
                    }else{
                        $('.navbar-brand img').attr('src', 'client/modules/treo-core/img/core_logo_dark.svg');
                    }
                }
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.getAcl().get('styleControlPermission') === 'no') {
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

        remove() {
            const style = this.getStyle();
            this.reloadStyle(style);
            Dep.prototype.remove.call(this);
        }

    });
});
