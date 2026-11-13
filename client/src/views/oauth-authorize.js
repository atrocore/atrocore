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

            // the authorization request of an OAuth client is kept in its cookie
            this.svelteComponent = new Svelte.OauthAuthorize({
                target: this.el
            });
        },

        onRemove: function () {
            this.svelteComponent?.$destroy();
        },

    });
});
