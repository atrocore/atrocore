/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/style/record/detail', ['views/record/detail', 'treo-core:views/site/master'], (Dep, Master) => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change', () => {
                let style = this.getThemeManager().getStyle();
                if(!this.model.isNew() && style.id === this.model.id) {
                    let master = new Master();
                    master.initStyleVariables(this.model.attributes);
                }
            })

            this.listenTo(this.model, 'after:save after:inlineEditSave', () => {
                this.getStorage().clear('icons', 'navigationIconColor');
                setTimeout(() => {
                    this.showReloadPageMessage()
                }, 2000);

            });
        }
    });
});

