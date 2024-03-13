/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/user/record/edit', ['views/user/record/edit', 'views/user/record/detail', 'views/record/edit'], function (Dep, Detail, MainDep) {

    return Dep.extend({

        setup: function () {
            MainDep.prototype.setup.call(this);

            this.setupNonAdminFieldsAccess();

            if (this.model.id == this.getUser().id) {
                this.listenTo(this.model, 'after:save', function () {
                    this.getUser().set(this.model.toJSON());
                }, this);
            }

            this.hideField('sendAccessInfo');

            var passwordChanged = false;

            this.listenToOnce(this.model, 'change:password', function (model) {
                passwordChanged = true;
                if (model.get('emailAddress')) {
                    this.showField('sendAccessInfo');
                    this.model.set('sendAccessInfo', true);
                }
            }, this);

            this.listenTo(this.model, 'change:emailAddress', function (model) {
                if (passwordChanged) {
                    if (model.get('emailAddress')) {
                        this.showField('sendAccessInfo');
                        this.model.set('sendAccessInfo', true);
                    } else {
                        this.hideField('sendAccessInfo');
                        this.model.set('sendAccessInfo', false);
                    }
                }
            }, this);

            Detail.prototype.setupFieldAppearance.call(this);

            this.hideField('passwordPreview');
            this.listenTo(this.model, 'change:passwordPreview', function (model, value) {
                value = value || '';
                if (value.length) {
                    this.showField('passwordPreview');
                } else {
                    this.hideField('passwordPreview');
                }
            }, this);
        },

    });
});
