/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/software-package/fields/target-version', 'views/fields/enum',
    Dep => Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.prepareOptionsList();
        },

        prepareOptionsList() {
            let value = this.model.get(this.name);

            // a constraint like '>=2.1.3-rc8 <=2.1.6' is set by us to keep the system working, so it has to be
            // kept as a value, but for the user it means exactly the same as '*'
            let latestValue = value && !this.isExactVersion(value) ? value : '*';

            this.params.options = [latestValue];
            this.translatedOptions = {[latestValue]: this.translate('latest', 'labels', 'SoftwarePackage')};

            (this.model.get('targetVersions') || []).forEach(version => {
                this.params.options.push(version);
                this.translatedOptions[version] = version;
            });
        },

        isExactVersion(version) {
            return !/[*^~<>=|,\s]/.test(version);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get(this.name) === null && ['list', 'detail'].includes(this.mode)) {
                const $span = this.$el.find('span');

                $span.removeClass('text-gray');
                $span.html('&mdash;');
            }
        },

    })
);
