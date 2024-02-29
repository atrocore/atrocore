/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/validation-rule/fields/int-size', 'views/fields/int', function (Dep) {
    return Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', function () {
                this.updateFieldLabel();
            }, this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.updateFieldLabel();
        },

        updateFieldLabel() {
            if (this.model.get('type') === 'Size') {
                this.getLabelElement().text(this.translate(this.name + 'Size', 'labels', this.model.name));
            } else {
                this.getLabelElement().text(this.getLabelText());
            }
        }
    });
});
