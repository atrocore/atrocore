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
                            model.set('response', '  ');

                            this.createView('type', 'views/fields/enum', {
                                prohibitedEmptyValue: true,
                                model: model,
                                el: `#api-request-content .field[data-name="type"]`,
                                defs: {
                                    name: 'type',
                                    params: {
                                        options: ['upsert'],
                                        translatedOptions: {
                                            upsert: this.getLanguage().translate('upsert', 'labels', 'Admin')
                                        }
                                    }
                                },
                                mode: 'edit'
                            }, view => view.render());

                            this.createView('request', 'views/fields/text', {
                                model: model,
                                el: `#api-request-content .field[data-name="request"]`,
                                mode: 'edit',
                                defs: {
                                    name: 'request'
                                },
                                params: {
                                    seeMoreDisabled: false,
                                    rowsMin: 40,
                                    rowsMax: 40
                                }
                            }, view => {
                                view.render()
                                view.listenTo(view, 'after:render', () => $(view.$el).find('textarea').css('resize', 'none'));
                                $(view.$el).on('paste', (e) => {
                                    e.preventDefault();
                                    let content = (e.clipboardData || e.originalEvent.clipboardData || window.clipboardData).getData("text");
                                    try {
                                        content = JSON.stringify(JSON.parse(content), undefined, 4);
                                        $(e.currentTarget).find('textarea').val(content);
                                    } catch (e) {
                                        this.notify(this.translate('You should paste only JSON content'), 'danger')
                                    }
                                    model.set('request', $(e.currentTarget).find('textarea').val())
                                })
                            });

                            this.createView('response', 'views/fields/text', {
                                model: model,
                                defs: {
                                    name: 'response'
                                },
                                params: {
                                    rowsMin: 40,
                                    rowsMax: 40
                                },
                                mode: 'edit',
                                el: `#api-request-content .field[data-name="response"]`
                            }, view => {
                                view.render()
                                view.listenTo(view, 'after:render', () => {
                                    $(view.$el).find('textarea').css('resize', 'none')
                                    $(view.$el).find('textarea').attr('readonly', true)
                                });
                                view.listenTo(model, 'change:response', () => view.reRender());
                                view.listenTo(model, 'change:status', () => {
                                    $('[data-name="response"] .status')
                                        .removeClass('hidden')
                                        .css('color', model.get('status') === 200 ? 'green': 'red')
                                        .find('span').text(model.get('status'))
                                });
                            });
                        },
                        sendRequest: (model) => {
                            if (!model.get('request')) {
                                this.notify(this.translate('No data provided'), 'danger');
                                return;
                            }
                            this.notify(this.translate('Please Wait...'))
                            this.ajaxPostRequest('MassActions/action/upsert', JSON.parse(model.get('request')))
                                .success(res => {
                                    this.notify(false)
                                    if (res) {
                                        model.set('response', JSON.stringify(res, undefined, 4))
                                    }
                                    model.set('status', 200)

                                }).error((e) => {
                                console.error(e);
                                model.set('status', e.status);
                                try {
                                    model.set('response', JSON.stringify(e.responseText, undefined, 4))
                                } catch (e) {
                                    model.set('response', e.responseText);
                                }
                            })
                        }
                    }
                });
            })
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('apiRequest', 'labels', 'Admin'));
        }
    })
})
