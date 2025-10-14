/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/style/record/detail', ['views/record/detail', 'treo-core:views/site/master', 'color-converter'], (Dep, Master, ColorConverter) => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            let style = this.getThemeManager().getStyle();

            if(style && style.id === this.model.id) {
                this.listenTo(this.model, 'change', () => {
                    const style = this.model.attributes;
                    this.reloadStyle(style);
                })
            }

            this.listenTo(this.model, 'after:save after:inlineEditSave', () => {
                let customStylesheetPath = this.model.get('customStylesheetPath');
                if (this.model.get('customStylesheet') && customStylesheetPath) {
                    customStylesheetPath = customStylesheetPath.replace('public/', '');
                    let customLink = $('#custom-stylesheet');

                    if (customLink.length > 0) {
                        customLink.attr('href', customStylesheetPath + `?r=${Date.now()}`);
                    } else {
                        $('head').append('<link href="' + customStylesheetPath + '" rel="stylesheet" id="custom-stylesheet">');
                    }
                } else {
                    $('#custom-stylesheet').remove();
                }

                if (this.navFilter) {
                    this.getSessionStorage().set('navigationIconColor', this.navFilter);
                } else {
                    this.getSessionStorage().clear('navigationIconColor')
                }

                if (this.toolbarFilter) {
                    this.getSessionStorage().set('toolbarIconColor', this.toolbarFilter);
                } else {
                    this.getSessionStorage().clear('toolbarIconColor')
                }

                const referenceData = this.getConfig().get('referenceData') || {}
                const styles = referenceData['Style'] || {};
                styles[this.model.get('code')] = this.model.attributes;
                referenceData['Style'] = styles;
                this.getConfig().set('referenceData', referenceData);
            });
        },

        reloadStyle(style) {
            let master = new Master();
            master.initStyleVariables(style);

            this.navFilter = null;
            this.toolbarFilter = null;

            if (style.navigationMenuFontColor) {
                this.navFilter = master.getIconFilter(style.navigationMenuFontColor);
                $(".short-label img[src^=\"client/img/icons\"]").css('filter', this.navFilter.replace("filter:", '').replace(';', ''));
            } else {
                $(".short-label img[src^=\"client/img/icons\"]").css('filter', '');
            }

            if (style.toolbarFontColor) {
                this.toolbarFilter = master.getIconFilter(style.toolbarFontColor);
                $(".label-wrapper img[src^=\"client/img/icons\"]").css('filter', this.toolbarFilter.replace("filter:", '').replace(';', ''));
            } else {
                $(".label-wrapper img[src^=\"client/img/icons\"]").css('filter', '');
            }
        },

        remove() {
            const style = this.getThemeManager().getStyle();
            if(style && this.model.id === style.id) {
                this.reloadStyle(style);
            }
            Dep.prototype.remove.call(this);
        }
    });
});

