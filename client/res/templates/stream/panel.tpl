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
{{{streamHeader}}}
<div class="list-container">
    {{{list}}}
</div>

<style>
    .panel-stream .header {
        width: 100%;
        border-bottom: 1px solid #e8eced;
        display: flex;
        padding: 20px 0;
        justify-content: space-between;
    }

    .panel-stream .header .filter .filter-item {
        border: 1px solid transparent;
        color: var(--primary-font-color);
        margin-right: 10px;
        padding: 5px 10px;
    }

    .panel-stream .header .filter .filter-item:hover,  .panel-stream .header .filter .filter-item:focus {
        text-decoration: none;
    }

    .panel-stream .header .filter .filter-item.active {
        border: 1px solid rgb(126 183 241);
        border-radius: 5px;
        background-color: rgba(126, 183, 241, 0.25);
    }

    .panel-stream .header .filter .filter-item:hover {
        border: 1px solid rgb(126 183 241);
        border-radius: 5px;
        background-color: rgba(126, 183, 241, 0.1);
    }
</style>
