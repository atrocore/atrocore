/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout/fields/related-entity', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        isScopeAvailable(scope) {
            const entity = this.model.get('entity')
            if (!entity) {
                return false
            }

            if (['ProductAttributeValue', 'ClassificationAttribute', 'ComponentAttributeValue'].includes(scope)) {
                return false
            }


            return this.getMetadata().get('scopes.' + scope + '.entity') &&
                this.getMetadata().get('scopes.' + scope + '.type') !== 'Relation' &&
                this.getMetadata().get('scopes.' + scope + '.layouts');
        },

        getAvailableOptions: function () {
            let options = [];

            for (const scope of this.getMetadata().getScopeList()) {
                if (this.isScopeAvailable(scope)) {
                    const links = this.getMetadata().get(`entityDefs.${scope}.links`) || {}
                    const linkNames = Object.keys(links)
                        .filter(link => links[link]?.entity === this.model.get('entity'))
                    linkNames.forEach(linkName => {
                        options.push({
                            name: `${scope}.${linkName}`,
                            scope: scope,
                            link: linkName,
                            label: this.translate(scope, 'scopeName')
                        })
                    })
                }
            }

            options.forEach(option => {
                const similar = options.filter(o => o.label === option.label)
                if (similar.length > 1) {
                    similar.forEach(option => {
                        const name = this.translate(option.link, 'fields', option.scope)
                        option.label = option.label + ` (${name})`
                    })
                }
            })

            options = options.sort(function (v1, v2) {
                return v1.label.localeCompare(v2.label);
            });

            return options;
        },

        setupOptions: function () {
           const options = this.getAvailableOptions()

            this.params.options = options.map(option => option.name);
            this.params.translatedOptions = options.reduce((prev, curr) => {
                prev[curr.name] = curr.label;
                return prev;
            }, {})
        },

        setup: function () {
            this.setupOptions();
            Dep.prototype.setup.call(this);
            if (!this.model.get(this.name) && this.params.options.length) {
                this.model.set(this.name, this.params.options[0])
            }

            this.listenTo(this.model, 'change:entity change:viewType', () => {
                this.setupOptions()
                if (!this.prohibitedEmptyValue) {
                    this.translatedOptions[''] = '';
                    if (!this.params.options.includes('')) {
                        this.params.options.unshift('')
                    }
                }
                if (this.model.get('relatedEntity') && !this.params.options.includes(this.model.get('relatedEntity'))) {
                    this.model.set('relatedEntity', '')
                }
                this.reRender()
            })
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this)
            const show = ['list', 'detail'].includes(this.model.get('viewType')) && this.getMetadata().get(['scopes', this.model.get('entity'), 'type']) !== 'Relation'
            if (this.mode !== 'list') {
                if (show) {
                    this.show()
                } else {
                    this.hide()
                }
            } else {
                if (show) {
                    this.$el.children().removeClass('invisible');
                } else {
                    this.$el.children().addClass('invisible');
                }
            }
        }
    });
});

