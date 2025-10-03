/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/matching-rule/fields/staging-entity', 'views/fields/varchar', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.onModelReady(() => {
                this.setValue();
                this.listenTo(this.model, 'change:matchingId', () => {
                    this.setValue();
                })
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get(this.name)) {
                this.$el.parent().show();
            } else {
                this.$el.parent().hide();
            }
        },

        setValue() {
            if (this.model.get('matchingId')) {
                $.each(this.getConfig().get('referenceData')?.Matching || [], (code, item) => {
                    if (item.type === 'staging' && item.id === this.model.get('matchingId')) {
                        this.model.set(this.name, item[this.name]);
                    }
                })
            } else {
                this.model.set(this.name, null);
            }
        },

    });
});