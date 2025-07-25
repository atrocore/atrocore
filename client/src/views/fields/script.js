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

        _template: '<div></div>',
        svelteComponent: null,

        fetch: function () {
            return this.svelteComponent.fetch()
        },

        setup() {
            Dep.prototype.setup.call(this);

            // @todo hotfix. For some reasons model does not contain script data.
            this.listenTo(this.model, 'sync', (model, data) => {
                if (data[this.name] && data[this.name] !== model.get(this.name)) {
                    this.model.set(this.name, data[this.name]);
                }
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this)

            if (!this.$el.children()[0]) {
                return
            }

            this.initStatusContainer()

            this.svelteComponent = new Svelte.Script({
                target: this.$el.children()[0],
                props: {
                    value: this.model.get(this.name),
                    scope: this.model.name,
                    name: this.name,
                    params: this.params,
                    mode: this.mode,
                }
            });
        },

        remove(dontEmpty) {
            if (this.svelteComponent) {
                try {
                    this.svelteComponent.$destroy()
                } catch (e) {
                }
            }

            Dep.prototype.remove.call(this, dontEmpty)
        }
    });
});
