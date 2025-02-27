/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-profile/fields/notification-profile', 'views/fields/link-dropdown', Dep => {

    return Dep.extend({

        getLinkOptions(foreignScope) {
            let data = Dep.prototype.getLinkOptions.call(this, foreignScope);

            if (this.getConfig().get('defaultNotificationProfileId')) {
                let newData = data.filter(profile => profile.id !== this.getConfig().get('defaultNotificationProfileId'))
                if (newData.length < data.length) {
                    newData.unshift({
                        id: 'default',
                        name: this.translate('Default')
                    })
                }
                return newData;
            }

            return data;
        }

    });
});
