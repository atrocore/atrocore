/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/translation/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        massActionList: ['massUpdate', 'export'],

        checkAllResultMassActionList: ['massUpdate', 'export'],

        setup() {
            Dep.prototype.setup.call(this);

            this.events['click a.link'] = function (e) {
                e.stopPropagation();
                if (!this.scope || this.selectable) {
                    return;
                }
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                var model = this.collection.get(id);

                var scope = this.getModelScope(id);

                var options = {
                    id: id,
                    model: model
                };
                if (this.options.keepCurrentRootUrl) {
                    options.rootUrl = this.getRouter().getCurrentUrl();
                }

                this.getRouter().navigate('#' + scope + '/edit/' + id, {trigger: false});
                this.getRouter().dispatch(scope, 'edit', options);
            };
        },

    });
});

