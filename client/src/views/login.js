/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/login', 'view', function (Dep) {

    return Dep.extend({

        _template: '',

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            // the session is opened by the sign-in, the application continues on the route it was opened with
            this.svelteComponent = new Svelte.Login({
                target: this.el,
                props: {
                    onSignedIn: () => this.trigger('login')
                }
            });
        },

        onRemove: function () {
            this.svelteComponent?.$destroy();
        },

    });
});
