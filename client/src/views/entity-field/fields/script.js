/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-field/fields/script', 'views/fields/script', Dep => {
    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:isMultilang change:type change:outputType', () => {
                this.controlViewVisibility();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.controlViewVisibility();
        },

        controlViewVisibility() {
            let locale = this.model.getFieldParam(this.name, 'multilangLocale') || 'main';

            if (locale !== 'main') {
                if (this.model.get('type') === 'script' && this.model.get('isMultilang') && this.model.get('outputType') === 'text') {
                    this.show();
                } else {
                    this.hide();
                    this.model.set(this.name, null);
                }
            }
        }
    });

});
