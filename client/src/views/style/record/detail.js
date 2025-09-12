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
                    if(!this.model.isNew()  && style.id === this.model.id) {
                        const newStyle = this.model.attributes;
                        let master = new Master();
                        master.initStyleVariables(newStyle);
                        if (newStyle.navigationIconColor ) {
                            let colorConverter = new ColorConverter(newStyle['navigationIconColor']);
                            this.filter = colorConverter.solve().filter;
                            $(".nav-link img[src^=\"client/img/icons\"]").css('filter', this.filter.replace("filter:", '').replace(';',''));
                        }else{
                            $(".nav-link img[src^=\"client/img/icons\"]").css('filter','');
                        }
                    }
                })
            }

            this.listenTo(this.model, 'after:save after:inlineEditSave', () => {
                this.getStorage().clear('icons', 'navigationIconColor');
                let customStylesheetPath = this.model.get('customStylesheetPath');
                if (this.model.changed._prev?.customStylesheet && this.model.get('customStylesheet') && customStylesheetPath) {
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
                if(this.filter) {
                    this.getStorage().set('icons', 'navigationIconColor', this.filter);
                }else{
                    this.getStorage().clear('icons', 'navigationIconColor')
                }
            });
        }
    });
});

