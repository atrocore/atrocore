<div class="form-group post-container{{#if postDisabled}} hidden{{/if}}">
    <div class="text-container field">
        {{{post}}}
    </div>
    <div class="buttons-panel margin  floated-row clearfix">
        <div class="attachments-container field">
            {{{attachments}}}
        </div>
        <div>
            <button class="btn btn-primary post" title="{{ translate 'streamPostInfo' category='messages' }}">{{translate 'Post'}}</button>
        </div>
    </div>
</div>
{{{streamHeader}}}
<div class="list-container">
    {{{list}}}
</div>

<style>
    .buttons-panel > div {
        margin-top: 10px;
    }
</style>