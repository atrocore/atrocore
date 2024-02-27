/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/list', 'views/list-tree',
    Dep => Dep.extend({

        createButton: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.menu.buttons.push({
                link: '#' + this.scope + '/create',
                action: 'create',
                label: 'Create ' + this.scope,
                style: 'primary',
                acl: 'create',
                cssStyle: "margin-left: 15px",
                aclScope: this.entityType || this.scope
            });

            (this.menu.dropdown || []).unshift({
                acl: 'create',
                aclScope: 'Asset',
                action: 'massAssetCreate',
                label: this.translate('upload', 'labels', 'Asset'),
                iconHtml: ''
            });
        },

        actionMassAssetCreate() {
            this.notify('Loading...');
            this.createView('massCreate', 'views/asset/modals/edit', {
                scope: 'Asset',
                attributes: _.extend({massCreate: true}, this.getCreateAttributes() || {}),
                fullFormDisabled: true,
                layoutName: 'detailSmall'
            }, view => {
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    this.collection.fetch();
                    view.close();
                });
                view.listenTo(view.model, 'updating-started', () => view.disableButton('save'));
                view.listenTo(view.model, 'updating-ended', () => view.enableButton('save'));
                view.render();
            });
        },

    })
);

