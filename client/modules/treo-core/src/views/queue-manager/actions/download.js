

Espo.define('treo-core:views/queue-manager/actions/download', 'treo-core:views/queue-manager/actions/abstract-action',
    Dep => Dep.extend({

        buttonLabel: 'download',

        runAction() {
            let url = this.getBasePath() + '?entryPoint=download&id=' + this.actionData.attachmentId;
            window.open(url, '_blank');
        }
    })
);

