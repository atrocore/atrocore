/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/matching/fields/matching-entity-type', 'views/fields/entity-type', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.setupOptions();
                this.setupTranslation();
                this.originalOptionList = null;
                if (this.model.get(this.name) && !this.params.options.includes(this.model.get(this.name))) {
                    this.model.set(this.name, null);
                }
                this.reRender();
            });
        },

        checkAvailability(entityType) {
            if (!this.model.get('type')) {
                return false;
            }

            if (!Dep.prototype.checkAvailability.call(this, entityType)) {
                return false;
            }

            if (this.model.get('type') === 'masterRecord') {
                const defs = this.scopesMetadataDefs[entityType] || {};
                return !!defs.primaryEntityId && defs.role === 'contributor';
            }

            return true;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get(this.name) !== null && ['list', 'detail'].includes(this.mode)) {
                this.$el.html(`<a href="/#MasterDataEntity/view/${this.model.get(this.name)}">${this.$el.html()}</a>`);
            }
        },

    });
});