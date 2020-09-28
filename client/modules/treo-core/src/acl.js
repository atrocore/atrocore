

Espo.define('treo-core:acl', 'class-replace!treo-core:acl', Acl => {

    _.extend(Acl.prototype, {

        checkIsOwner(model) {
            let result = false;

            if (model.hasField('assignedUser') || model.hasField('ownerUser')) {
                if (this.getUser().id === model.get('assignedUserId') || this.getUser().id === model.get('ownerUserId')) {
                    return true;
                } else {
                    if (!model.has('assignedUserId') && !model.has('ownerUserId')) {
                        result = null;
                    }
                }
            } else {
                if (model.hasField('createdBy')) {
                    if (this.getUser().id === model.get('createdById')) {
                        return true;
                    } else {
                        if (!model.has('createdById')) {
                            result = null;
                        }
                    }
                }
            }

            if (model.hasField('assignedUsers')) {
                if (!model.has('assignedUsersIds')) {
                    return null;
                }

                if (~(model.get('assignedUsersIds') || []).indexOf(this.getUser().id)) {
                    return true;
                } else {
                    result = false;
                }
            }

            return result;
        }

    });

    return Acl;
});