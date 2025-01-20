/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout/fields/entity', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        isScopeAvailable(scope) {
            return this.getMetadata().get('scopes.' + scope + '.entity') &&
                this.getMetadata().get('scopes.' + scope + '.layouts');
        },

        setupOptions: function () {
            this.params.options = this.getAvailableOptions()
        },

        getAvailableOptions() {
            const scopeList = [];

            const scopeFullList = this.getMetadata().getScopeList().sort(function (v1, v2) {
                return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
            }.bind(this));

            scopeFullList.forEach(function (scope) {
                if (this.isScopeAvailable(scope)) {
                    scopeList.push(scope);
                }
            }, this);

            return scopeList;
        },

        setup: function () {
            if (!this.params.translation) {
                this.params.translation = 'Global.scopeNames';
            }
            this.setupOptions();
            Dep.prototype.setup.call(this);
        },
    });
});

