

Espo.define('views/modals/select-records-with-categories', ['views/modals/select-records', 'views/list-with-categories'], function (Dep, List) {

    return Dep.extend({

        template: 'modals/select-records-with-categories',

        categoryScope: null,

        categoryField: 'category',

        categoryFilterType: 'inCategory',

        isExpanded: true,

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.categoriesDisabled = this.categoriesDisabled;
            return data;
        },

        setup: function () {
            this.scope = this.entityType = this.options.scope || this.scope;
            this.categoryScope = this.categoryScope || this.scope + 'Category';

            this.categoriesDisabled = this.categoriesDisabled ||
                                   this.getMetadata().get('scopes.' + this.categoryScope + '.disabled') ||
                                   !this.getAcl().checkScope(this.categoryScope);

            Dep.prototype.setup.call(this);


        },

        loadList: function () {
            if (!this.categoriesDisabled) {
                this.loadCategories();
            }
            Dep.prototype.loadList.call(this);
        },

        loadCategories: function () {
            this.getCollectionFactory().create(this.categoryScope, function (collection) {
                collection.url = collection.name + '/action/listTree';

                collection.data.onlyNotEmpty = true;

                this.listenToOnce(collection, 'sync', function () {
                    this.createView('categories', 'views/record/list-tree', {
                        collection: collection,
                        el: this.options.el + ' .categories-container',
                        selectable: true,
                        createDisabled: true,
                        showRoot: true,
                        rootName: this.translate(this.scope, 'scopeNamesPlural'),
                        buttonsDisabled: true,
                        checkboxes: false
                    }, function (view) {
                        if (this.isRendered()) {
                            view.render();
                        } else {
                            this.listenToOnce(this, 'after:render', function () {
                                view.render();
                            }, this);
                        }

                        this.listenTo(view, 'select', function (model) {
                            this.currentCategoryId = null;
                            this.currentCategoryName = '';

                            if (model && model.id) {
                                this.currentCategoryId = model.id;
                                this.currentCategoryName = model.get('name');
                            }

                            this.applyCategoryToCollection();

                            this.notify('Please wait...');
                            this.listenToOnce(this.collection, 'sync', function () {
                                this.notify(false);
                            }, this);
                            this.collection.fetch();

                        }, this);
                    }.bind(this));
                }, this);
                collection.fetch();
            }, this);
        },

        applyCategoryToCollection: function () {
            List.prototype.applyCategoryToCollection.call(this);
        },

        isCategoryMultiple: function () {
            List.prototype.isCategoryMultiple.call(this);
        }

    });
});
