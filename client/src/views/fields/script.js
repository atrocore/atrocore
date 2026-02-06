/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/script', ['views/fields/base'], Dep => {

    return Dep.extend({

        _template: '<div class="monaco-wrapper"></div>',

        svelteComponent: null,

        fetch: function () {
            return this.svelteComponent.fetch();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this)

            if (!this.$el.find('.monaco-wrapper')[0]) {
                return;
            }

            this.initStatusContainer()

            this.removeSvelteComponent()

            this.svelteComponent = new Svelte.Script({
                target: this.$el.find('.monaco-wrapper')[0],
                props: {
                    value: this.model.get(this.name),
                    scope: this.model.name,
                    name: this.name,
                    params: this.params,
                    mode: this.mode,
                    scriptFieldView: this
                }
            });
        },

        removeSvelteComponent() {
            if (this.svelteComponent) {
                try {
                    this.svelteComponent.$destroy()
                } catch (e) {
                }
            }
        },

        remove(dontEmpty) {
            this.removeSvelteComponent()

            Dep.prototype.remove.call(this, dontEmpty)
        }
    });
});
