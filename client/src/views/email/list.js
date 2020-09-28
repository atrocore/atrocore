

Espo.define('views/email/list', 'views/list', function (Dep) {

    return Dep.extend({

        createButton: false,

        template: 'email/list',

        folderId: null,

        folderScope: 'EmailFolder',

        currentFolderId: null,

        defaultFolderId: 'inbox',

        keepCurrentRootUrl: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.getAcl().checkScope('EmailAccountScope')) {
                this.addMenuItem('dropdown', {
                    name: 'reply',
                    label: 'Email Accounts',
                    link: '#EmailAccount/list/userId=' + this.getUser().id + '&userName=' +  encodeURIComponent(this.getUser().get('name'))
                });
            }

            if (this.getUser().isAdmin()) {
                this.menu.dropdown.push({
                    link: '#InboundEmail',
                    label: 'Inbound Emails'
                });
            }

            this.foldersDisabled = this.foldersDisabled ||
                                   this.getMetadata().get('scopes.' + this.folderScope + '.disabled') ||
                                   !this.getAcl().checkScope(this.folderScope);

            var params = this.options.params || {};

            this.selectedFolderId = params.folder || this.defaultFolderId;

            this.applyFolder();
        },

        data: function () {
            var data = {};
            data.foldersDisabled = this.foldersDisabled;
            return data;
        },

        actionComposeEmail: function () {
            this.notify('Loading...');
            var viewName = this.getMetadata().get('clientDefs.Email.modalViews.compose') || 'views/modals/compose-email';
            this.createView('quickCreate', viewName, {
                attributes: {
                    status: 'Draft'
                }
            }, function (view) {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', function () {
                    this.collection.fetch();
                }, this);
            }, this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (!this.foldersDisabled && !this.hasView('folders')) {
                this.loadFolders();
            }
        },

        getFolderCollection: function (callback) {
            this.getCollectionFactory().create(this.folderScope, function (collection) {
                collection.url = 'EmailFolder/action/listAll';
                collection.maxSize = 200;

                collection.folderCollection = collection;

                this.listenToOnce(collection, 'sync', function () {
                    callback.call(this, collection);
                }, this);
                collection.fetch();
            }, this);
        },

        loadFolders: function () {
            var xhr = null;
            this.getFolderCollection(function (collection) {
                this.createView('folders', 'views/email-folder/list-side', {
                    collection: collection,
                    emailCollection: this.collection,
                    el: this.options.el + ' .folders-container',
                    showEditLink: this.getAcl().check(this.folderScope, 'edit'),
                    selectedFolderId: this.selectedFolderId
                }, function (view) {
                    view.render();
                    this.listenTo(view, 'select', function (id) {
                        this.selectedFolderId = id;
                        this.applyFolder();

                        if (xhr && xhr.readyState < 4) {
                            xhr.abort();
                        }

                        this.notify('Please wait...');
                        xhr = this.collection.fetch({
                            success: function () {
                                this.notify(false);
                            }.bind(this)
                        });

                        if (id !== this.defaultFolderId) {
                            this.getRouter().navigate('#Email/list/folder=' + id);
                        } else {
                            this.getRouter().navigate('#Email');
                        }
                        this.updateLastUrl();
                    }, this);
                }, this);
            }, this);
        },

        applyFolder: function () {
            this.collection.data.folderId = this.selectedFolderId;
        },

        applyRoutingParams: function (params) {
            var id;

            if ('folder' in params) {
                id = params.folder || 'inbox';
            } else {
                return;
            }

            if (!params.isReturnThroughLink && id !== this.selectedFolderId) {
                var foldersView = this.getView('folders');
                if (foldersView) {
                    foldersView.actionSelectFolder(id);
                    foldersView.reRender();
                    $(window).scrollTop(0);
                }
            }
        }

    });
});
