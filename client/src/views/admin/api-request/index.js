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

                            this.createView('request', 'views/fields/text', {
                                model: model,
                                el: `#api-request-content .field[data-name="request"]`,
                                mode: 'edit',
                                params: {
                                    seeMoreDisabled: false,
                                    rowsMin: 42,
                                    rowsMax: 50
                                }
                            }, view => {
                                view.render()
                                view.once('after:render', () => $(view.$el).find('textarea').css('resize', 'none'));
                                $(view.$el).on('paste', (e) => {

                                    e.preventDefault();
                                    let content = (e.clipboardData || e.originalEvent.clipboardData || window.clipboardData).getData("text");
                                    try {
                                        content = JSON.stringify(JSON.parse(content), undefined, 4);
                                        $(e.currentTarget).find('textarea').val(content);
                                    } catch (e) {
                                        this.notify(this.translate('You should paste only JSON content'), 'danger')
                                    }

                                    model.set($(e.currentTarget).find('textarea').val())
                                })
                            });
                            this.listenTo(model, 'change:request', () => {
                                model.set('response', model.get('request'))
                            })
                            this.createView('response', 'views/fields/script', {
                                model: model,
                                params: {
                                    rowsMin: 42,
                                    rowsMax: 50
                                },
                                el: `#api-request-content .field[data-name="response"]`
                            }, view => {
                                view.render()
                            });
                        }
                    }
                });
            })
        }
    })
})
