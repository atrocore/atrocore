<div class="form-group post-container{{#if postDisabled}} hidden{{/if}}">
    <div class="text-container field">
        {{{post}}}
    </div>
    <div class="buttons-panel margin hide floated-row clearfix">
        <div>
            <button class="btn btn-primary post" title="{{ translate 'streamPostInfo' category='messages' }}">{{translate 'Post'}}</button>
        </div>
        <div class="attachments-container field">
            {{{attachments}}}
        </div>
    </div>
</div>

<div class="list-container">
    {{{list}}}
</div>
