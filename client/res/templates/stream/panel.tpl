<div class="form-group post-container{{#if postDisabled}} hidden{{/if}}">
    <textarea class="note form-control" rows="1" cols="10" placeholder="{{placeholderText}}"></textarea>
    <div class="buttons-panel margin hide floated-row clearfix">
        <div>
            <button class="btn btn-primary post">{{translate 'Post'}}</button>
        </div>
        <div class="attachments-container field">
            {{{attachments}}}
        </div>
        <a href="javascript:" class="text-muted pull-right stream-post-info">
        <span class="fas fa-info-circle"></span>
        </a>
    </div>
</div>

<div class="list-container">
    {{{list}}}
</div>
