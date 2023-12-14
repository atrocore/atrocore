<div class="panel panel-default">
    <div class="panel-heading clearfix">
        <span class="panel-heading-title">{{translate 'queueManager' category='labels' scope="QueueItem"}}</span>
        <span class="pull-right">
            <a href="javascript:" class="close" data-action="close"><span aria-hidden="true">×</span></a>
            <a href="javascript:" title="{{translate 'Start'}}" class="qm-button" data-action="start-qm">{{translate 'Start'}}</a>
            <a href="javascript:" title="{{translate 'Pause'}}" class="qm-button" data-action="pause-qm">{{translate 'Pause'}}</a>
            <a href="#QueueItem" title="{{translate 'View List'}}" style="margin-left: 5px" data-action="viewList">{{translate 'View List'}}</a>
        </span>
    </div>
    <div class="panel-body">
        <div class="list-container">
            {{translate 'Loading...'}}
        </div>
    </div>
</div>
