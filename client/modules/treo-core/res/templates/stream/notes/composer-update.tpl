<div class="stream-head-container">
    <div class="pull-left">
        {{{avatar}}}
    </div>
    <div class="stream-head-text-container">
        <span class="text-muted message">{{{message}}}</span>
    </div>
</div>

<div class="stream-post-container">
    <span class="cell cell-post">
        <span class="complex-text">
            <p>{{translate 'Status'}}: <b style="color: {{#if fail}}#ff8080{{else}}#08cc08{{/if}}">{{#if fail}}{{translate 'fail'}}{{else}}{{translate 'success'}}{{/if}}</b></p>
            <p><a class="action" href="javascript:" data-action="showUpdateDetails">{{translate 'viewDetails'}}</a></p>
        </span>
    </span>
</div>

<div class="stream-date-container">
    <span class="text-muted small">{{{createdAt}}}</span>
</div>


