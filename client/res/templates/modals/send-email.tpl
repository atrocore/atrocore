<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="emailTo">{{translate 'emailTo' scope='Action' category='fields'}}</label>
        <div class="field" data-name="emailTo">{{{emailTo}}}</div>
    </div>
</div>
<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="emailCc">{{translate 'emailCc' scope='Action' category='fields'}}</label>
        <div class="field" data-name="emailCc">{{{emailCc}}}</div>
    </div>
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="emailBcc">{{translate 'emailBcc' scope='Action' category='fields'}}</label>
        <div class="field" data-name="emailBcc">{{{emailBcc}}}</div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12 cell form-group">
        <label class="control-label" data-name="subject">{{translate 'subject' scope='EmailTemplate' category='fields'}}</label>
        <div class="field" data-name="subject">{{{subject}}}</div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12 cell form-group">
        <label class="control-label" data-name="body">{{translate 'body' scope='EmailTemplate' category='fields'}}</label>
        <div class="field" data-name="body">{{{body}}}</div>
    </div>
</div>
{{#if allowAttachments}}
<div class="row">
    <div class="col-xs-6 cell form-group">
        <label class="control-label" data-name="attachments">{{translate 'attachments' scope='EmailTemplate' category='fields'}}</label>
        <div class="field" data-name="attachments">{{{attachments}}}</div>
    </div>
</div>
{{/if}}