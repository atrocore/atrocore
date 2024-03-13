/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/queue-manager/fields/loader', 'views/fields/base',
    Dep => Dep.extend({

        listTemplate: 'treo-core:queue-manager/fields/loader/list',

        data() {
            return {
                showLoader: this.model.get('status') === 'Running'
            };
        },

        afterRender() {
            let loader = this.$el.find('.loader-container').get(0);
            if (loader) {
                let animationDuration = 2000;
                let deg = 360 * ((new Date()).getTime() % animationDuration) /
                    animationDuration;

                loader.style.transform = `rotate(${deg}deg)`;
            }
        }

    })
);

