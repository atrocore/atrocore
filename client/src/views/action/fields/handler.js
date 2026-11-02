/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

/**
 * The handler of a custom action: the labels and the suggested name and description come from the handler class.
 */
Espo.define('views/action/fields/handler', 'views/fields/enum',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            $.each((this.getMetadata().get('action.customHandlers') || {}), (handler, data) => {
                if (data.typeLabel) {
                    this.translatedOptions[handler] = data.typeLabel;
                }
            });

            this.listenTo(this.model, `change:${this.name}`, () => {
                const data = this.getMetadata().get(['action', 'customHandlers', this.model.get(this.name)]) || {};

                if (data.name && !this.model.get('name')) {
                    this.model.set('name', data.name);
                }

                if (data.description && !this.model.get('description')) {
                    this.model.set('description', data.description);
                }
            });
        },

    })
);
