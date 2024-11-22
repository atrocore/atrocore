    {{#unless noEdit}}
    <div class="pull-right right-container">
    {{{right}}}
    </div>
    {{/unless}}

    <div class="stream-head-container">
        <div class="pull-left">
            {{{avatar}}}
        </div>
        <div class="stream-head-text-container">
            <span class="text-muted"><span class="{{emailIconClassName}} action icon"></span>
                {{{message}}}
            </span>
            <a href="javascript:" data-action="expandDetails"><span class="fas fa-angle-down"></span></a>
        </div>
    </div>

    <div class="stream-date-container">
        <span class="text-muted small">{{{createdAt}}}</span>
    </div>

<div class="hidden details stream-details-container">
        <div class="panel">
                <div class="row">
        <div class="cell col-md-12 col-lg-6 form-group">
            <label class="control-label">{{translate 'emailTo' category='fields' scope='EmailTemplate'}}</label>
            <div class="field">{{{emailTo}}}</div>
        </div>
        <div class="cell col-md-12 col-lg-6 form-group">
            <label class="control-label">{{translate 'emailCc' category='fields' scope='EmailTemplate'}}</label>
            <div class="field">{{{emailCc}}}</div>
        </div>
        </div>
        <div class="row">
        <div class="cell col-md-12 form-group">
            <label class="control-label">{{translate 'subject' category='fields' scope='EmailTemplate'}}</label>
            <div class="field">{{{subject}}}</div>
        </div>
        <div class="cell col-md-12 form-group">
            <label class="control-label">{{translate 'body' category='fields' scope='EmailTemplate'}}</label>
            <div class="field">{{{body}}}</div>
        </div>
</div>

            </div>
    </div>
