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
            this.params.options = ['*'];
            this.translatedOptions = {'*': this.getLanguage().translateOption('*', 'targetVersion', 'SoftwarePackage')};

            this.model.get('targetVersions').forEach(version => {
                this.params.options.push(version);
                this.translatedOptions[version] = version;
            });
        },

    })
);
