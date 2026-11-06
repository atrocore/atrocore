/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

/**
 * Each record of an entity with subtypes belongs to one of them, so the subtype is chosen before the record is created.
 */
Espo.define('views/modals/select-subtype', 'view', Dep => Dep.extend({

    _template: '',

    svelteComponent: null,

    setup() {
        Dep.prototype.setup.call(this);

        this.once('remove', () => {
            if (this.svelteComponent) {
                this.svelteComponent.$destroy();
                this.svelteComponent = null;
            }
        });
    },

    afterRender() {
        Dep.prototype.afterRender.call(this);

        this.svelteComponent = new Svelte.SubtypeSelectModal({
            target: document.body,
            props: {
                scope: this.options.scope,
                onSelect: subtype => this.trigger('select', subtype),
                onClose: () => this.close()
            }
        });
    },

    close() {
        this.remove();
    },

}));
