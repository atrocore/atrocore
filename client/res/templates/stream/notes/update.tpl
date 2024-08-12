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
            <div class="stream-date-container">
                <span class="text-muted small">{{{createdAt}}}</span>
            </div>
                {{#if showInline}}
                    {{#each fieldsArr}}
                        <span class="text-muted message">{{{../message}}} <code>{{label}}</code> {{translate 'from' category='streamMessages' scope='Global'}}&nbsp;<{{htmlTag}} class="was">{{{var was ../this}}}</{{htmlTag}}>&nbsp;{{translate 'to' category='streamMessages' scope='Global'}} <{{htmlTag}} class="became">{{{var became ../this}}}</{{htmlTag}}></span>
                    {{/each}}
                {{/if}}
                {{#if showCommon}}
                    <span class="text-muted message">{{{message}}} {{{changedFieldsStr}}}</span>
                    <a href="javascript:" data-action="expandDetails"><span class="fas fa-angle-down"></span></a>
                {{/if}}
        </div>
    </div>

    <div class="hidden details stream-details-container">

        {{#if showCommon}}
            {{#if detailFieldsArr}}
            <div class="panel">
                {{#each detailFieldsArr}}
                    <div class="row">
            <div class="cell col-md-12 col-lg-6 form-group">
                <label class="control-label"><code>{{label}}</code> {{translate 'was' category='streamMessages' scope='Global'}}</label>
                <div class="field">{{{var was ../this}}}</div>
            </div>
            <div class="cell col-md-12 col-lg-6 form-group">
                <label class="control-label"><code>{{label}}</code> {{translate 'become' category='streamMessages' scope='Global'}}</label>
                <div class="field">{{{var became ../this}}}</div>
            </div>
            </div>
                {{/each}}
                </div>
            {{/if}}
        {{/if}}
        {{#if showDiff }}
            <div class="panel diff">
                {{#each diffArr}}
                    <div class="row">
                        <div class="cell col-md-12 col-lg-12 form-group">
                            <label class="control-label"><code>{{field}}</code> {{translate 'changed' category='streamMessages' scope='Global'}}</label>
                            <div class="field">{{{diff}}}</div>
                        </div>
                    </div>
                {{/each}}
            <div>
        {{/if}}
    </div>

