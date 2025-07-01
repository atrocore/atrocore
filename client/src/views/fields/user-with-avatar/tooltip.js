/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/user-with-avatar/tooltip', 'view', function (Dep) {

    return Dep.extend({

        template: 'fields/user-with-avatar/tooltip',

        fields: ['title', 'department', 'emailAddress', 'phoneNumber'],

        data() {
            const result = {
                avatar: this.getAvatarHtml(),
                name: this.model.get('name'),
                username: this.model.get('userName'),
            };

            const items = this.getTableItems();
            if (Object.keys(items).length > 0) {
                result.items = items;
            }

            return result;
        },

        getAvatarHtml: function () {
            return this.getHelper().getAvatarHtml(this.model.get('id'), 'small', 48);
        },

        getTableItems: function () {
            const result = {};

            this.fields.forEach(field => {
                const value = this.model.get(field);
                if (value) {
                    result[field] = value;
                }
            });

            return result;
        }
    });
});
