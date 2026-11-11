/*
 *  AtroCore Software
 *
 *  This source file is available under GNU General Public License version 3 (GPLv3).
 *  Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 *  @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 *  @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/oauth-authorize', 'view', function (Dep) {

    return Dep.extend({

        _template: '',

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            // the query string of the authorization request, without the parameter of the entry point
            const params = new URLSearchParams(window.location.search);
            params.delete('entryPoint');

            this.svelteComponent = new Svelte.OauthAuthorize({
                target: this.el,
                props: {
                    query: params.toString()
                }
            });
        },

        onRemove: function () {
            this.svelteComponent?.$destroy();
        },

    });
});
