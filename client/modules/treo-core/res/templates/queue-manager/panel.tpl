<div class="panel panel-default">
    <div class="panel-heading clearfix">
        <span>{{translate 'queueManager' category='labels' scope="QueueItem"}}</span>
        <span class="pull-right">
            <label class="show-done">
                <input type="checkbox" name="showDone" {{#if showDone}}checked{{/if}}>
                <span class="text-muted">{{translate 'showDone' category='labels' scope="QueueItem"}}</span>
            </label>
            <a href="#QueueItem" title="{{translate 'View List'}}" data-action="viewList">{{translate 'View List'}}</a>
        </span>
    </div>
    <div class="panel-body">
        <div class="list-container">
            {{translate 'Loading...'}}
        </div>
    </div>
</div>
