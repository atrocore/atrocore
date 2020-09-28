

Espo.define('views/record/panels/default-side', 'views/record/panels/side', function (Dep) {

    return Dep.extend({

        template: 'record/panels/default-side',

        data: function () {
            var data = Dep.prototype.data.call(this);
            if (this.complexCreatedDisabled && this.complexModifiedDisabled  || (!this.hasComplexCreated && !this.hasComplexModified)) {
                data.complexDateFieldsDisabled = true;
            }
            data.hasComplexCreated = this.hasComplexCreated;
            data.hasComplexModified = this.hasComplexModified;
            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.hasComplexCreated =
                !!this.getMetadata().get(['entityDefs', this.model.name, 'fields', 'createdAt'])
                &&
                !!this.getMetadata().get(['entityDefs', this.model.name, 'fields', 'createdBy']);

            this.hasComplexModified =
                !!this.getMetadata().get(['entityDefs', this.model.name, 'fields', 'modifiedAt'])
                &&
                !!this.getMetadata().get(['entityDefs', this.model.name, 'fields', 'modifiedBy']);

            if (!this.complexCreatedDisabled) {
                if (this.hasComplexCreated) {
                    this.createField('createdBy', null, null, null, true);
                    this.createField('createdAt', null, null, null, true);
                    if (!this.model.get('createdById')) {
                        this.recordViewObject.hideField('complexCreated');
                    }
                }
            } else {
                this.recordViewObject.hideField('complexCreated');
            }

            if (!this.complexModifiedDisabled) {
                if (this.hasComplexModified) {
                    this.createField('modifiedBy', null, null, null, true);
                    this.createField('modifiedAt', null, null, null, true);
                }
                if (!this.model.get('modifiedById')) {
                    this.recordViewObject.hideField('complexModified');
                }
            } else {
                this.recordViewObject.hideField('complexModified');
            }

            if (!this.complexCreatedDisabled && this.hasComplexCreated) {
                this.listenTo(this.model, 'change:createdById', function () {
                    if (!this.model.get('createdById')) return;
                    this.recordViewObject.showField('complexCreated');
                }, this);
            }
            if (!this.complexModifiedDisabled && this.hasComplexModified) {
                this.listenTo(this.model, 'change:modifiedById', function () {
                    if (!this.model.get('modifiedById')) return;
                    this.recordViewObject.showField('complexModified');
                }, this);
            }

            if (this.getMetadata().get(['scopes', this.model.name ,'stream']) && !this.getUser().isPortal()) {
                this.createField('followers', 'views/fields/followers', null, null, true);
                this.controlFollowersField();
                this.listenTo(this.model, 'change:followersIds', this.controlFollowersField, this);
            }
        },

        controlFollowersField: function () {
            if (this.model.get('followersIds') && this.model.get('followersIds').length) {
                this.recordViewObject.showField('followers');
            } else {
                this.recordViewObject.hideField('followers');
            }
        }
    });
});
