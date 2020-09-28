

Espo.define('views/template/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew()) {
                this.setFieldReadOnly('entityType');
            }

            if (this.model.isNew()) {
                var storedData = {};

                this.listenTo(this.model, 'change:entityType', function (model) {
                    var entityType = this.model.get('entityType');

                    if (!entityType) {
                        this.model.set('header', '');
                        this.model.set('body', '');
                        this.model.set('footer', '');
                        return;
                    }

                    if (entityType in storedData) {
                        this.model.set('header', storedData[entityType].header);
                        this.model.set('body', storedData[entityType].body);
                        this.model.set('footer', storedData[entityType].footer);
                        return;
                    }

                    var header, body, footer;
                    if (this.getMetadata().get(['entityDefs', 'Template', 'defaultTemplates', entityType])) {
                        header = this.getMetadata().get(['entityDefs', 'Template', 'defaultTemplates', entityType, 'header']);
                        body = this.getMetadata().get(['entityDefs', 'Template', 'defaultTemplates', entityType, 'body']);
                        footer = this.getMetadata().get(['entityDefs', 'Template', 'defaultTemplates', entityType, 'footer']);
                    } else {
                        var scopeType = this.getMetadata().get(['scopes', entityType, 'type']);
                        if (scopeType) {
                            if (this.getMetadata().get(['entityDefs', 'Template', 'defaultTemplates', scopeType])) {
                                header = this.getMetadata().get(['entityDefs', 'Template', 'defaultTemplates', scopeType, 'header']);
                                body = this.getMetadata().get(['entityDefs', 'Template', 'defaultTemplates', scopeType, 'body']);
                                footer = this.getMetadata().get(['entityDefs', 'Template', 'defaultTemplates', scopeType, 'footer']);
                            }
                        }
                    }

                    if (header) {
                        this.model.set('header', header);
                    } else {
                        this.model.set('header', '');
                    }
                    if (body) {
                        this.model.set('body', body);
                    } else {
                        this.model.set('body', '');
                    }
                    if (footer) {
                        this.model.set('footer', footer);
                    } else {
                        this.model.set('footer', '');
                    }
                }, this);

                this.listenTo(this.model, 'change', function (e, o) {
                    if (!o.ui) return;

                    if (!this.model.hasChanged('header') && !this.model.hasChanged('body') && !this.model.hasChanged('footer')) {
                        return;
                    }

                    var entityType = this.model.get('entityType');
                    if (!entityType) return;

                    storedData[entityType] = {
                        header: this.model.get('header'),
                        body: this.model.get('body'),
                        footer: this.model.get('footer')
                    };
                }, this);
            }
        }

    });

});

