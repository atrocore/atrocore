/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/allowed-options', 'views/fields/extensible-multi-enum-dropdown',
    Dep => {

        return Dep.extend({

            getExtensibleEnumId() {
                return this.model.get('extensibleEnumId');
            },

        });

    });