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

        data() {
            let data = Dep.prototype.data.call(this);
            if (!data.value && this.getConfig().get('defaultNotificationProfileId')) {
                data.value = 'default';
                data.translatedOptions = Object.assign({}, data.translatedOptions, {
                    'default': this.translate('Default')
                });
                data.isNotEmpty = true;
            }
            return data;
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            if (data[this.name] === 'default') {
                data[this.name] = null;
                data[this.name + 'Name'] = null;
            }
            return data;
        },

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
