/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/translation/fields/is-customized', 'views/fields/bool', function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            if (this.mode !== 'list') {
                this.listenTo(this.model, 'change', (model, o) => {
                    if (!model.hasChanged('isCustomized') && o.ui) {
                        this.model.set('isCustomized', true);
                    } else {
                        if (!this.model.get('isCustomized')) {
                            this.ajaxGetRequest(`Translation/action/getDefaults?key=${this.model.get('name')}`).then(fetchedAttributes => {
                                let values = this.getMetadata().get('entityDefs.Label.fields') || {};
                                $.each(values, (field, data) => {
                                    if (!!(data.isValue) && this.model.has(field)) {
                                        let value = (fetchedAttributes[field]) ? fetchedAttributes[field] : null;
                                        this.model.set(field, value, {reset: true});
                                    }
                                });
                            });
                        }
                    }
                });
            }
        },

    });
});

