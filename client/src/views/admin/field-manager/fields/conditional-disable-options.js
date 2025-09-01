/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/conditional-disable-options', 'views/fields/base', Dep => Dep.extend({
        // detailTemplate: 'fields/field-value-container',
        // editTemplate: 'fields/field-value-container',

        // setup() {
        //     Dep.prototype.setup.call(this);
        //     this.listenTo(this.model, 'change:extensibleEnumId', () => {
        //         this.reRender();
        //     });
        // },

        afterRender() {
            Dep.prototype.afterRender.call(this);
        },

    })
);