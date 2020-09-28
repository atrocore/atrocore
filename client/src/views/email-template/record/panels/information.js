

Espo.define('views/email-template/record/panels/information', 'views/record/panels/side', function (Dep) {

    return Dep.extend({

        _template: '{{{infoText}}}',

        data: function () {
            var infoText = '';
            var placeholderList = this.getMetadata().get(['clientDefs', 'EmailTemplate', 'placeholderList']);
            if (placeholderList.length) {
                infoText += '<h4>' + this.translate('Available placeholders') + ':</h4>';
                infoText += '<ul>';
                placeholderList.forEach(function (item, i) {
                    infoText += '<li>' + '<code>{' + item + '}</code> &#8211; ' + this.translate(item, 'placeholderTexts', 'EmailTemplate');
                    if (i === placeholderList.length - 1) {
                        infoText += '.';
                    } else {
                        infoText += ';';
                    }

                    infoText += '</li>';
                }, this);
                infoText += '</ul>';

                infoText = '<span class="complex-text">' + infoText + '</span>';
            }

            return {
                infoText: infoText
            };
        }

    });

});