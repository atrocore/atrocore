/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout/fields/related-entity', 'views/layout/fields/entity', function (Dep) {

    return Dep.extend({

        isScopeAvailable(scope) {
            const entity = this.model.get('entity')
            if (!entity) {
                return false
            }

            const links = this.getMetadata().get(`entityDefs.${scope}.links`) || {}

            const link = Object.keys(links)
                .find(link => links[link]?.entity === entity)

            return !!link && this.getMetadata().get('scopes.' + scope + '.entity') &&
                this.getMetadata().get('scopes.' + scope + '.layouts');
        },


        setup: function () {
            Dep.prototype.setup.call(this);

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
            if(this.mode!=='list'){
                if (this.model.get('viewType') !== 'list') {
                    this.hide()
                } else {
                    this.show()
                }
            }else{
                if (this.model.get('viewType') !== 'list') {
                    this.$el.children().addClass('invisible');
                } else {
                    this.$el.children().removeClass('invisible');
                }
            }
        }
    });
});

