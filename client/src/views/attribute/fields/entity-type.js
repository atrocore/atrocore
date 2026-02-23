/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/fields/entity-type', 'views/fields/enum',
    Dep => Dep.extend({

        setupOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            let scopes = this.getMetadata().get('scopes') || null;
            if (scopes) {
                let entityList = Object.keys(scopes).filter(function (item) {
                    return scopes[item].entity && ["Base", "Hierarchy"].includes(scopes[item].type);
                }).sort(function (v1, v2) {
                    let t1 = this.translate(v1, 'scopeNames');
                    let t2 = this.translate(v2, 'scopeNames');
                    return t1.localeCompare(t2);
                }.bind(this));

                entityList.forEach(entity => {
                    this.params.options.push(entity);
                    this.translatedOptions[entity] = this.translate(entity, 'scopeNames');
                });
            }
        },
    })
);
