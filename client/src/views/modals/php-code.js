/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/modals/php-code', 'views/modal',
    Dep => Dep.extend({

        template: 'modals/php-code',

        setup() {
            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.header = this.getLanguage().translate('phpCode');

            this.setupFields();
        },

        setupFields() {
            this.createView('phpCode', 'views/fields/script', {
                el: `${this.options.el} .field[data-name="phpCode"]`,
                model: this.model,
                params: {
                    language: 'php'
                },
                name: 'phpCode',
                mode: 'detail',
                inlineEditDisabled: true
            });
        },

    })
);
