/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare/relationship-instance', 'views/record/compare/relationship', function (Dep) {
    return Dep.extend({

        prepareModels(selectFields, callback) {

            this.getModelFactory().create(this.relationship.scope, function (model) {
                this.ajaxGetRequest(this.scope + '/' + this.model.get('id') + '/' + this.relationship.name, {
                    select: selectFields.join(',')
                }).success(res => {
                    this.currentItemModels = res.list.map(item => {
                        let itemModel = model.clone()
                        itemModel.set(item)
                        return itemModel
                    });

                    return this.ajaxPostRequest('Synchronization/action/distantInstanceRequest', {
                        'uri': this.scope + '/' + this.model.get('id') + '/' + this.relationship.name + '?select=' + selectFields.join(','),
                        'type': 'list'
                    }).success(res => {
                        this.otherItemModels = [];
                        res.forEach((data, index) => {
                            if ('_error' in data) {
                                this.instances[index]['_error'] = data['_error'];
                            }
                            this.otherItemModels.push((data.list ?? []).map(item => {
                                for (let key in item) {
                                    let el = item[key];
                                    let instanceUrl = this.instances[index].atrocoreUrl;
                                    if (key.includes('PathsData')) {
                                        if (el && ('thumbnails' in el)) {
                                            for (let size in el['thumbnails']) {
                                                item[key]['thumbnails'][size] = instanceUrl + '/' + el['thumbnails'][size]
                                            }
                                        }
                                    }
                                }
                                let itemModel = model.clone()
                                itemModel.set(item)
                                return itemModel
                            }));
                        });

                        callback();
                    })
                });

            }, this);

        },

        updateBaseUrl(view, instanceUrl) {
            if (Number.isInteger(instanceUrl)) {
                instanceUrl = this.instances[instanceUrl]?.atrocoreUrl;
            }
            view.listenTo(view, 'after:render', () => {
                setTimeout(() => {
                    let localUrl = this.getConfig().get('siteUrl');
                    view.$el.find('a').each((i, el) => {
                        let href = $(el).attr('href')

                        if (href.includes('http') && localUrl) {
                            $(el).attr('href', href.replace(localUrl, instanceUrl))
                        }

                        if ((!href.includes('http') && !localUrl) || href.startsWith('/#') || href.startsWith('?') || href.startsWith('#')) {
                            $(el).attr('href', instanceUrl + href)
                        }
                        $(el).attr('target', '_blank')
                    });

                    view.$el.find('img').each((i, el) => {
                        let src = $(el).attr('src')
                        if (src.includes('http') && localUrl) {
                            $(el).attr('src', src.replace(localUrl, instanceUrl))
                        }

                        if (!src.includes('http')) {
                            $(el).attr('src', instanceUrl + '/' + src)
                        }
                    });
                })
            }, 1000)
        }
    })
})