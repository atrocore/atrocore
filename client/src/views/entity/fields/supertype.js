/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity/fields/supertype', 'views/fields/link',
    Dep => Dep.extend({

        createDisabled: true,

        selectBoolFilterList: ['canBeSupertype'],

        inheritedIconClass: null,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:supertypeId', () => {
                this.inheritIconClass();
            });

            this.listenTo(this.model, 'prepareAttributesForCreateRelated', (attributes, link, cb) => {
                if (link === 'subtypes') {
                    attributes.type = 'Subtype';
                    attributes.supertypeId = this.model.id;
                    attributes.supertypeName = this.model.get('name');
                    cb(attributes);
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.inheritIconClass();
            }
        },

        inheritIconClass() {
            if (this.model.id || !this.model.get('supertypeId')) {
                return;
            }

            // the icon of the supertype is a default only, an icon chosen by the user is kept
            const iconClass = this.model.get('iconClass');
            if (iconClass && iconClass !== this.inheritedIconClass) {
                return;
            }

            this.inheritedIconClass = this.getMetadata().get(['clientDefs', this.model.get('supertypeId'), 'iconClass']) || null;
            this.model.set('iconClass', this.inheritedIconClass);
        },

    })
);
