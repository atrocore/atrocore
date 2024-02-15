/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/query-builder', ['view', 'lib!Interact', 'lib!QueryBuilder'], function (Dep) {

    return Dep.extend({

        search() {
            this.collection.where = [];

            const rules = this.$el.find('.query-builder').queryBuilder('getRules');

            if (rules && rules.rules && rules.rules.length > 0) {
                this.collection.where = [rules];
            }

            this.collection.fetch().then(() => Backbone.trigger('after:search', this.collection));

            Backbone.Events.trigger('search', this);
        },

        resetFilters() {
            this.$el.find('.query-builder').queryBuilder('setRules', []);
            this.search();
        },

    });
});

