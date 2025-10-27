/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/panels/side/sharing', 'views/record/panels/relationship', Dep => {

    return Dep.extend({

        name: 'sharing',

        template: 'record/panels/sharing',

        events: _.extend({
            'click .copy-link': function (e) {
                let link = $(e.currentTarget).data('link');

                let $temp = $("<input>");
                $("body").append($temp);
                $temp.val(link).select();
                document.execCommand("copy");
                $temp.remove();

                this.notify(this.translate('copiedToClipboard'), 'success');
            }
        }, Dep.prototype.events),

        data: function () {
            const data = Dep.prototype.data.call(this);

            data.canShare = this.getAcl().check('Sharing', 'create');

            return data;
        },

        setup: function () {
            this.scope = this.model.name;

            this.wait(true);
            this.getCollectionFactory().create('Sharing', function (collection) {
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;

                collection.where = [
                    {
                        "type": "equals",
                        "attribute": "fileId",
                        "value": this.model.get('id')
                    }
                ];

                collection.sortBy = 'createdAt';
                collection.asc = true;

                this.collection = collection;

                let viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';

                this.once('after:render', function () {
                    this.createView('list', viewName, {
                        collection: collection,
                        layoutName: 'list',
                        listLayout: [
                            {
                                "name": "available",
                                "widthPx": 20,
                                "view": "views/sharing/fields/available-in-side-panel"
                            },
                            {
                                "name": "name",
                                "link": true,
                            },
                            {
                                "name": "link",
                                "widthPx": 30,
                                "view": "views/sharing/fields/link-in-side-panel"
                            }
                        ],
                        checkboxes: false,
                        rowActionsView: "views/record/row-actions/relationship-no-unlink",
                        buttonsDisabled: true,
                        el: this.options.el + ' .list-container',
                        skipBuildRows: true,
                        listRowsOrderSaveUrl: this.listRowsOrderSaveUrl,
                        panelView: this,
                    }, function (view) {
                        view.getSelectAttributeList(selectAttributeList => {
                            if (selectAttributeList) {
                                collection.data.select = selectAttributeList.join(',');
                            }
                            collection.fetch();
                        });

                        view.listenTo(view, 'after:render', view => {
                            view.$el.removeClass('no-data');
                            if (view.$el.find('table').length === 0) {
                                view.$el.addClass('no-data');
                            }
                        });
                    });
                }, this);

                this.wait(false);
            }, this);
        },

        actionCreateSharing: function (data) {
            let viewName = this.getMetadata().get('clientDefs.Sharing.modalViews.edit') || 'views/modals/edit';

            this.notify('Loading...');
            this.createView('quickCreate', viewName, {
                scope: 'Sharing',
                attributes: {
                    fileId: this.model.get('id'),
                    fileName: this.model.get('name')
                },
            }, function (view) {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', function () {
                    this.collection.fetch();
                    this.model.trigger('after:relate');
                }, this);
            });
        },

        actionRefresh: function () {
            this.collection.fetch();
        },

    });
});

