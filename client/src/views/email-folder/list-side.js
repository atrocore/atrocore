

Espo.define('views/email-folder/list-side', 'view', function (Dep) {

    return Dep.extend({

        template: 'email-folder/list-side',

        events: {
            'click [data-action="selectFolder"]': function (e) {
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                this.actionSelectFolder(id);
            }
        },

        data: function () {
            var data = {};
            data.selectedFolderId = this.selectedFolderId;
            data.showEditLink = this.options.showEditLink;
            data.scope = this.scope;
            return data;
        },

        actionSelectFolder: function (id) {
            this.$el.find('li.selected').removeClass('selected');
            this.selectFolder(id);
            this.$el.find('li[data-id="'+id+'"]').addClass('selected');
        },

        setup: function () {
            this.scope = 'EmailFolder';
            this.selectedFolderId = this.options.selectedFolderId || 'all';
            this.emailCollection = this.options.emailCollection;

            this.loadNotReadCounts();

            this.listenTo(this.emailCollection, 'sync', this.loadNotReadCounts);

            this.listenTo(this.emailCollection, 'all-marked-read', function (m) {
                this.countsData = this.countsData || {};
                for (var id in this.countsData) {
                    if (id === 'drafts') {
                        continue;
                    }
                    this.countsData[id] = 0;
                }
                this.renderCounts();
            });

            this.listenTo(this.emailCollection, 'change:isRead', function (model) {
                if (this.countsIsBeingLoaded) return;
                this.manageCountsDataAfterModelChanged(model);
            }, this);

            this.listenTo(this.emailCollection, 'model-removing', function (id) {
                var model = this.emailCollection.get(id);
                if (!model) return;
                if (this.countsIsBeingLoaded) return;
                this.manageModelRemoving(model);
            }, this);

            this.listenTo(this.emailCollection, 'moving-to-trash', function (id) {
                var model = this.emailCollection.get(id);
                if (!model) return;
                if (this.countsIsBeingLoaded) return;
                this.manageModelRemoving(model);
            }, this);

            this.listenTo(this.emailCollection, 'retrieving-from-trash', function (id) {
                var model = this.emailCollection.get(id);
                if (!model) return;
                if (this.countsIsBeingLoaded) return;
                this.manageModelRetrieving(model);
            }, this);
        },

        manageModelRemoving: function (model) {
            if (model.get('status') === 'Draft') {
                this.decreaseNotReadCount('drafts');
                this.renderCounts();
                return;
            }

            if (!model.get('isUsers')) return;
            if (model.get('isRead')) return;

            var folderId = model.get('folderId') || 'inbox';
            this.decreaseNotReadCount(folderId);
            this.renderCounts();
        },

        manageModelRetrieving: function (model) {
            if (!model.get('isUsers')) return;
            if (model.get('isRead')) return;
            var folderId = model.get('folderId') || 'inbox';
            this.increaseNotReadCount(folderId);
            this.renderCounts();
        },

        manageCountsDataAfterModelChanged: function (model) {
            if (!model.get('isUsers')) return;
            var folderId = model.get('folderId') || 'inbox';
            if (!model.get('isRead')) {
                this.increaseNotReadCount(folderId);
            } else {
                this.decreaseNotReadCount(folderId);
            }
            this.renderCounts();
        },

        increaseNotReadCount: function (folderId) {
            this.countsData = this.countsData || {};
            this.countsData[folderId] = this.countsData[folderId] || 0;
            this.countsData[folderId]++;
        },

        decreaseNotReadCount: function (folderId) {
            this.countsData = this.countsData || {};
            this.countsData[folderId] = this.countsData[folderId] || 0;
            if (this.countsData[folderId]) {
                this.countsData[folderId]--;
            }
        },

        selectFolder: function (id) {
            this.selectedFolderId = id;
            this.trigger('select', id);
        },

        afterRender: function () {
            if (this.countsData) {
                this.renderCounts();
            }
        },

        loadNotReadCounts: function () {
            if (this.countsIsBeingLoaded) return;

            this.countsIsBeingLoaded = true;
            this.ajaxGetRequest('Email/action/getFoldersNotReadCounts').then(function (data) {
                this.countsData = data;
                if (this.isRendered()) {
                    this.renderCounts();
                    this.countsIsBeingLoaded = false;
                } else {
                    this.once('after:render', function () {
                        this.renderCounts();
                        this.countsIsBeingLoaded = false;
                    }, this);
                }
            }.bind(this));
        },

        renderCounts: function () {
            var data = this.countsData;
            for (var id in data) {
                var value = '';
                if (data[id]) {
                    value = data[id].toString();
                }
                this.$el.find('li a.count[data-id="'+id+'"]').text(value);
            }
        }

    });
});

