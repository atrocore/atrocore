
Espo.define('views/email/fields/email-address', ['views/fields/base'], function (Dep) {

    return Dep.extend({

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$input = this.$el.find('input');

            if (this.mode == 'search') {
                this.$input.autocomplete({
                    serviceUrl: function (q) {
                        return 'EmailAddress/action/searchInAddressBook?limit=5';
                    }.bind(this),
                    paramName: 'q',
                    minChars: 1,
                    autoSelectFirst: true,
                    formatResult: function (suggestion) {
                        return suggestion.name + ' &#60;' + suggestion.id + '&#62;';
                    },
                    transformResult: function (response) {
                        var response = JSON.parse(response);
                        var list = [];
                        response.forEach(function(item) {
                            list.push({
                                id: item.emailAddress,
                                name: item.entityName,
                                emailAddress: item.emailAddress,
                                entityId: item.entityId,
                                entityName: item.entityName,
                                entityType: item.entityType,
                                data: item.emailAddress,
                                value: item.emailAddress
                            });
                        }, this);
                        return {
                            suggestions: list
                        };
                    }.bind(this),
                    onSelect: function (s) {
                        this.$input.val(s.emailAddress);
                    }.bind(this)
                });

                this.once('render', function () {
                    this.$input.autocomplete('dispose');
                }, this);
                this.once('remove', function () {
                    this.$input.autocomplete('dispose');
                }, this);
            }
        },


        fetchSearch: function () {
            var value = this.$element.val();
            if (typeof value.trim === 'function') {
                value = value.trim();
            }
            if (value) {
                var data = {
                    type: 'equals',
                    value: value
                }
                return data;
            }
            return false;
        }

    });

});
