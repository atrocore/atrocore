/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/translation/fields/module', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setupTranslation() {
            const now = new Date();
            const key = 'installedModulesData';

            let data = localStorage.getItem(key);
            if (data) {
                data = JSON.parse(data);
            }

            if (!data || now.getTime() > data.expiry) {
                this.ajaxGetRequest(`Composer/list`, {}, { async: false }).then(response => {
                    const list = response.list.map(module => {
                        return {
                            id: module.id,
                            name: module.name
                        }
                    });
                    data = { data: list, expiry: now.getTime() + 5 * 60 * 1000 };
                    try {
                        localStorage.setItem(key, JSON.stringify(data))
                    } catch (e) {
                        console.warn('Failed to save data to localStorage', e);
                    }
                });
            }

            this.params.options = [];
            this.translatedOptions = {};

            data.data.forEach(module => {
                let id = module.id === 'Atro' ? 'core' : module.id;

                this.params.options.push(id);
                this.translatedOptions[id] = module.name;
            });

            this.params.options.push('custom');
            this.translatedOptions['custom'] = 'Custom';
        },

    });
});

