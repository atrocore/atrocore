/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/api-request/index', 'view', function (Dep) {
    return Dep.extend({
        template: 'admin/api-request/index',
        setup() {
            this.once('after:render', () => {
                this.svelteComponent = new Svelte.ApiRequestComponent({
                    target: $('#api-request-content').get(0),
                    props: {
                        afterOnMount: (model) => {
                            console.log('executedddd', $('#api-request-content .field[data-name="type"]'))
                            this.createView('type', 'views/fields/enum', {
                                prohibitedEmptyValue: true,
                                model: model,
                                el: `#api-request-content .field[data-name="type"]`,
                                defs: {
                                    name: 'type',
                                    params: {
                                        options: ['upsert'],
                                        translatedOptions: {
                                            upsert: this.getLanguage().translate('upsert', 'labels')
                                        }
                                    }
                                },
                                mode: 'edit'
                            }, view => view.render());


                            this.createView('request', 'views/fields/script', {
                                model: model,
                                el: `#api-request-content .field[data-name="request"]`,
                                mode: 'edit'
                            }, view => view.render());

                            this.createView('response', 'views/fields/script', {
                                model: model,
                                el: `#api-request-content .field[data-name="response"]`
                            }, view => view.render());
                        }
                    }
                });
            })
        }
    })
})
