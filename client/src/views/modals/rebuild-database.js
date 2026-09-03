/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/modals/rebuild-database', 'view', function (Dep) {

    return Dep.extend({

        _template: '',

        svelteComponent: null,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.once('remove', () => {
                if (this.svelteComponent) {
                    this.svelteComponent.$destroy();
                    this.svelteComponent = null;
                }
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.svelteComponent = new Svelte.RebuildDatabaseModal({
                target: document.body,
                props: {
                    onClose: () => this.remove()
                }
            });
        }

    });
});