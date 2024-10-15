/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/settings/fields/locale', 'views/fields/enum', Dep => {

    return Dep.extend({

        setupOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            $.each(this.getConfig().get('locales') || {}, (id, row) => {
                this.params.options.push(id);
                this.translatedOptions[id] = row.name;
            });
        },

    });
});
