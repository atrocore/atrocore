/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-field/fields/option-label', 'views/fields/varchar-with-translation-sign', Dep => {

    return Dep.extend({

        getEntityScope() {
            return this.options.scope;
        },

        getEntityFieldName() {
            return this.options.field + '.' + this.model.get('code');
        },

        getCategory() {
            return 'options';
        },

        modalRenderedCallback(view, key) {
            Espo.Ui.notify(false);
            if (!view.model.get('code')) {
                view.model.set('code', key);
            }

            view.render();

            this.listenToOnce(view, 'remove', () => {
                this.clearView('modal');
            });

            this.listenToOnce(view, 'after:save', () => {
                this.model.trigger('label-update');
            });
        }

    });
});
