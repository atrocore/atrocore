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
              {{#if showInline}}
                     {{#each fieldsArr}}
                <span class="text-muted message">{{{../message}}} <code>{{translate field category='fields' scope=../parentType}}</code> {{translate 'from' category='streamMessages' scope='Global'}}&nbsp;<{{htmlTag}} class="was">{{{var was ../this}}}</{{htmlTag}}>&nbsp;{{translate 'to' category='streamMessages' scope='Global'}} <{{htmlTag}} class="became">{{{var became ../this}}}</{{htmlTag}}></span>
                    {{/each}}
              {{/if}}
              {{#if showCommon}}
                <span class="text-muted message">{{{message}}} {{{changedFieldsStr}}}</span> <a href="javascript:" data-action="expandDetails"><span class="fas fa-angle-down"></span></a>
              {{/if}}
              {{#if showDiff }}
                     {{#each fieldsArr}}
                <span class="text-muted message">{{{../message}}} <code>{{translate field category='fields' scope=../parentType}}</code></span> <a href="javascript:" data-action="expandDetails"><span class="fas fa-angle-down"></span></a>
                    {{/each}}
              {{/if}}
        </div>
    </div>
    <div class="stream-date-container">
        <span class="text-muted small">{{{createdAt}}}</span>
    </div>

    <div class="hidden details stream-details-container">

        {{#if showCommon}}
        <div class="panel">
            {{#each fieldsArr}}
                <div class="row">
        <div class="cell col-md-12 col-lg-6 form-group">
            <label class="control-label">{{translate field category='fields' scope=../parentType}} {{translate 'was' category='streamMessages' scope='Global'}}</label>
            <div class="field">{{{var was ../this}}}</div>
        </div>
        <div class="cell col-md-12 col-lg-6 form-group">
            <label class="control-label">{{translate field category='fields' scope=../parentType}} {{translate 'become' category='streamMessages' scope='Global'}}</label>
            <div class="field">{{{var became ../this}}}</div>
        </div>
        </div>
            {{/each}}
            </div>
        {{/if}}
        {{#if showDiff }}
                <div class="panel diff">
               {{{diff}}}
               </div>
         {{/if}}
    </div>

