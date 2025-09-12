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
                let style = this.getStyle(this.model.get(this.name + 'Id'));
                if(style){
                    let master = new Master();
                    master.initStyleVariables(style);
                }
            })

            this.listenTo(this.model, 'after:save', () => {
                this.getStorage().clear('icons', 'navigationIconColor');
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.getAcl().get('styleControlPermission') === 'no') {
                this.hide();
            }
        },

        getStyle(id) {
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

    });
});
