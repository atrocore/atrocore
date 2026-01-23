/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection-item/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({
        events: _.extend({
            'click a.link': function (e) {
                e.stopPropagation();
                if (e.ctrlKey || !this.scope || this.selectable) {
                    return;
                }
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                var recordModel = this.collection.get(id);

                var scope = recordModel.get('entityType');

                this.getModelFactory().create(scope, (model) => {
                    model.id = recordModel.get('entityId')
                    var options = {
                        id: recordModel.get('entityId'),
                        model: model
                    };

                    if (this.options.keepCurrentRootUrl) {
                        options.rootUrl = this.getRouter().getCurrentUrl();
                    }

                    this.getRouter().navigate('#' + scope + '/view/' + model.id);
                    this.getRouter().dispatch(scope, 'view', options);
                })
            },
        })
    });
});
