/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/assets', 'views/record/panels/relationship',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.actionList.unshift({
                label: this.translate('upload', 'labels', 'Asset'),
                action: 'massAssetCreate',
                data: {
                    link: this.link
                },
                acl: 'create',
                aclScope: 'Asset'
            });
        },

        actionMassAssetCreate(data) {
            const link = data.link;
            const foreignLink = this.model.defs['links'][link].foreign;

            this.model.defs['_relationName'] = link;

            this.notify('Loading...');
            this.createView('massCreate', 'views/asset/modals/edit', {
                name: 'massCreate',
                scope: 'Asset',
                relate: {
                    model: this.model,
                    link: foreignLink,
                },
                attributes: {massCreate: true},
                fullFormDisabled: true,
                layoutName: 'detailSmall'
            }, view => {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    this.actionRefresh();
                    this.model.trigger('after:relate', this.link, this.defs);
                });
            });
        },

    })
);

