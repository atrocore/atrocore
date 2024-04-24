/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/dropdown', 'views/fields/bool', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();

            const view = this.model.get('view');
            const type = this.model.get('type');
            let viewType = null;

            switch (type) {
                case 'linkMultiple':
                    viewType = 'link-multiple';
                    break;
                case 'extensibleEnum':
                    viewType = 'extensible-enum';
                    break;
                case 'extensibleMultiEnum':
                    viewType = 'extensible-multi-enum';
                    break;
                default:
                    viewType = type;
            }

            if (!view || (view === `views/fields/${viewType}-dropdown` && this.model.get('dropdown'))) {
                this.$el.parent().show();
            }
        },

    });

});
